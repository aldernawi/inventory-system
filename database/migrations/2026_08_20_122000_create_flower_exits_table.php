<?php

use App\Enums\FlowerExitType;
use App\Enums\InventoryRecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_exits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flower_product_id')->constrained('flower_products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->date('exit_date');
            $table->enum('exit_type', FlowerExitType::values());
            $table->string('recipient_name')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', InventoryRecordStatus::values())->default(InventoryRecordStatus::Confirmed->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['flower_product_id', 'exit_date']);
            $table->index(['status', 'exit_date']);
            $table->index('exit_type');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE flower_exits ADD CONSTRAINT flower_exits_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_exits');
    }
};
