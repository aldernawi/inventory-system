<?php

namespace App\Services\Salami;

use App\Enums\InventoryRecordStatus;
use App\Exceptions\Inventory\StockMutationException;
use App\Exceptions\Salami\StockOperationException;
use App\Models\SalamiProduct;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustmentService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{product_id: int|string, actual_quantity: string, adjustment_date: string, reason: string, notes?: string|null}  $attributes
     */
    public function record(array $attributes, User $createdBy): StockAdjustment
    {
        return DB::transaction(function () use ($attributes, $createdBy): StockAdjustment {
            $product = SalamiProduct::query()
                ->whereKey($attributes['product_id'])
                ->lockForUpdate()
                ->first();

            if (! $product instanceof SalamiProduct || ! $product->is_active) {
                throw ValidationException::withMessages(['product_id' => 'اختر صنف سلامي نشطًا لتسوية المخزون.']);
            }

            $actualQuantity = Quantity::from($attributes['actual_quantity']);

            if ($actualQuantity->isNegative()) {
                throw ValidationException::withMessages(['actual_quantity' => 'الكمية الفعلية يجب أن تكون صفرًا أو أكبر.']);
            }

            $systemQuantity = Quantity::from($product->current_quantity);
            $difference = $actualQuantity->minus($systemQuantity);

            if ($difference->isZero()) {
                throw new StockOperationException('لا توجد فروقات بين رصيد النظام والكمية الفعلية.');
            }

            $reason = trim($attributes['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب التسوية مطلوب.']);
            }

            $adjustment = StockAdjustment::query()->create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->getKey(),
                'system_quantity' => $systemQuantity->toString(),
                'actual_quantity' => $actualQuantity->toString(),
                'difference_quantity' => $difference->toString(),
                'adjustment_date' => $attributes['adjustment_date'],
                'reason' => $reason,
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'status' => InventoryRecordStatus::Confirmed,
                'created_by' => $createdBy->getKey(),
                'confirmed_by' => $createdBy->getKey(),
                'confirmed_at' => now(),
            ]);

            try {
                $this->stockService->adjust(
                    $product,
                    $difference,
                    $createdBy,
                    $adjustment,
                    "تسوية مخزون: {$reason}",
                );
            } catch (StockMutationException $exception) {
                throw new StockOperationException('تعذر تنفيذ تسوية المخزون.', previous: $exception);
            }

            return $adjustment->fresh(['stockable', 'stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
