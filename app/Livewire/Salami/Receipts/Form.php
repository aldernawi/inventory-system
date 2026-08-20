<?php

namespace App\Livewire\Salami\Receipts;

use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Salami\ReceivingException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use App\Services\Salami\ReceivingService;
use App\Support\ReceivingQuantities;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public ?SalamiReceipt $receipt = null;

    public string $supplierId = '';

    public string $supplierInvoiceNumber = '';

    public string $receiptDate = '';

    public string $notes = '';

    /**
     * @var list<array{product_id: string, expected_quantity: string, received_quantity: string, damaged_quantity: string, purchase_price: string, notes: string}>
     */
    public array $items = [];

    public function mount(?SalamiReceipt $receipt = null): void
    {
        $this->authorizeSalamiReceipts();
        $this->receipt = $receipt;

        if (! $receipt instanceof SalamiReceipt) {
            $this->receiptDate = today()->toDateString();
            $this->addItem();

            return;
        }

        if ($receipt->status->value !== 'draft') {
            $this->redirectRoute('salami.receipts.show', ['receipt' => $receipt], navigate: true);

            return;
        }

        $this->supplierId = (string) $receipt->supplier_id;
        $this->supplierInvoiceNumber = $receipt->supplier_invoice_number ?? '';
        $this->receiptDate = $receipt->receipt_date->toDateString();
        $this->notes = $receipt->notes ?? '';
        $this->items = $receipt->items()
            ->orderBy('product_id')
            ->get()
            ->map(fn ($item): array => [
                'product_id' => (string) $item->product_id,
                'expected_quantity' => $item->expected_quantity,
                'received_quantity' => $item->received_quantity,
                'damaged_quantity' => $item->damaged_quantity,
                'purchase_price' => $item->purchase_price ?? '',
                'notes' => $item->notes ?? '',
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

        $segments = explode('.', $path);
        $index = (int) ($segments[0] ?? -1);
        $product = SalamiProduct::query()->find((int) $value);

        if (isset($this->items[$index]) && $product instanceof SalamiProduct) {
            $this->items[$index]['purchase_price'] = $product->purchase_price ?? '';
        }
    }

    public function saveDraft(ReceivingService $receivingService): mixed
    {
        $receipt = $this->persistDraft($receivingService);

        if (! $receipt instanceof SalamiReceipt) {
            return null;
        }

        session()->flash('status', 'تم حفظ الإيصال كمسودة. لم يتغير المخزون.');

        return $this->redirectRoute('salami.receipts.show', ['receipt' => $receipt], navigate: true);
    }

    public function confirm(ReceivingService $receivingService): mixed
    {
        $receipt = $this->persistDraft($receivingService);

        if (! $receipt instanceof SalamiReceipt) {
            return null;
        }

        try {
            $receipt = $receivingService->confirm($receipt, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (ReceivingException $exception) {
            $this->addError('items', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم اعتماد الإيصال وإضافة الكمية المقبولة إلى المخزون.');

        return $this->redirectRoute('salami.receipts.show', ['receipt' => $receipt], navigate: true);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{expected_quantity: string, received_quantity: string, damaged_quantity: string, shortage_quantity: string, surplus_quantity: string, accepted_quantity: string, valid: bool}
     */
    public function preview(array $item): array
    {
        try {
            return [...ReceivingQuantities::calculate(
                $item['expected_quantity'] ?? '0',
                $item['received_quantity'] ?? '0',
                $item['damaged_quantity'] ?? '0',
            ), 'valid' => true];
        } catch (InvalidStockQuantityException) {
            return [
                'expected_quantity' => '0.000',
                'received_quantity' => '0.000',
                'damaged_quantity' => '0.000',
                'shortage_quantity' => '0.000',
                'surplus_quantity' => '0.000',
                'accepted_quantity' => '0.000',
                'valid' => false,
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'supplierInvoiceNumber' => ['nullable', 'string', 'max:255'],
            'receiptDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:salami_products,id'],
            'items.*.expected_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.received_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.damaged_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.purchase_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.notes' => ['nullable', 'string'],
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
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->whereIn('module_scope', ['salami', 'both'])
            ->orderBy('name')
            ->get();

        return view('livewire.salami.receipts.form', [
            'products' => $products,
            'productLookup' => $products->keyBy('id'),
            'suppliers' => $suppliers,
        ]);
    }

    private function persistDraft(ReceivingService $receivingService): ?SalamiReceipt
    {
        $this->authorizeSalamiReceipts();
        $validated = $this->validate();

        try {
            $attributes = [
                'supplier_id' => $validated['supplierId'],
                'supplier_invoice_number' => $validated['supplierInvoiceNumber'],
                'receipt_date' => $validated['receiptDate'],
                'notes' => $validated['notes'],
            ];

            $this->receipt = $this->receipt instanceof SalamiReceipt
                ? $receivingService->updateDraft($this->receipt, $attributes, $validated['items'], auth()->user())
                : $receivingService->createDraft($attributes, $validated['items'], auth()->user());

            return $this->receipt;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
        } catch (InvalidStockQuantityException $exception) {
            $this->addError('items', $exception->getMessage());
        } catch (ReceivingException $exception) {
            $this->addError('items', $exception->getMessage());
        }

        return null;
    }

    /**
     * @return array{product_id: string, expected_quantity: string, received_quantity: string, damaged_quantity: string, purchase_price: string, notes: string}
     */
    private function emptyItem(): array
    {
        return [
            'product_id' => '',
            'expected_quantity' => '0.000',
            'received_quantity' => '0.000',
            'damaged_quantity' => '0.000',
            'purchase_price' => '',
            'notes' => '',
        ];
    }
}
