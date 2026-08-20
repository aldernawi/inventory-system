<?php

namespace App\Services\Inventory;

use App\Contracts\Stockable;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Support\Quantity;
use LogicException;

/**
 * Read-only stock-ledger consistency verification.
 *
 * This intentionally never repairs balances. A mismatch is a data-integrity
 * defect that must be investigated rather than silently overwritten.
 */
class StockLedgerReconciler
{
    /**
     * @return array{current_quantity: string, ledger_quantity: string, matches: bool}
     */
    public function check(Stockable $stockable): array
    {
        $ledgerQuantity = StockMovement::query()
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->orderBy('id')
            ->cursor()
            ->reduce(
                fn (Quantity $total, StockMovement $movement): Quantity => $total->plus(Quantity::from($movement->quantity)),
                Quantity::zero(),
            );
        $currentQuantity = Quantity::from($stockable->current_quantity);

        return [
            'current_quantity' => $currentQuantity->toString(),
            'ledger_quantity' => $ledgerQuantity->toString(),
            'matches' => $currentQuantity->isEqualTo($ledgerQuantity),
        ];
    }

    /**
     * @return list<array{module: 'salami'|'flowers', product_id: int, current_quantity: string, ledger_quantity: string, matches: bool}>
     */
    public function checkAll(): array
    {
        return [
            ...$this->checkProducts('salami', SalamiProduct::query()->orderBy('id')->cursor()),
            ...$this->checkProducts('flowers', FlowerProduct::query()->orderBy('id')->cursor()),
        ];
    }

    public function assertMatches(Stockable $stockable): void
    {
        $result = $this->check($stockable);

        if (! $result['matches']) {
            throw new LogicException('Stock ledger does not match the product current quantity.');
        }
    }

    /**
     * @param  iterable<Stockable>  $products
     * @return list<array{module: 'salami'|'flowers', product_id: int, current_quantity: string, ledger_quantity: string, matches: bool}>
     */
    private function checkProducts(string $module, iterable $products): array
    {
        $results = [];

        foreach ($products as $product) {
            $results[] = ['module' => $module, 'product_id' => $product->getKey(), ...$this->check($product)];
        }

        return $results;
    }
}
