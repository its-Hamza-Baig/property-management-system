<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('cnic', 32)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            // Optional portal login, gated by the investor_portal entitlement.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('portal_enabled')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Investors are never hard-deleted. Soft delete is for list hygiene only
            // and never detaches historical financial records.
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
