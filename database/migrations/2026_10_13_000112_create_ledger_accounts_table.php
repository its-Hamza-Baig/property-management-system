<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            // asset | liability | equity | income | expense
            $table->string('type', 16);
            // Stable key used by the posting services, e.g. property_cost,
            // buyer_receivable, seller_payable. See App\Support\LedgerAccounts.
            $table->string('system_key', 64)->nullable();
            $table->boolean('is_system')->default(true);
            // Each cash/bank account and each expense category owns a ledger account,
            // so balances and category totals come from the same single source.
            $table->foreignId('financial_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'system_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
