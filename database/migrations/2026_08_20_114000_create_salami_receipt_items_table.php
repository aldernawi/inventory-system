<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salami_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('salami_receipts')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('salami_products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('unit', 50);
            $table->decimal('expected_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('received_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('damaged_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('shortage_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('surplus_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('accepted_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('purchase_price', 12, 3)->unsigned()->nullable();
            $table->decimal('balance_before', 15, 3)->unsigned()->nullable();
            $table->decimal('balance_after', 15, 3)->unsigned()->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['receipt_id', 'product_id']);
            $table->index('product_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salami_receipt_items ADD CONSTRAINT salami_receipt_items_quantities_check CHECK (damaged_quantity <= received_quantity AND shortage_quantity = CASE WHEN expected_quantity > received_quantity THEN expected_quantity - received_quantity ELSE 0 END AND surplus_quantity = CASE WHEN received_quantity > expected_quantity THEN received_quantity - expected_quantity ELSE 0 END AND accepted_quantity = received_quantity - damaged_quantity)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salami_receipt_items');
    }
};
