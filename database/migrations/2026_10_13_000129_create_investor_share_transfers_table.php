<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_share_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // Null means the account holder is the counterparty.
            $table->foreignId('from_investor_id')->nullable()->constrained('investors')->nullOnDelete();
            $table->foreignId('to_investor_id')->nullable()->constrained('investors')->nullOnDelete();
            $table->date('transfer_date');
            $table->decimal('capital_amount', 18, 2)->default(0);
            $table->decimal('ownership_percentage', 9, 4)->default(0);
            $table->decimal('profit_share_percentage', 9, 4)->default(0);
            // A share transfer settles between the two parties. It is NOT a property
            // sale and must never post to sale revenue or realised profit.
            $table->decimal('settlement_amount', 18, 2)->default(0);
            $table->string('settlement_status', 16)->default('unpaid');
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_share_transfers');
    }
};
