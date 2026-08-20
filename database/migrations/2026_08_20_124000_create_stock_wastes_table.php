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
        Schema::create('stock_wastes', function (Blueprint $table): void {
            $table->id();
            $table->string('stockable_type', 32);
            $table->unsignedBigInteger('stockable_id');
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->string('reason');
            $table->date('waste_date');
            $table->text('notes')->nullable();
            $table->enum('status', InventoryRecordStatus::values())->default(InventoryRecordStatus::Confirmed->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['stockable_type', 'stockable_id'], 'stock_wastes_stockable_index');
            $table->index(['status', 'waste_date']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_wastes ADD CONSTRAINT stock_wastes_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_wastes');
    }
};
