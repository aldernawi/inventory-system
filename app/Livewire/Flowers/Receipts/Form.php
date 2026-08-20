<?php

namespace App\Livewire\Flowers\Receipts;

use App\Exceptions\Flowers\ReceivingException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\Supplier;
use App\Services\Flowers\ReceivingService;
use App\Support\ReceivingQuantities;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public ?FlowerReceipt $receipt = null;

    public string $supplierId = '';

    public string $supplierInvoiceNumber = '';

    public string $receiptDate = '';

    public string $notes = '';

    /** @var list<array{flower_product_id: string, expected_quantity: string, received_quantity: string, damaged_quantity: string, purchase_price: string, notes: string}> */
    public array $items = [];

    public function mount(?FlowerReceipt $receipt = null): void
    {
        $this->authorizeFlowerReceipts();
        $this->receipt = $receipt;

        if (! $receipt instanceof FlowerReceipt) {
            $this->receiptDate = today()->toDateString();
            $this->addItem();

            return;
        }

        if ($receipt->status->value !== 'draft') {
            $this->redirectRoute('flowers.receipts.show', ['receipt' => $receipt], navigate: true);

            return;
        }

        $this->supplierId = (string) $receipt->supplier_id;
        $this->supplierInvoiceNumber = $receipt->supplier_invoice_number ?? '';
        $this->receiptDate = $receipt->receipt_date->toDateString();
        $this->notes = $receipt->notes ?? '';
        $this->items = $receipt->items()->orderBy('flower_product_id')->get()->map(fn ($item): array => [
            'flower_product_id' => (string) $item->flower_product_id,
            'expected_quantity' => $item->expected_quantity,
            'received_quantity' => $item->received_quantity,
            'damaged_quantity' => $item->damaged_quantity,
            'purchase_price' => $item->purchase_price ?? '',
            'notes' => $item->notes ?? '',
        ])->all();
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
            $this->items[$index]['purchase_price'] = $product->purchase_price ?? '';
        }
    }

    public function saveDraft(ReceivingService $receivingService): mixed
    {
        $receipt = $this->persistDraft($receivingService);

        if (! $receipt instanceof FlowerReceipt) {
            return null;
        }

        session()->flash('status', 'تم حفظ الإيصال كمسودة. لم يتغير المخزون.');

        return $this->redirectRoute('flowers.receipts.show', ['receipt' => $receipt], navigate: true);
    }

    public function confirm(ReceivingService $receivingService): mixed
    {
        $receipt = $this->persistDraft($receivingService);

        if (! $receipt instanceof FlowerReceipt) {
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

        session()->flash('status', 'تم اعتماد الإيصال وإضافة الكمية المقبولة إلى مخزون الورد.');

        return $this->redirectRoute('flowers.receipts.show', ['receipt' => $receipt], navigate: true);
    }

    /** @param array<string, mixed> $item
     * @return array{expected_quantity: string, received_quantity: string, damaged_quantity: string, shortage_quantity: string, surplus_quantity: string, accepted_quantity: string, valid: bool} */
    public function preview(array $item): array
    {
        try {
            return [...ReceivingQuantities::calculate($item['expected_quantity'] ?? '0', $item['received_quantity'] ?? '0', $item['damaged_quantity'] ?? '0'), 'valid' => true];
        } catch (InvalidStockQuantityException) {
            return ['expected_quantity' => '0.000', 'received_quantity' => '0.000', 'damaged_quantity' => '0.000', 'shortage_quantity' => '0.000', 'surplus_quantity' => '0.000', 'accepted_quantity' => '0.000', 'valid' => false];
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
            'items.*.flower_product_id' => ['required', 'integer', 'distinct', 'exists:flower_products,id'],
            'items.*.expected_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.received_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.damaged_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.purchase_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function render(): View
    {
        $selectedIds = collect($this->items)->pluck('flower_product_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $products = FlowerProduct::query()->where(function ($query) use ($selectedIds): void {
            $query->where('is_active', true);
            if ($selectedIds !== []) {
                $query->orWhereIn('id', $selectedIds);
            }
        })->orderBy('name')->get();
        $suppliers = Supplier::query()->where('is_active', true)->whereIn('module_scope', ['flower', 'both'])->orderBy('name')->get();

        return view('livewire.flowers.receipts.form', ['products' => $products, 'productLookup' => $products->keyBy('id'), 'suppliers' => $suppliers]);
    }

    private function persistDraft(ReceivingService $receivingService): ?FlowerReceipt
    {
        $this->authorizeFlowerReceipts();
        $validated = $this->validate();

        try {
            $attributes = ['supplier_id' => $validated['supplierId'], 'supplier_invoice_number' => $validated['supplierInvoiceNumber'], 'receipt_date' => $validated['receiptDate'], 'notes' => $validated['notes']];
            $this->receipt = $this->receipt instanceof FlowerReceipt
                ? $receivingService->updateDraft($this->receipt, $attributes, $validated['items'], auth()->user())
                : $receivingService->createDraft($attributes, $validated['items'], auth()->user());

            return $this->receipt;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
        } catch (InvalidStockQuantityException|ReceivingException $exception) {
            $this->addError('items', $exception->getMessage());
        }

        return null;
    }

    /** @return array{flower_product_id: string, expected_quantity: string, received_quantity: string, damaged_quantity: string, purchase_price: string, notes: string} */
    private function emptyItem(): array
    {
        return ['flower_product_id' => '', 'expected_quantity' => '0.000', 'received_quantity' => '0.000', 'damaged_quantity' => '0.000', 'purchase_price' => '', 'notes' => ''];
    }
}
