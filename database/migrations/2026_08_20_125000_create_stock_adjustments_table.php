<?php

use App\Enums\InventoryRecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('stockable_type', 32);
            $table->unsignedBigInteger('stockable_id');
            $table->decimal('system_quantity', 15, 3)->unsigned();
            $table->decimal('actual_quantity', 15, 3)->unsigned();
            $table->decimal('difference_quantity', 15, 3);
            $table->date('adjustment_date');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->enum('status', InventoryRecordStatus::values())->default(InventoryRecordStatus::Confirmed->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['stockable_type', 'stockable_id'], 'stock_adjustments_stockable_index');
            $table->index(['status', 'adjustment_date']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_difference_check CHECK (difference_quantity = actual_quantity - system_quantity)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
