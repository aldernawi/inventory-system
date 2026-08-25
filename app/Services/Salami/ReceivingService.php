<?php

namespace App\Services\Salami;

use App\Enums\ReceiptStatus;
use App\Enums\SalamiItemUnit;
use App\Enums\SupplierScope;
use App\Exceptions\Salami\ReceivingException;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\SalamiReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use App\Support\ReceivingQuantities;
use App\Support\SalamiUnitConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivingService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{supplier_id: int|string, supplier_invoice_number?: string|null, receipt_date: string, notes?: string|null}  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function createDraft(array $attributes, array $items, User $createdBy): SalamiReceipt
    {
        return DB::transaction(function () use ($attributes, $items, $createdBy): SalamiReceipt {
            $supplier = $this->salamiSupplier($attributes['supplier_id']);
            $receipt = SalamiReceipt::query()->create([
                'receipt_number' => $this->nextReceiptNumber(),
                'supplier_id' => $supplier->getKey(),
                'supplier_invoice_number' => $this->nullableText($attributes['supplier_invoice_number'] ?? null),
                'receipt_date' => $attributes['receipt_date'],
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'status' => ReceiptStatus::Draft,
                'created_by' => $createdBy->getKey(),
            ]);

            $this->replaceDraftItems($receipt, $items);

            return $receipt->fresh(['supplier', 'items.product']);
        });
    }

    /**
     * @param  array{supplier_id: int|string, supplier_invoice_number?: string|null, receipt_date: string, notes?: string|null}  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function updateDraft(SalamiReceipt $receipt, array $attributes, array $items, User $updatedBy): SalamiReceipt
    {
        return DB::transaction(function () use ($receipt, $attributes, $items, $updatedBy): SalamiReceipt {
            $lockedReceipt = $this->lockDraft($receipt);
            $supplier = $this->salamiSupplier($attributes['supplier_id']);

            $lockedReceipt->update([
                'supplier_id' => $supplier->getKey(),
                'supplier_invoice_number' => $this->nullableText($attributes['supplier_invoice_number'] ?? null),
                'receipt_date' => $attributes['receipt_date'],
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'updated_by' => $updatedBy->getKey(),
            ]);

            $lockedReceipt->items()->delete();
            $this->replaceDraftItems($lockedReceipt, $items);

            return $lockedReceipt->fresh(['supplier', 'items.product']);
        });
    }

    public function deleteDraft(SalamiReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $lockedReceipt = $this->lockDraft($receipt);
            $lockedReceipt->items()->delete();
            $lockedReceipt->delete();
        });
    }

    public function confirm(SalamiReceipt $receipt, User $confirmedBy): SalamiReceipt
    {
        return DB::transaction(function () use ($receipt, $confirmedBy): SalamiReceipt {
            $lockedReceipt = SalamiReceipt::query()
                ->with('supplier')
                ->lockForUpdate()
                ->find($receipt->getKey());

            if (! $lockedReceipt instanceof SalamiReceipt) {
                throw new ReceivingException('The receipt no longer exists.');
            }

            if ($lockedReceipt->status !== ReceiptStatus::Draft) {
                throw new ReceivingException('Only draft receipts can be confirmed.');
            }

            $this->assertSalamiSupplier($lockedReceipt->supplier);

            $items = SalamiReceiptItem::query()
                ->with('product')
                ->where('receipt_id', $lockedReceipt->getKey())
                ->orderBy('product_id')
                ->get();

            if ($items->isEmpty()) {
                throw new ReceivingException('A receipt must include at least one item before confirmation.');
            }

            if ($items->pluck('product_id')->unique()->count() !== $items->count()) {
                throw new ReceivingException('A receipt cannot contain the same product more than once.');
            }

            foreach ($items as $item) {
                $product = $item->product;

                if (! $product instanceof SalamiProduct || ! $product->is_active) {
                    throw new ReceivingException('Every receipt item must reference an active Salami product.');
                }

                $calculation = ReceivingQuantities::calculate(
                    $item->expected_quantity,
                    $item->received_quantity,
                    $item->damaged_quantity,
                );

                $acceptedStockQuantity = Quantity::from($calculation['accepted_quantity'])
                    ->multipliedBy(Quantity::from($item->conversion_factor));

                $item->update([
                    ...$calculation,
                    'accepted_stock_quantity' => $acceptedStockQuantity->toString(),
                ]);

                $movement = $this->stockService->receipt(
                    $product,
                    $acceptedStockQuantity,
                    $confirmedBy,
                    $item,
                    "استلام {$lockedReceipt->receipt_number}",
                );

                $item->update([
                    'balance_before' => $movement->balance_before,
                    'balance_after' => $movement->balance_after,
                ]);
            }

            $lockedReceipt->update([
                'status' => ReceiptStatus::Confirmed,
                'confirmed_by' => $confirmedBy->getKey(),
                'confirmed_at' => now(),
            ]);

            return $lockedReceipt->fresh(['supplier', 'items.product', 'items.stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    private function lockDraft(SalamiReceipt $receipt): SalamiReceipt
    {
        $lockedReceipt = SalamiReceipt::query()->lockForUpdate()->find($receipt->getKey());

        if (! $lockedReceipt instanceof SalamiReceipt) {
            throw new ReceivingException('The receipt no longer exists.');
        }

        if ($lockedReceipt->status !== ReceiptStatus::Draft) {
            throw new ReceivingException('Confirmed receipts are read-only.');
        }

        return $lockedReceipt;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceDraftItems(SalamiReceipt $receipt, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف صنفًا واحدًا على الأقل إلى الإيصال.']);
        }

        $productIds = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;

            if (! is_numeric($productId)) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'اختر صنف السلامي.']);
            }

            $productId = (int) $productId;

            if (in_array($productId, $productIds, true)) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'لا يمكن تكرار الصنف في الإيصال نفسه.']);
            }

            $productIds[] = $productId;

            $product = SalamiProduct::query()->find($productId);

            if (! $product instanceof SalamiProduct || ! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'الصنف غير متاح للاستلام.']);
            }

            $calculation = ReceivingQuantities::calculate(
                $item['expected_quantity'] ?? '0',
                $item['received_quantity'] ?? '0',
                $item['damaged_quantity'] ?? '0',
            );
            $conversion = $this->conversionForItem($item, $index);

            $receipt->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'unit' => $conversion['unit']->label(),
                'conversion_factor' => $conversion['factor']->toString(),
                ...$calculation,
                'accepted_stock_quantity' => Quantity::from($calculation['accepted_quantity'])
                    ->multipliedBy($conversion['factor'])
                    ->toString(),
                'purchase_price' => $this->nullableDecimal($item['purchase_price'] ?? $product->purchase_price),
                'notes' => $this->nullableText($item['notes'] ?? null),
            ]);
        }
    }

    private function salamiSupplier(int|string $supplierId): Supplier
    {
        $supplier = Supplier::query()->find($supplierId);
        $this->assertSalamiSupplier($supplier);

        return $supplier;
    }

    private function assertSalamiSupplier(?Supplier $supplier): void
    {
        if (! $supplier instanceof Supplier || ! $supplier->is_active || ! in_array($supplier->module_scope, [SupplierScope::Salami, SupplierScope::Both], true)) {
            throw ValidationException::withMessages(['supplier_id' => 'اختر موردًا نشطًا مخصصًا للسلامي أو لكلا النظامين.']);
        }
    }

    private function nextReceiptNumber(): string
    {
        return 'SAL-R-'.Str::upper((string) Str::ulid());
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $quantity = Quantity::from((string) $value);

        if ($quantity->isNegative()) {
            throw ValidationException::withMessages(['purchase_price' => 'سعر الشراء يجب أن يكون صفرًا أو أكبر.']);
        }

        return $quantity->toString();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{unit: SalamiItemUnit, factor: Quantity, stock_quantity: Quantity}
     */
    private function conversionForItem(array $item, int $index): array
    {
        try {
            return SalamiUnitConversion::fromInput(
                (string) ($item['unit_type'] ?? 'piece'),
                (string) ($item['received_quantity'] ?? '0'),
                $item['pieces_per_box'] ?? null,
                false,
            );
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors["items.{$index}.{$field}"] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
