<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salami_customers', function (Blueprint $table): void {
            $table->foreignId('delivery_agent_id')->nullable()->after('id')->constrained('salami_delivery_agents')->restrictOnDelete();
            $table->index(['delivery_agent_id', 'is_active']);
        });

        Schema::table('salami_invoices', function (Blueprint $table): void {
            $table->foreignId('delivery_agent_id')->nullable()->after('customer_id')->constrained('salami_delivery_agents')->restrictOnDelete();
            $table->index(['delivery_agent_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::table('salami_invoices', function (Blueprint $table): void {
            $table->dropForeign(['delivery_agent_id']);
            $table->dropIndex(['delivery_agent_id', 'invoice_date']);
            $table->dropColumn('delivery_agent_id');
        });

        Schema::table('salami_customers', function (Blueprint $table): void {
            $table->dropForeign(['delivery_agent_id']);
            $table->dropIndex(['delivery_agent_id', 'is_active']);
            $table->dropColumn('delivery_agent_id');
        });
    }
};
