<?php

namespace App\Livewire\Flowers\Reports;

use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerExit;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\FlowerReceiptItem;
use App\Models\StockMovement;
use App\Models\StockWaste;
use App\Models\Supplier;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    private const REPORTS = ['inventory', 'receipts', 'variance', 'arrival_damage', 'waste', 'exits', 'sales', 'movements', 'arrival_history'];

    #[Url]
    public string $report = 'inventory';

    #[Url]
    public string $productId = '';

    #[Url]
    public string $supplierId = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $movementType = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeFlowerReports();
        $this->validReport();
    }

    public function updated(): void
    {
        $this->validReport();
        $this->resetPage();
    }

    public function estimatedValue(FlowerProduct $product): string
    {
        return $product->purchase_price === null ? '—' : Quantity::from($product->current_quantity)->multipliedBy(Quantity::from($product->purchase_price))->toString();
    }

    public function percentage(string $numerator, string $denominator): string
    {
        if (Quantity::from($denominator)->isZero()) {
            return '—';
        }

        return (string) BigDecimal::of($numerator)->multipliedBy('100')->dividedBy($denominator, 3, RoundingMode::HalfUp);
    }

    public function stockStatus(FlowerProduct $product): string
    {
        $current = Quantity::from($product->current_quantity);
        if ($current->isZero()) {
            return 'نفد';
        }

        return $product->minimum_quantity !== null && ! $current->isGreaterThan(Quantity::from($product->minimum_quantity)) ? 'مخزون منخفض' : 'متوفر';
    }

    public function movementLabel(MovementType $type): string
    {
        return match ($type) {
            MovementType::Opening => 'رصيد افتتاحي',MovementType::Receipt => 'استلام',MovementType::Sale => 'بيع',MovementType::ManualExit => 'خروج ورد',MovementType::Waste => 'تالف',MovementType::AdjustmentIn => 'تسوية زيادة',MovementType::AdjustmentOut => 'تسوية نقص',MovementType::Reversal => 'عكس حركة'
        };
    }

    public function render(): View
    {
        return view('livewire.flowers.reports.index', ['rows' => $this->rows(), 'products' => FlowerProduct::query()->orderBy('name')->get(['id', 'name', 'color']), 'suppliers' => Supplier::query()->whereIn('module_scope', ['flower', 'both'])->orderBy('name')->get(['id', 'name']), 'movementTypes' => MovementType::cases()]);
    }

    private function validReport(): void
    {
        if (! in_array($this->report, self::REPORTS, true)) {
            $this->report = 'inventory';
        }
    }

    private function rows(): mixed
    {
        return match ($this->report) {
            'inventory' => FlowerProduct::query()->orderBy('name')->paginate(20), 'receipts','variance','arrival_damage','arrival_history' => $this->receiptRows(), 'waste' => $this->wasteRows(), 'exits' => $this->exitRows(), 'sales' => $this->salesRows(), 'movements' => $this->movementRows()
        };
    }

    private function receiptRows(): mixed
    {
        return FlowerReceiptItem::query()->with(['receipt.supplier', 'product'])->whereHas('receipt', fn (Builder $q) => $q->where('status', ReceiptStatus::Confirmed)->when($this->supplierId !== '', fn (Builder $q) => $q->where('supplier_id', $this->supplierId))->when($this->from !== '', fn (Builder $q) => $q->whereDate('receipt_date', '>=', $this->from))->when($this->to !== '', fn (Builder $q) => $q->whereDate('receipt_date', '<=', $this->to)))->when($this->productId !== '', fn (Builder $q) => $q->where('flower_product_id', $this->productId))->when($this->report === 'variance', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('shortage_quantity', '>', 0)->orWhere('surplus_quantity', '>', 0)))->when($this->report === 'arrival_damage', fn (Builder $q) => $q->where('damaged_quantity', '>', 0))->latest('id')->paginate(20);
    }

    private function wasteRows(): mixed
    {
        return StockWaste::query()->with(['stockable', 'createdBy'])->where('stockable_type', (new FlowerProduct)->getMorphClass())->when($this->productId !== '', fn (Builder $q) => $q->where('stockable_id', $this->productId))->when($this->from !== '', fn (Builder $q) => $q->whereDate('waste_date', '>=', $this->from))->when($this->to !== '', fn (Builder $q) => $q->whereDate('waste_date', '<=', $this->to))->latest('waste_date')->paginate(20);
    }

    private function exitRows(): mixed
    {
        return FlowerExit::query()->with(['product', 'createdBy'])->when($this->productId !== '', fn (Builder $q) => $q->where('flower_product_id', $this->productId))->when($this->from !== '', fn (Builder $q) => $q->whereDate('exit_date', '>=', $this->from))->when($this->to !== '', fn (Builder $q) => $q->whereDate('exit_date', '<=', $this->to))->latest('exit_date')->paginate(20);
    }

    private function salesRows(): mixed
    {
        return FlowerInvoice::query()->with('items.product')->when($this->productId !== '', fn (Builder $q) => $q->whereHas('items', fn (Builder $q) => $q->where('flower_product_id', $this->productId)))->when($this->paymentStatus !== '', fn (Builder $q) => $q->where('payment_status', $this->paymentStatus))->when($this->from !== '', fn (Builder $q) => $q->whereDate('invoice_date', '>=', $this->from))->when($this->to !== '', fn (Builder $q) => $q->whereDate('invoice_date', '<=', $this->to))->latest('invoice_date')->paginate(20);
    }

    private function movementRows(): mixed
    {
        return StockMovement::query()->with(['stockable', 'reference', 'createdBy'])->where('stockable_type', (new FlowerProduct)->getMorphClass())->when($this->productId !== '', fn (Builder $q) => $q->where('stockable_id', $this->productId))->when($this->movementType !== '', fn (Builder $q) => $q->where('movement_type', $this->movementType))->when($this->from !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $this->from))->when($this->to !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $this->to))->latest('occurred_at')->paginate(20);
    }
}
