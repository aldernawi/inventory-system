<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('flower_invoices')->restrictOnDelete();
            $table->foreignId('flower_product_id')->constrained('flower_products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('unit', 50);
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->decimal('unit_price', 12, 3)->unsigned();
            $table->decimal('line_total', 14, 3)->unsigned();
            $table->timestamps();

            $table->unique(['invoice_id', 'flower_product_id'], 'flower_invoice_product_unique');
            $table->index('flower_product_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE flower_invoice_items ADD CONSTRAINT flower_invoice_items_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_invoice_items');
    }
};
