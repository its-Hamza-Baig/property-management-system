<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            // Fixed precision only. Never floats for money.
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            // Dimensions so every balance can be sliced per property and per investor
            // from this one table, instead of re-deriving totals elsewhere.
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('memo')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'ledger_account_id']);
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'investor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
