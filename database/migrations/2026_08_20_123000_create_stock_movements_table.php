<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('stockable_type', 32);
            $table->unsignedBigInteger('stockable_id');
            $table->enum('movement_type', MovementType::values());
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance_before', 15, 3)->unsigned();
            $table->decimal('balance_after', 15, 3)->unsigned();
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('reverses_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['stockable_type', 'stockable_id'], 'stock_movements_stockable_index');
            $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_index');
            $table->index(['movement_type', 'occurred_at']);
            $table->unique(['reference_type', 'reference_id', 'movement_type'], 'stock_movements_reference_type_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_balances_check CHECK (quantity <> 0 AND balance_before >= 0 AND balance_after >= 0 AND balance_after = balance_before + quantity)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
