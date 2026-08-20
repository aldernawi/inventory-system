<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flower_invoice_items', function (Blueprint $table): void {
            $table->string('color', 100)->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('flower_invoice_items', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
