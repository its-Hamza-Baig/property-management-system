<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_investor_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // A null investor with is_owner = true is the account holder. Modelling
            // the owner as a participant row keeps every percentage total at 100
            // without special-casing "the rest belongs to the owner" in code.
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->decimal('capital_amount', 18, 2)->default(0);
            $table->date('contribution_date')->nullable();
            // Ownership and profit sharing are separate agreements. They default to
            // the capital ratio but may be configured independently, and each is
            // validated to total 100 percent across the active rows.
            $table->decimal('ownership_percentage', 9, 4)->default(0);
            $table->decimal('profit_share_percentage', 9, 4)->default(0);
            // active | exited | transferred
            $table->string('status', 16)->default('active');
            // Versioned by effective date so a finalised sale keeps the exact
            // ratios that were in force when it was finalised.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('agreement_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'investor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_investor_allocations');
    }
};
