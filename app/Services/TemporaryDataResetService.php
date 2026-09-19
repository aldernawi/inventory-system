<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TemporaryDataResetService
{
    public function clearFlowerBusinessData(): void
    {
        DB::transaction(function (): void {
            DB::table('invoice_payments')->where('invoiceable_type', 'flower_invoice')->delete();

            $flowerMovementIds = DB::table('stock_movements')
                ->where('stockable_type', 'flower_product')
                ->pluck('id');

            // Reversal movements reference their original movement through a restrictive foreign key.
            DB::table('stock_movements')->whereIn('reverses_movement_id', $flowerMovementIds)->delete();
            DB::table('stock_movements')->whereIn('id', $flowerMovementIds)->delete();

            DB::table('stock_wastes')->where('stockable_type', 'flower_product')->delete();
            DB::table('stock_adjustments')->where('stockable_type', 'flower_product')->delete();
            DB::table('stock_openings')->where('stockable_type', 'flower_product')->delete();

            DB::table('flower_invoice_items')->delete();
            DB::table('flower_invoices')->delete();
            DB::table('flower_receipt_items')->delete();
            DB::table('flower_receipts')->delete();
            DB::table('flower_exits')->delete();
            DB::table('flower_products')->delete();
        });
    }

    public function clearBusinessData(): void
    {
        DB::transaction(function (): void {
            DB::table('invoice_payments')->delete();

            // Reversal movements reference their original movement through a restrictive foreign key.
            DB::table('stock_movements')->whereNotNull('reverses_movement_id')->delete();
            DB::table('stock_movements')->delete();

            DB::table('stock_wastes')->delete();
            DB::table('stock_adjustments')->delete();
            DB::table('stock_openings')->delete();

            DB::table('salami_invoice_items')->delete();
            DB::table('flower_invoice_items')->delete();
            DB::table('salami_invoices')->delete();
            DB::table('flower_invoices')->delete();

            DB::table('salami_receipt_items')->delete();
            DB::table('flower_receipt_items')->delete();
            DB::table('salami_receipts')->delete();
            DB::table('flower_receipts')->delete();
            DB::table('flower_exits')->delete();

            DB::table('salami_customers')->delete();
            DB::table('salami_delivery_agents')->delete();
            DB::table('salami_products')->delete();
            DB::table('flower_products')->delete();
            DB::table('suppliers')->delete();
        });
    }
}
