<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profit_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_owner')->default(false);
            // The ratio actually used for this sale, copied at finalisation.
            $table->decimal('profit_share_percentage', 9, 4);
            // Negative when the sale made a loss.
            $table->decimal('profit_amount', 18, 2);
            // What has actually been handed over. Calculated share, amount payable
            // and amount paid are three different numbers and stay separate.
            $table->decimal('paid_amount', 18, 2)->default(0);
            // open | partially_paid | paid
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->unique(['sale_id', 'investor_id', 'is_owner'], 'ipp_sale_investor_unique');
            $table->index(['tenant_id', 'investor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profit_payables');
    }
};
