<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // One table covers purchase expenses, holding expenses and sale expenses.
            // Keeping them together is what makes "never count an expense twice"
            // a property of the schema rather than of each controller.
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable();
            $table->foreignId('expense_category_id')->constrained();
            $table->date('expense_date');
            $table->decimal('amount', 18, 2);
            $table->string('description')->nullable();
            // draft | pending_approval | confirmed | rejected | cancelled
            $table->string('status', 24)->default('draft');
            // Snapshot of the category flag at the time of recognition, so later
            // category edits cannot silently restate historical profit.
            $table->boolean('is_capitalized')->default(false);
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'expense_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
