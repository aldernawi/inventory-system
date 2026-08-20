<?php

namespace App\Services\Flowers;

use App\Enums\ReceiptStatus;
use App\Enums\SupplierScope;
use App\Exceptions\Flowers\ReceivingException;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\FlowerReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use App\Support\ReceivingQuantities;
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
    public function createDraft(array $attributes, array $items, User $createdBy): FlowerReceipt
    {
        return DB::transaction(function () use ($attributes, $items, $createdBy): FlowerReceipt {
            $supplier = $this->flowerSupplier($attributes['supplier_id']);
            $receipt = FlowerReceipt::query()->create([
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
    public function updateDraft(FlowerReceipt $receipt, array $attributes, array $items, User $updatedBy): FlowerReceipt
    {
        return DB::transaction(function () use ($receipt, $attributes, $items, $updatedBy): FlowerReceipt {
            $lockedReceipt = $this->lockDraft($receipt);
            $supplier = $this->flowerSupplier($attributes['supplier_id']);

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

    public function deleteDraft(FlowerReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $lockedReceipt = $this->lockDraft($receipt);
            $lockedReceipt->items()->delete();
            $lockedReceipt->delete();
        });
    }

    public function confirm(FlowerReceipt $receipt, User $confirmedBy): FlowerReceipt
    {
        return DB::transaction(function () use ($receipt, $confirmedBy): FlowerReceipt {
            $lockedReceipt = FlowerReceipt::query()
                ->with('supplier')
                ->lockForUpdate()
                ->find($receipt->getKey());

            if (! $lockedReceipt instanceof FlowerReceipt) {
                throw new ReceivingException('إيصال الورد لم يعد موجودًا.');
            }

            if ($lockedReceipt->status !== ReceiptStatus::Draft) {
                throw new ReceivingException('يمكن اعتماد مسودة الاستلام فقط.');
            }

            $this->assertFlowerSupplier($lockedReceipt->supplier);
            $items = FlowerReceiptItem::query()
                ->with('product')
                ->where('receipt_id', $lockedReceipt->getKey())
                ->orderBy('flower_product_id')
                ->get();

            if ($items->isEmpty()) {
                throw new ReceivingException('أضف صنف ورد واحدًا على الأقل قبل اعتماد الإيصال.');
            }

            if ($items->pluck('flower_product_id')->unique()->count() !== $items->count()) {
                throw new ReceivingException('لا يمكن تكرار نوع الورد في الإيصال نفسه.');
            }

            foreach ($items as $item) {
                $product = $item->product;

                if (! $product instanceof FlowerProduct || ! $product->is_active) {
                    throw new ReceivingException('كل بند يجب أن يشير إلى نوع ورد نشط.');
                }

                $calculation = ReceivingQuantities::calculate(
                    $item->expected_quantity,
                    $item->received_quantity,
                    $item->damaged_quantity,
                );
                $item->update([
                    'product_name' => $product->name,
                    'color' => $product->color,
                    'unit' => $product->unit,
                    ...$calculation,
                ]);

                $movement = $this->stockService->receipt(
                    $product,
                    $calculation['accepted_quantity'],
                    $confirmedBy,
                    $item,
                    "استلام ورد {$lockedReceipt->receipt_number}",
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

    private function lockDraft(FlowerReceipt $receipt): FlowerReceipt
    {
        $lockedReceipt = FlowerReceipt::query()->lockForUpdate()->find($receipt->getKey());

        if (! $lockedReceipt instanceof FlowerReceipt) {
            throw new ReceivingException('إيصال الورد لم يعد موجودًا.');
        }

        if ($lockedReceipt->status !== ReceiptStatus::Draft) {
            throw new ReceivingException('إيصال الورد المعتمد للعرض فقط.');
        }

        return $lockedReceipt;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceDraftItems(FlowerReceipt $receipt, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف نوع ورد واحدًا على الأقل إلى الإيصال.']);
        }

        $productIds = [];

        foreach ($items as $index => $item) {
            $productId = $item['flower_product_id'] ?? null;

            if (! is_numeric($productId)) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'اختر نوع الورد.']);
            }

            $productId = (int) $productId;

            if (in_array($productId, $productIds, true)) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'لا يمكن تكرار نوع الورد في الإيصال نفسه.']);
            }

            $productIds[] = $productId;
            $product = FlowerProduct::query()->find($productId);

            if (! $product instanceof FlowerProduct || ! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'نوع الورد غير متاح للاستلام.']);
            }

            $calculation = ReceivingQuantities::calculate(
                $item['expected_quantity'] ?? '0',
                $item['received_quantity'] ?? '0',
                $item['damaged_quantity'] ?? '0',
            );

            $receipt->items()->create([
                'flower_product_id' => $product->getKey(),
                'product_name' => $product->name,
                'color' => $product->color,
                'unit' => $product->unit,
                ...$calculation,
                'purchase_price' => $this->nullableDecimal($item['purchase_price'] ?? $product->purchase_price),
                'notes' => $this->nullableText($item['notes'] ?? null),
            ]);
        }
    }

    private function flowerSupplier(int|string $supplierId): Supplier
    {
        $supplier = Supplier::query()->find($supplierId);
        $this->assertFlowerSupplier($supplier);

        return $supplier;
    }

    private function assertFlowerSupplier(?Supplier $supplier): void
    {
        if (! $supplier instanceof Supplier || ! $supplier->is_active || ! in_array($supplier->module_scope, [SupplierScope::Flower, SupplierScope::Both], true)) {
            throw ValidationException::withMessages(['supplier_id' => 'اختر موردًا نشطًا مخصصًا للورد أو لكلا النظامين.']);
        }
    }

    private function nextReceiptNumber(): string
    {
        return 'FL-R-'.Str::upper((string) Str::ulid());
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = Quantity::from((string) $value);

        if ($value->isNegative()) {
            throw ValidationException::withMessages(['purchase_price' => 'سعر الشراء يجب أن يكون صفرًا أو أكبر.']);
        }

        return $value->toString();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
