<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained();
            $table->foreignId('buyer_id')->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('agreement_date');
            $table->decimal('sale_price', 18, 2);
            // draft | pending_approval | approved | finalized | cancelled | rejected
            $table->string('status', 24)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            // Frozen at finalisation so later cost or category edits cannot restate
            // a closed sale. Corrections go through a new audited adjustment.
            $table->decimal('allocated_cost', 18, 2)->default(0);
            $table->decimal('sale_expense_total', 18, 2)->default(0);
            $table->decimal('realized_profit', 18, 2)->default(0);
            $table->date('cancellation_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('forfeited_amount', 18, 2)->default(0);
            $table->decimal('refunded_amount', 18, 2)->default(0);
            // A cancelled sale is kept forever. A later sale of the same property
            // is a NEW row, never an overwrite of the cancelled one.
            $table->foreignId('supersedes_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'property_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
        Schema::dropIfExists('sales');
    }
};
