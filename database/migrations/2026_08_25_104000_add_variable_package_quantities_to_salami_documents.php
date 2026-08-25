<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salami_invoice_items', function (Blueprint $table): void {
            $table->decimal('conversion_factor', 15, 3)->unsigned()->default(1)->after('unit');
            $table->decimal('stock_quantity', 15, 3)->unsigned()->default(0)->after('quantity');
        });

        Schema::table('salami_receipt_items', function (Blueprint $table): void {
            $table->decimal('conversion_factor', 15, 3)->unsigned()->default(1)->after('unit');
            $table->decimal('accepted_stock_quantity', 15, 3)->unsigned()->default(0)->after('accepted_quantity');
        });

        DB::table('salami_invoice_items')->update(['stock_quantity' => DB::raw('quantity')]);
        DB::table('salami_receipt_items')->update(['accepted_stock_quantity' => DB::raw('accepted_quantity')]);
    }

    public function down(): void
    {
        Schema::table('salami_receipt_items', function (Blueprint $table): void {
            $table->dropColumn(['conversion_factor', 'accepted_stock_quantity']);
        });

        Schema::table('salami_invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['conversion_factor', 'stock_quantity']);
        });
    }
};
