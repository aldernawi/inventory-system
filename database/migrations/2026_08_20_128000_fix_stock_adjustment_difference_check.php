<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE stock_adjustments DROP CHECK stock_adjustments_difference_check');
        DB::statement('ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_difference_check CHECK (difference_quantity = CAST(actual_quantity AS DECIMAL(15, 3)) - CAST(system_quantity AS DECIMAL(15, 3)))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE stock_adjustments DROP CHECK stock_adjustments_difference_check');
        DB::statement('ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_difference_check CHECK (difference_quantity = actual_quantity - system_quantity)');
    }
};
