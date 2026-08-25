<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flower_products', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('name');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('flower_products', function (Blueprint $table): void {
            $table->dropIndex(['company_name']);
            $table->dropColumn('company_name');
        });
    }
};
