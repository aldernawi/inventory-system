<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('invoiceable_type', 32);
            $table->unsignedBigInteger('invoiceable_id');
            $table->decimal('amount', 14, 3)->unsigned();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['invoiceable_type', 'invoiceable_id'], 'invoice_payments_invoiceable_index');
            $table->index('paid_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoice_payments ADD CONSTRAINT invoice_payments_amount_check CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
