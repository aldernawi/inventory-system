<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_openings', function (Blueprint $table): void {
            $table->id();
            $table->string('stockable_type', 32);
            $table->unsignedBigInteger('stockable_id');
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->date('opening_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['stockable_type', 'stockable_id'], 'stock_openings_stockable_unique');
            $table->index('opening_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_openings ADD CONSTRAINT stock_openings_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_openings');
    }
};
