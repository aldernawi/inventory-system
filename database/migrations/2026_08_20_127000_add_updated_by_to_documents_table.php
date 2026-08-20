<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['salami_receipts', 'flower_receipts', 'salami_invoices', 'flower_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['salami_receipts', 'flower_receipts', 'salami_invoices', 'flower_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
