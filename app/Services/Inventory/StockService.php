<?php

namespace App\Services\Inventory;

use App\Contracts\Stockable;
use App\Enums\MovementType;
use App\Exceptions\Inventory\DuplicateStockMutationException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\InvalidStockableException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Inventory\StockMutationException;
use App\Models\StockMovement;
use App\Models\StockOpening;
use App\Models\User;
use App\Support\Quantity;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private readonly StockMovementService $movementService) {}

    public function opening(
        Stockable $stockable,
        Quantity|int|string $quantity,
        User $createdBy,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        $quantity = $this->positiveQuantity($quantity);

        return $this->mutate(
            stockable: $stockable,
            movementType: MovementType::Opening,
            signedQuantity: $quantity,
            createdBy: $createdBy,
            notes: $notes,
            occurredAt: $occurredAt,
            referenceFactory: function (Model $lockedStockable, Quantity $balanceBefore) use ($quantity, $createdBy, $notes, $occurredAt): StockOpening {
                if (! $balanceBefore->isZero()) {
                    throw new StockMutationException('Opening stock can only be recorded while the current balance is zero.');
                }

                $stockableAttributes = $this->stockableAttributes($lockedStockable);

                if (StockMovement::query()->where($stockableAttributes)->exists()) {
                    throw new StockMutationException('Opening stock cannot be recorded after stock history exists for this product.');
                }

                if (StockOpening::query()->where($stockableAttributes)->exists()) {
                    throw new DuplicateStockMutationException('Opening stock has already been recorded for this product.');
                }

                return StockOpening::query()->create([
                    ...$stockableAttributes,
                    'quantity' => $quantity->toString(),
                    'opening_date' => ($occurredAt ?? now())->toDateString(),
                    'notes' => $notes,
                    'created_by' => $createdBy->getKey(),
                ]);
            },
        );
    }

    public function receipt(
        Stockable $stockable,
        Quantity|int|string $quantity,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        return $this->mutate($stockable, MovementType::Receipt, $this->positiveQuantity($quantity), $createdBy, $reference, $notes, $occurredAt);
    }

    public function sale(
        Stockable $stockable,
        Quantity|int|string $quantity,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        return $this->mutate($stockable, MovementType::Sale, $this->positiveQuantity($quantity)->negated(), $createdBy, $reference, $notes, $occurredAt);
    }

    public function manualExit(
        Stockable $stockable,
        Quantity|int|string $quantity,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        return $this->mutate($stockable, MovementType::ManualExit, $this->positiveQuantity($quantity)->negated(), $createdBy, $reference, $notes, $occurredAt);
    }

    public function waste(
        Stockable $stockable,
        Quantity|int|string $quantity,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        return $this->mutate($stockable, MovementType::Waste, $this->positiveQuantity($quantity)->negated(), $createdBy, $reference, $notes, $occurredAt);
    }

    public function adjust(
        Stockable $stockable,
        Quantity|int|string $difference,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        $difference = Quantity::from($difference);

        if ($difference->isZero()) {
            throw new InvalidStockQuantityException('Stock adjustments must have a non-zero difference.');
        }

        return $this->mutate(
            $stockable,
            $difference->isPositive() ? MovementType::AdjustmentIn : MovementType::AdjustmentOut,
            $difference,
            $createdBy,
            $reference,
            $notes,
            $occurredAt,
        );
    }

    public function reverse(
        StockMovement $movement,
        User $createdBy,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): StockMovement {
        if ($movement->getKey() === null) {
            throw new InvalidStockableException('The movement to reverse must be persisted.');
        }

        $original = StockMovement::query()->with('stockable')->find($movement->getKey());

        if (! $original instanceof StockMovement || ! $original->stockable instanceof Stockable) {
            throw new InvalidStockableException('The original movement does not have a valid stockable product.');
        }

        if ($original->movement_type === MovementType::Reversal) {
            throw new StockMutationException('A reversal movement cannot be reversed again.');
        }

        return $this->mutate(
            stockable: $original->stockable,
            movementType: MovementType::Reversal,
            signedQuantity: Quantity::from($original->quantity)->negated(),
            createdBy: $createdBy,
            notes: $notes,
            occurredAt: $occurredAt,
            reversesMovement: $original,
            beforeMutation: function (Model $lockedStockable) use ($original): void {
                $lockedOriginal = StockMovement::query()->lockForUpdate()->find($original->getKey());

                if (! $lockedOriginal instanceof StockMovement) {
                    throw new InvalidStockableException('The original movement no longer exists.');
                }

                $stockableAttributes = $this->stockableAttributes($lockedStockable);

                if ($lockedOriginal->stockable_type !== $stockableAttributes['stockable_type'] || $lockedOriginal->stockable_id !== $stockableAttributes['stockable_id']) {
                    throw new InvalidStockableException('The original movement belongs to a different product.');
                }

                if (StockMovement::query()->where('reverses_movement_id', $lockedOriginal->getKey())->exists()) {
                    throw new DuplicateStockMutationException('This movement has already been reversed.');
                }
            },
        );
    }

    /**
     * The only application-level write path for a product current_quantity.
     *
     * @param  (Closure(Model, Quantity): void)|null  $beforeMutation
     * @param  (Closure(Model, Quantity): Model)|null  $referenceFactory
     */
    private function mutate(
        Stockable $stockable,
        MovementType $movementType,
        Quantity $signedQuantity,
        User $createdBy,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
        ?StockMovement $reversesMovement = null,
        ?Closure $beforeMutation = null,
        ?Closure $referenceFactory = null,
    ): StockMovement {
        $stockable = $this->assertStockable($stockable);
        $this->assertPersistedActor($createdBy);

        return DB::transaction(function () use ($stockable, $movementType, $signedQuantity, $createdBy, $reference, $notes, $occurredAt, $reversesMovement, $beforeMutation, $referenceFactory): StockMovement {
            $lockedStockable = $this->lockStockable($stockable);
            // Eloquent's decimal cast returns a fixed-scale string. Avoid the raw value here
            // because SQLite may hydrate DECIMAL columns as PHP floats.
            $balanceBefore = Quantity::from($lockedStockable->getAttribute('current_quantity'));

            if ($beforeMutation !== null) {
                $beforeMutation($lockedStockable, $balanceBefore);
            }

            if ($signedQuantity->isNegative() && $signedQuantity->absolute()->isGreaterThan($balanceBefore)) {
                throw new InsufficientStockException($balanceBefore->toString(), $signedQuantity->absolute()->toString());
            }

            $resolvedReference = $referenceFactory !== null
                ? $referenceFactory($lockedStockable, $balanceBefore)
                : $reference;
            $referenceAttributes = $this->referenceAttributes($resolvedReference);

            if ($resolvedReference !== null && StockMovement::query()
                ->where('reference_type', $referenceAttributes['reference_type'])
                ->where('reference_id', $referenceAttributes['reference_id'])
                ->where('movement_type', $movementType)
                ->exists()) {
                throw new DuplicateStockMutationException('A movement for this source reference and type already exists.');
            }

            $balanceAfter = $balanceBefore->plus($signedQuantity);

            if ($balanceAfter->isNegative()) {
                throw new InsufficientStockException($balanceBefore->toString(), $signedQuantity->absolute()->toString());
            }

            // current_quantity is deliberately not fillable on product models.
            $lockedStockable->forceFill(['current_quantity' => $balanceAfter->toString()])->save();

            return $this->movementService->record([
                ...$this->stockableAttributes($lockedStockable),
                'movement_type' => $movementType,
                'quantity' => $signedQuantity->toString(),
                'balance_before' => $balanceBefore->toString(),
                'balance_after' => $balanceAfter->toString(),
                ...$referenceAttributes,
                'reverses_movement_id' => $reversesMovement?->getKey(),
                'notes' => $notes,
                'created_by' => $createdBy->getKey(),
                'occurred_at' => $occurredAt ?? now(),
            ]);
        }, attempts: 3);
    }

    private function positiveQuantity(Quantity|int|string $quantity): Quantity
    {
        $quantity = Quantity::from($quantity);

        if (! $quantity->isPositive()) {
            throw new InvalidStockQuantityException('Stock quantities must be greater than zero.');
        }

        return $quantity;
    }

    private function assertStockable(Stockable $stockable): Model
    {
        if (! $stockable instanceof Model || $stockable->getKey() === null) {
            throw new InvalidStockableException('Stock mutations require a persisted stockable Eloquent model.');
        }

        $this->stockableAttributes($stockable);

        return $stockable;
    }

    private function assertPersistedActor(User $createdBy): void
    {
        if ($createdBy->getKey() === null) {
            throw new StockMutationException('Stock mutations require a persisted actor.');
        }
    }

    private function lockStockable(Model $stockable): Model
    {
        $lockedStockable = $stockable->newQuery()->whereKey($stockable->getKey())->lockForUpdate()->first();

        if (! $lockedStockable instanceof Model) {
            throw new InvalidStockableException('The stockable product no longer exists.');
        }

        return $lockedStockable;
    }

    /**
     * @return array{stockable_type: string, stockable_id: int}
     */
    private function stockableAttributes(Model $stockable): array
    {
        $stockableType = $stockable->getMorphClass();

        if (Relation::getMorphedModel($stockableType) !== $stockable::class) {
            throw new InvalidStockableException('The stockable product must use a configured morph alias.');
        }

        return [
            'stockable_type' => $stockableType,
            'stockable_id' => (int) $stockable->getKey(),
        ];
    }

    /**
     * @return array{reference_type: string|null, reference_id: int|null}
     */
    private function referenceAttributes(?Model $reference): array
    {
        if ($reference === null) {
            return ['reference_type' => null, 'reference_id' => null];
        }

        if ($reference->getKey() === null) {
            throw new InvalidStockableException('Movement references must be persisted models.');
        }

        $referenceType = $reference->getMorphClass();

        if (Relation::getMorphedModel($referenceType) !== $reference::class) {
            throw new InvalidStockableException('Movement references must use a configured morph alias.');
        }

        return [
            'reference_type' => $referenceType,
            'reference_id' => (int) $reference->getKey(),
        ];
    }
}
