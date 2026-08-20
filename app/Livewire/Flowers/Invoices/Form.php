<?php

namespace App\Livewire\Flowers\Invoices;

use App\Exceptions\Flowers\InvoiceException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Services\Flowers\InvoiceService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public ?FlowerInvoice $invoice = null;

    public string $recipientName = '';

    public string $invoiceDate = '';

    public string $paymentType = 'credit';

    public string $discountAmount = '0.000';

    public string $paidAmount = '0.000';

    public string $notes = '';

    /** @var list<array{flower_product_id:string,quantity:string,unit_price:string}> */
    public array $items = [];

    public function mount(?FlowerInvoice $invoice = null): void
    {
        $this->authorizeFlowerInvoices();
        $this->invoice = $invoice;
        if (! $invoice instanceof FlowerInvoice) {
            $this->invoiceDate = today()->toDateString();
            $this->addItem();

            return;
        }
        if ($invoice->status->value !== 'draft') {
            $this->redirectRoute('flowers.invoices.show', ['invoice' => $invoice], navigate: true);

            return;
        }
        $this->recipientName = $invoice->recipient_name ?? '';
        $this->invoiceDate = $invoice->invoice_date->toDateString();
        $this->paymentType = $invoice->payment_type->value;
        $this->discountAmount = $invoice->discount_amount;
        $this->paidAmount = $invoice->paid_amount;
        $this->notes = $invoice->notes ?? '';
        $this->items = $invoice->items()->orderBy('flower_product_id')->get()->map(fn ($item) => ['flower_product_id' => (string) $item->flower_product_id, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price])->all();
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function updatedItems(mixed $value, string $path): void
    {
        if (! str_ends_with($path, '.flower_product_id') || ! is_numeric($value)) {
            return;
        }
        $index = (int) (explode('.', $path)[0] ?? -1);
        $product = FlowerProduct::query()->find((int) $value);
        if (isset($this->items[$index]) && $product instanceof FlowerProduct) {
            $this->items[$index]['unit_price'] = $product->sale_price ?? '0.000';
        }
    }

    public function saveDraft(InvoiceService $service): mixed
    {
        $invoice = $this->persistDraft($service);
        if (! $invoice) {
            return null;
        }
        session()->flash('status', 'تم حفظ فاتورة الورد كمسودة. لم يتغير المخزون.');

        return $this->redirectRoute('flowers.invoices.show', ['invoice' => $invoice], navigate: true);
    }

    public function confirm(InvoiceService $service): mixed
    {
        $invoice = $this->persistDraft($service);
        if (! $invoice) {
            return null;
        }
        try {
            $invoice = $service->confirm($invoice, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return null;
        } catch (InvoiceException $e) {
            $this->addError('invoice', $e->getMessage());

            return null;
        }
        session()->flash('status', 'تم اعتماد فاتورة الورد وخصم الكميات من المخزون.');

        return $this->redirectRoute('flowers.invoices.show', ['invoice' => $invoice], navigate: true);
    }

    /** @return array{items:list<array{line_total:string}>,subtotal:string,discount:string,total:string,paid:string,remaining:string,valid:bool} */
    public function preview(): array
    {
        $subtotal = Quantity::zero();
        $previews = [];
        $valid = true;
        foreach ($this->items as $item) {
            try {
                $q = Quantity::from($item['quantity'] ?? '0');
                $p = Quantity::from($item['unit_price'] ?? '0');
                if (! $q->isPositive() || $p->isNegative()) {
                    throw new InvalidStockQuantityException('invalid');
                } $line = $q->multipliedBy($p);
                $subtotal = $subtotal->plus($line);
                $previews[] = ['line_total' => $line->toString()];
            } catch (InvalidStockQuantityException) {
                $valid = false;
                $previews[] = ['line_total' => '0.000'];
            }
        }
        try {
            $discount = Quantity::from($this->discountAmount === '' ? '0' : $this->discountAmount);
            $paid = Quantity::from($this->paidAmount === '' ? '0' : $this->paidAmount);
            if ($discount->isNegative() || $paid->isNegative() || $discount->isGreaterThan($subtotal)) {
                throw new InvalidStockQuantityException('invalid');
            } $total = $subtotal->minus($discount);
            if ($paid->isGreaterThan($total)) {
                throw new InvalidStockQuantityException('invalid');
            }
        } catch (InvalidStockQuantityException) {
            $valid = false;
            $discount = Quantity::zero();
            $paid = Quantity::zero();
            $total = $subtotal;
        }

        return ['items' => $previews, 'subtotal' => $subtotal->toString(), 'discount' => $discount->toString(), 'total' => $total->toString(), 'paid' => $paid->toString(), 'remaining' => $total->minus($paid)->toString(), 'valid' => $valid];
    }

    protected function rules(): array
    {
        return ['recipientName' => ['nullable', 'string', 'max:255'], 'invoiceDate' => ['required', 'date'], 'paymentType' => ['required', 'in:cash,credit,partial'], 'discountAmount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'paidAmount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.flower_product_id' => ['required', 'integer', 'distinct', 'exists:flower_products,id'], 'items.*.quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'items.*.unit_price' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/']];
    }

    public function render(): View
    {
        $selected = collect($this->items)->pluck('flower_product_id')->filter()->map(fn ($id) => (int) $id)->all();
        $products = FlowerProduct::query()->where(fn ($q) => $q->where('is_active', true)->when($selected !== [], fn ($q) => $q->orWhereIn('id', $selected)))->orderBy('name')->get();

        return view('livewire.flowers.invoices.form', ['products' => $products, 'productLookup' => $products->keyBy('id')]);
    }

    private function persistDraft(InvoiceService $service): ?FlowerInvoice
    {
        $this->authorizeFlowerInvoices();
        $valid = $this->validate();
        $attrs = ['recipient_name' => $valid['recipientName'], 'invoice_date' => $valid['invoiceDate'], 'payment_type' => $valid['paymentType'], 'discount_amount' => $valid['discountAmount'], 'paid_amount' => $valid['paidAmount'], 'notes' => $valid['notes']];
        try {
            return $this->invoice = $this->invoice instanceof FlowerInvoice ? $service->updateDraft($this->invoice, $attrs, $valid['items'], auth()->user()) : $service->createDraft($attrs, $valid['items'], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
        } catch (InvoiceException $e) {
            $this->addError('invoice', $e->getMessage());
        }

        return null;
    }

    private function emptyItem(): array
    {
        return ['flower_product_id' => '', 'quantity' => '1.000', 'unit_price' => '0.000'];
    }
}
