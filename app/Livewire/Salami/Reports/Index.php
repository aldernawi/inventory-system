<?php

namespace App\Livewire\Salami\Reports;

use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\SalamiReceiptItem;
use App\Models\StockMovement;
use App\Models\StockWaste;
use App\Models\Supplier;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess;
    use WithPagination;

    /** @var list<string> */
    private const REPORTS = ['inventory', 'receipts', 'variance', 'waste', 'sales', 'movements'];

    #[Url]
    public string $customerId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $movementType = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $productId = '';

    #[Url]
    public string $report = 'inventory';

    #[Url]
    public string $supplierId = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeSalamiReports();
        $this->ensureValidReport();
    }

    public function updated(string $property): void
    {
        if ($property === 'report') {
            $this->ensureValidReport();
        }

        $this->resetPage();
    }

    public function estimatedValue(SalamiProduct $product): string
    {
        if ($product->purchase_price === null) {
            return '—';
        }

        return Quantity::from($product->current_quantity)
            ->multipliedBy(Quantity::from($product->purchase_price))
            ->toString();
    }

    public function movementLabel(MovementType $type): string
    {
        return match ($type) {
            MovementType::Opening => 'رصيد افتتاحي',
            MovementType::Receipt => 'استلام',
            MovementType::Sale => 'بيع',
            MovementType::ManualExit => 'خروج يدوي',
            MovementType::Waste => 'تالف',
            MovementType::AdjustmentIn => 'تسوية زيادة',
            MovementType::AdjustmentOut => 'تسوية نقص',
            MovementType::Reversal => 'عكس حركة',
        };
    }

    public function stockStatus(SalamiProduct $product): string
    {
        $current = Quantity::from($product->current_quantity);

        if ($current->isZero()) {
            return 'نفد';
        }

        if ($product->minimum_quantity !== null && ! $current->isGreaterThan(Quantity::from($product->minimum_quantity))) {
            return 'مخزون منخفض';
        }

        return 'متوفر';
    }

    public function render(): View
    {
        $this->ensureValidReport();

        return view('livewire.salami.reports.index', [
            'customers' => SalamiCustomer::query()->orderBy('name')->get(['id', 'name']),
            'movementTypes' => MovementType::cases(),
            'products' => SalamiProduct::query()->orderBy('name')->get(['id', 'name']),
            'rows' => $this->rows(),
            'suppliers' => Supplier::query()->whereIn('module_scope', ['salami', 'both'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function ensureValidReport(): void
    {
        if (! in_array($this->report, self::REPORTS, true)) {
            $this->report = 'inventory';
        }
    }

    private function rows(): mixed
    {
        return match ($this->report) {
            'inventory' => SalamiProduct::query()->orderBy('name')->paginate(20),
            'receipts', 'variance' => $this->receiptRows(),
            'waste' => $this->wasteRows(),
            'sales' => $this->salesRows(),
            'movements' => $this->movementRows(),
        };
    }

    private function receiptRows(): mixed
    {
        return SalamiReceiptItem::query()
            ->with(['receipt.supplier', 'product'])
            ->whereHas('receipt', function (Builder $query): void {
                $query->where('status', ReceiptStatus::Confirmed)
                    ->when($this->supplierId !== '', fn (Builder $query) => $query->where('supplier_id', $this->supplierId))
                    ->when($this->from !== '', fn (Builder $query) => $query->whereDate('receipt_date', '>=', $this->from))
                    ->when($this->to !== '', fn (Builder $query) => $query->whereDate('receipt_date', '<=', $this->to));
            })
            ->when($this->productId !== '', fn (Builder $query) => $query->where('product_id', $this->productId))
            ->when($this->report === 'variance', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('shortage_quantity', '>', '0')->orWhere('surplus_quantity', '>', '0');
                });
            })
            ->latest('id')
            ->paginate(20);
    }

    private function wasteRows(): mixed
    {
        return StockWaste::query()
            ->with(['stockable', 'createdBy'])
            ->where('stockable_type', (new SalamiProduct)->getMorphClass())
            ->when($this->productId !== '', fn (Builder $query) => $query->where('stockable_id', $this->productId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('waste_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('waste_date', '<=', $this->to))
            ->latest('waste_date')
            ->latest('id')
            ->paginate(20);
    }

    private function salesRows(): mixed
    {
        return SalamiInvoice::query()
            ->with('customer')
            ->when($this->customerId !== '', fn (Builder $query) => $query->where('customer_id', $this->customerId))
            ->when($this->productId !== '', fn (Builder $query) => $query->whereHas('items', fn (Builder $query) => $query->where('product_id', $this->productId)))
            ->when($this->paymentStatus !== '', fn (Builder $query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('invoice_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('invoice_date', '<=', $this->to))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(20);
    }

    private function movementRows(): mixed
    {
        return StockMovement::query()
            ->with(['stockable', 'reference', 'createdBy'])
            ->where('stockable_type', (new SalamiProduct)->getMorphClass())
            ->when($this->productId !== '', fn (Builder $query) => $query->where('stockable_id', $this->productId))
            ->when($this->movementType !== '', fn (Builder $query) => $query->where('movement_type', $this->movementType))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $this->to))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20);
    }
}
