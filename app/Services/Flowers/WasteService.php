<?php

namespace App\Services\Flowers;

use App\Enums\InventoryRecordStatus;
use App\Exceptions\Flowers\StockOperationException;
use App\Exceptions\Inventory\StockMutationException;
use App\Models\FlowerProduct;
use App\Models\StockWaste;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WasteService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{flower_product_id: int|string, quantity: string, reason: string, waste_date: string, notes?: string|null}  $attributes
     */
    public function record(array $attributes, User $createdBy): StockWaste
    {
        return DB::transaction(function () use ($attributes, $createdBy): StockWaste {
            $product = FlowerProduct::query()->find($attributes['flower_product_id']);

            if (! $product instanceof FlowerProduct || ! $product->is_active) {
                throw ValidationException::withMessages(['flower_product_id' => 'اختر نوع ورد نشطًا لتسجيل التالف.']);
            }

            $quantity = Quantity::from($attributes['quantity']);

            if (! $quantity->isPositive()) {
                throw ValidationException::withMessages(['quantity' => 'كمية التالف يجب أن تكون أكبر من صفر.']);
            }

            $reason = trim($attributes['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'سبب التلف مطلوب.']);
            }

            $waste = StockWaste::query()->create([
                'stockable_type' => $product->getMorphClass(),
                'stockable_id' => $product->getKey(),
                'quantity' => $quantity->toString(),
                'reason' => $reason,
                'waste_date' => $attributes['waste_date'],
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'status' => InventoryRecordStatus::Confirmed,
                'created_by' => $createdBy->getKey(),
                'confirmed_by' => $createdBy->getKey(),
                'confirmed_at' => now(),
            ]);

            try {
                $this->stockService->waste($product, $quantity, $createdBy, $waste, "تالف ورد: {$reason}");
            } catch (StockMutationException $exception) {
                throw new StockOperationException('تعذر تسجيل التالف بسبب الرصيد المتاح.', previous: $exception);
            }

            return $waste->fresh(['stockable', 'stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
