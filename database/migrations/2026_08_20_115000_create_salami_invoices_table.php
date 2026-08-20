<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salami_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('salami_customers')->restrictOnDelete();
            $table->date('invoice_date');
            $table->enum('payment_type', PaymentType::values());
            $table->enum('payment_status', PaymentStatus::values())->default(PaymentStatus::Unpaid->value);
            $table->decimal('subtotal_amount', 14, 3)->unsigned()->default(0);
            $table->decimal('discount_amount', 14, 3)->unsigned()->default(0);
            $table->decimal('total_amount', 14, 3)->unsigned()->default(0);
            $table->decimal('paid_amount', 14, 3)->unsigned()->default(0);
            $table->decimal('remaining_amount', 14, 3)->unsigned()->default(0);
            $table->enum('status', InvoiceStatus::values())->default(InvoiceStatus::Draft->value);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'invoice_date']);
            $table->index(['status', 'invoice_date']);
            $table->index('payment_status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salami_invoices ADD CONSTRAINT salami_invoices_amounts_check CHECK (discount_amount <= subtotal_amount AND total_amount = subtotal_amount - discount_amount AND paid_amount <= total_amount AND remaining_amount = total_amount - paid_amount)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salami_invoices');
    }
};
