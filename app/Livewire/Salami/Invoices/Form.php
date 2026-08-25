<?php

namespace App\Livewire\Salami\Invoices;

use App\Enums\SalamiItemUnit;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Salami\InvoiceException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Services\Salami\InvoiceService;
use App\Support\Quantity;
use App\Support\SalamiUnitConversion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public string $customerId = '';

    public string $deliveryAgentId = '';

    public string $discountAmount = '0.000';

    /**
     * @var list<array{product_id: string, unit_type: string, pieces_per_box: string, quantity: string, unit_price: string}>
     */
    public array $items = [];

    public ?SalamiInvoice $invoice = null;

    public string $invoiceDate = '';

    public string $notes = '';

    public string $paidAmount = '0.000';

    // Start new invoices as credit so an empty draft can be saved safely.
    // Cash invoices still require the employee to enter the full paid amount.
    public string $paymentType = 'credit';

    public function mount(?SalamiInvoice $invoice = null): void
    {
        $this->authorizeSalamiInvoices();
        $this->invoice = $invoice;

        if (! $invoice instanceof SalamiInvoice) {
            $this->invoiceDate = today()->toDateString();
            $this->addItem();

            return;
        }

        if ($invoice->status->value !== 'draft') {
            $this->redirectRoute('salami.invoices.show', ['invoice' => $invoice], navigate: true);

            return;
        }

        $this->deliveryAgentId = (string) ($invoice->delivery_agent_id ?? '');
        $this->customerId = (string) $invoice->customer_id;
        $this->invoiceDate = $invoice->invoice_date->toDateString();
        $this->paymentType = $invoice->payment_type->value;
        $this->discountAmount = $invoice->discount_amount;
        $this->paidAmount = $invoice->paid_amount;
        $this->notes = $invoice->notes ?? '';
        $this->items = $invoice->items()
            ->orderBy('product_id')
            ->get()
            ->map(fn ($item): array => [
                'product_id' => (string) $item->product_id,
                'unit_type' => SalamiItemUnit::fromStoredLabel($item->unit)->value,
                'pieces_per_box' => $item->unit === 'صندوق' ? $item->conversion_factor : '1',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])
            ->all();
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
        if (! str_ends_with($path, '.product_id') || ! is_numeric($value)) {
            return;
        }

        $index = (int) (explode('.', $path)[0] ?? -1);
        $product = SalamiProduct::query()->find((int) $value);

        if (isset($this->items[$index]) && $product instanceof SalamiProduct) {
            $this->items[$index]['unit_price'] = $product->sale_price ?? '0.000';
        }
    }

    public function updatedDeliveryAgentId(): void
    {
        $this->customerId = '';
    }

    public function saveDraft(InvoiceService $invoiceService): mixed
    {
        $invoice = $this->persistDraft($invoiceService);

        if (! $invoice instanceof SalamiInvoice) {
            return null;
        }

        session()->flash('status', 'تم حفظ الفاتورة كمسودة. لم يتغير المخزون.');

        return $this->redirectRoute('salami.invoices.show', ['invoice' => $invoice], navigate: true);
    }

    public function confirm(InvoiceService $invoiceService): mixed
    {
        $invoice = $this->persistDraft($invoiceService);

        if (! $invoice instanceof SalamiInvoice) {
            return null;
        }

        try {
            $invoice = $invoiceService->confirm($invoice, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvoiceException $exception) {
            $this->addError('invoice', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم اعتماد الفاتورة وخصم الكميات من المخزون.');

        return $this->redirectRoute('salami.invoices.show', ['invoice' => $invoice], navigate: true);
    }

    /**
     * @return array{items: list<array{line_total: string}>, subtotal: string, discount: string, total: string, paid: string, remaining: string, valid: bool}
     */
    public function preview(): array
    {
        $subtotal = Quantity::zero();
        $itemPreviews = [];
        $valid = true;

        foreach ($this->items as $item) {
            try {
                $quantity = Quantity::from($item['quantity'] ?? '0');
                $price = Quantity::from($item['unit_price'] ?? '0');

                if (! $quantity->isPositive() || $price->isNegative()) {
                    throw new InvalidStockQuantityException('Invalid invoice item.');
                }

                $conversion = SalamiUnitConversion::fromInput(
                    (string) ($item['unit_type'] ?? 'piece'),
                    (string) ($item['quantity'] ?? '0'),
                    $item['pieces_per_box'] ?? null,
                );
                $lineTotal = $quantity->multipliedBy($price);
                $subtotal = $subtotal->plus($lineTotal);
                $itemPreviews[] = ['line_total' => $lineTotal->toString(), 'stock_quantity' => $conversion['stock_quantity']->toString()];
            } catch (InvalidStockQuantityException|ValidationException) {
                $valid = false;
                $itemPreviews[] = ['line_total' => '0.000', 'stock_quantity' => '0.000'];
            }
        }

        try {
            $discount = Quantity::from($this->discountAmount === '' ? '0' : $this->discountAmount);
            $paid = Quantity::from($this->paidAmount === '' ? '0' : $this->paidAmount);

            if ($discount->isNegative() || $paid->isNegative() || $discount->isGreaterThan($subtotal)) {
                throw new InvalidStockQuantityException('Invalid invoice amount.');
            }

            $total = $subtotal->minus($discount);

            if ($paid->isGreaterThan($total)) {
                throw new InvalidStockQuantityException('Invalid paid amount.');
            }
        } catch (InvalidStockQuantityException) {
            $valid = false;
            $discount = Quantity::zero();
            $paid = Quantity::zero();
            $total = $subtotal;
        }

        return [
            'items' => $itemPreviews,
            'subtotal' => $subtotal->toString(),
            'discount' => $discount->toString(),
            'total' => $total->toString(),
            'paid' => $paid->toString(),
            'remaining' => $total->minus($paid)->toString(),
            'valid' => $valid,
        ];
    }

    protected function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'exists:salami_customers,id'],
            'invoiceDate' => ['required', 'date'],
            'paymentType' => ['required', 'in:cash,credit,partial'],
            'discountAmount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'paidAmount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'deliveryAgentId' => ['required', 'integer', Rule::exists('salami_delivery_agents', 'id')->where('is_active', true)],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:salami_products,id'],
            'items.*.unit_type' => ['required', 'in:piece,box'],
            'items.*.pieces_per_box' => ['nullable', 'regex:/^[1-9]\d*$/'],
            'items.*.quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.unit_price' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ];
    }

    public function render(): View
    {
        $selectedIds = collect($this->items)->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $products = SalamiProduct::query()
            ->where(function ($query) use ($selectedIds): void {
                $query->where('is_active', true);

                if ($selectedIds !== []) {
                    $query->orWhereIn('id', $selectedIds);
                }
            })
            ->orderBy('name')
            ->get();
        $customers = SalamiCustomer::query()
            ->where('is_active', true)
            ->when($this->deliveryAgentId !== '', fn ($query) => $query->where('delivery_agent_id', $this->deliveryAgentId))
            ->orderBy('name')
            ->get();
        $deliveryAgents = SalamiDeliveryAgent::query()
            ->where('is_active', true)
            ->when($this->deliveryAgentId !== '', fn ($query) => $query->orWhere('id', $this->deliveryAgentId))
            ->orderBy('name')
            ->get();

        return view('livewire.salami.invoices.form', [
            'customers' => $customers,
            'deliveryAgents' => $deliveryAgents,
            'productLookup' => $products->keyBy('id'),
            'products' => $products,
        ]);
    }

    private function persistDraft(InvoiceService $invoiceService): ?SalamiInvoice
    {
        $this->authorizeSalamiInvoices();
        $validated = $this->validate();
        $attributes = [
            'customer_id' => $validated['customerId'],
            'delivery_agent_id' => $validated['deliveryAgentId'],
            'invoice_date' => $validated['invoiceDate'],
            'payment_type' => $validated['paymentType'],
            'discount_amount' => $validated['discountAmount'],
            'paid_amount' => $validated['paidAmount'],
            'notes' => $validated['notes'],
        ];

        try {
            $this->invoice = $this->invoice instanceof SalamiInvoice
                ? $invoiceService->updateDraft($this->invoice, $attributes, $validated['items'], auth()->user())
                : $invoiceService->createDraft($attributes, $validated['items'], auth()->user());

            return $this->invoice;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
        } catch (InvoiceException $exception) {
            $this->addError('invoice', $exception->getMessage());
        }

        return null;
    }

    /**
     * @return array{product_id: string, unit_type: string, pieces_per_box: string, quantity: string, unit_price: string}
     */
    private function emptyItem(): array
    {
        return [
            'product_id' => '',
            'unit_type' => 'piece',
            'pieces_per_box' => '1',
            'quantity' => '1.000',
            'unit_price' => '0.000',
        ];
    }
}
