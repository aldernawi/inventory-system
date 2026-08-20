<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('color')->nullable();
            $table->string('grade')->nullable();
            $table->string('unit', 50);
            $table->decimal('current_quantity', 15, 3)->unsigned()->default(0);
            $table->decimal('purchase_price', 12, 3)->unsigned()->nullable();
            $table->decimal('sale_price', 12, 3)->unsigned()->nullable();
            $table->decimal('minimum_quantity', 15, 3)->unsigned()->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'current_quantity']);
            $table->index(['name', 'color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_products');
    }
};
