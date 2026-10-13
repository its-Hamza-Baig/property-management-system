<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_profit_payable_id')->constrained('investor_profit_payables')->cascadeOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->constrained();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 24)->default('cash');
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('confirmed');
            $table->foreignId('reversal_of_id')->nullable()->constrained('investor_distributions')->nullOnDelete();
            // Posts against investor profit payable, never against an expense
            // account: paying an investor is a settlement, not a property cost.
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_distributions');
    }
};
