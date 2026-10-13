<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->constrained();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 24)->default('cash'); // cash|bank_transfer|cheque|other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            // confirmed | reversed. A reversal is a new linked row, never an edit.
            $table->string('status', 16)->default('confirmed');
            $table->foreignId('reversal_of_id')->nullable()->constrained('seller_payments')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            // Guards against a double-submitted form creating two payments.
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payments');
    }
};
