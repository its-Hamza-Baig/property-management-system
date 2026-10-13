<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->constrained();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->constrained();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 24)->default('cash');
            $table->string('reference')->nullable();
            // token | advance | installment | final | late_fee | refund
            // The type decides the accounting treatment: a token credits a buyer
            // advance (liability), an installment clears the buyer receivable.
            // Neither is ever treated as profit.
            $table->string('type', 16)->default('installment');
            $table->string('status', 16)->default('confirmed');
            $table->foreignId('reversal_of_id')->nullable()->constrained('buyer_payments')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'payment_date']);
            $table->index(['tenant_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_payments');
    }
};
