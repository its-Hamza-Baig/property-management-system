<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // A transfer only moves money between two owned accounts. It posts a
            // balanced journal that touches no income or expense account, so it can
            // never show up as a sale, a receipt or a cost in any report.
            $table->foreignId('from_financial_account_id')->constrained('financial_accounts');
            $table->foreignId('to_financial_account_id')->constrained('financial_accounts');
            $table->date('transfer_date');
            $table->decimal('amount', 18, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
