<?php

use App\Enums\SupplierScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country')->nullable();
            $table->enum('module_scope', SupplierScope::values())->default(SupplierScope::Both->value);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module_scope', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
