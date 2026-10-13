<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_funding_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            // WHOSE CAPITAL the expense represents. This is deliberately separate
            // from expense_payments, which records WHO ACTUALLY PAID the money.
            // The two concepts must never be merged.
            $table->string('funder_type', 24); // account_holder | investor
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->index(['tenant_id', 'expense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_funding_sources');
    }
};
