<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 64);
            $table->string('title');
            // Project / society stays free text on purpose: there is no separate
            // Projects/Blocks/Societies module in version 1.
            $table->string('project_name')->nullable();
            $table->string('block', 64)->nullable();
            $table->string('plot_number', 64)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('address')->nullable();
            $table->string('property_type', 32);
            $table->decimal('size', 14, 4)->nullable();
            $table->string('size_unit', 24)->nullable();
            // available -> reserved -> under_sale -> sold
            $table->string('status', 24)->default('available');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
