<?php

use App\Enums\ReceiptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salami_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_invoice_number')->nullable();
            $table->date('receipt_date');
            $table->enum('status', ReceiptStatus::values())->default(ReceiptStatus::Draft->value);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'receipt_date']);
            $table->index(['status', 'receipt_date']);
            $table->index('supplier_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salami_receipts');
    }
};
