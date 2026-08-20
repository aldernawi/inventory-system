<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salami_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('salami_invoices')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('salami_products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('unit', 50);
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->decimal('unit_price', 12, 3)->unsigned();
            $table->decimal('line_total', 14, 3)->unsigned();
            $table->timestamps();

            $table->unique(['invoice_id', 'product_id']);
            $table->index('product_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salami_invoice_items ADD CONSTRAINT salami_invoice_items_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salami_invoice_items');
    }
};
