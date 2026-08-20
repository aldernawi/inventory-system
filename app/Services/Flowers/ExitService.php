<?php

namespace App\Services\Flowers;

use App\Enums\FlowerExitType;
use App\Enums\InventoryRecordStatus;
use App\Exceptions\Flowers\StockOperationException;
use App\Exceptions\Inventory\StockMutationException;
use App\Models\FlowerExit;
use App\Models\FlowerProduct;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExitService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{flower_product_id: int|string, quantity: string, exit_date: string, exit_type: string, recipient_name?: string|null, notes?: string|null}  $attributes
     */
    public function record(array $attributes, User $createdBy): FlowerExit
    {
        return DB::transaction(function () use ($attributes, $createdBy): FlowerExit {
            $product = FlowerProduct::query()->find($attributes['flower_product_id']);

            if (! $product instanceof FlowerProduct || ! $product->is_active) {
                throw ValidationException::withMessages(['flower_product_id' => 'اختر نوع ورد نشطًا للخروج اليدوي.']);
            }

            $quantity = Quantity::from($attributes['quantity']);

            if (! $quantity->isPositive()) {
                throw ValidationException::withMessages(['quantity' => 'الكمية الخارجة يجب أن تكون أكبر من صفر.']);
            }

            try {
                $exitType = FlowerExitType::from($attributes['exit_type']);
            } catch (\ValueError) {
                throw ValidationException::withMessages(['exit_type' => 'اختر نوع خروج صالحًا.']);
            }

            $exit = FlowerExit::query()->create([
                'flower_product_id' => $product->getKey(),
                'quantity' => $quantity->toString(),
                'exit_date' => $attributes['exit_date'],
                'exit_type' => $exitType,
                'recipient_name' => $this->nullableText($attributes['recipient_name'] ?? null),
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'status' => InventoryRecordStatus::Confirmed,
                'created_by' => $createdBy->getKey(),
                'confirmed_by' => $createdBy->getKey(),
                'confirmed_at' => now(),
            ]);

            try {
                $this->stockService->manualExit($product, $quantity, $createdBy, $exit, "خروج ورد يدوي: {$exitType->value}");
            } catch (StockMutationException $exception) {
                throw new StockOperationException('تعذر تسجيل الخروج اليدوي بسبب الرصيد المتاح.', previous: $exception);
            }

            return $exit->fresh(['product', 'stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
