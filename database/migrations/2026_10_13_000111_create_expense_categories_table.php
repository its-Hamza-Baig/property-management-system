<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            // purchase | property | sale | general
            $table->string('applies_to', 16)->default('general');
            // Capitalised categories increase the cost base of the property (and so
            // the investor allocation cap). Non-capitalised categories reduce profit
            // when the related sale is finalised. This flag is what stops the same
            // rupee being counted as both property cost and period expense.
            $table->boolean('is_capitalized')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
