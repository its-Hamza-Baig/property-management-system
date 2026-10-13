<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // property_purchase | sale_finalization | expense | refund | investor_payout
            $table->string('action', 32);
            $table->boolean('is_enabled')->default(false);
            // Null threshold means every action of this type needs approval.
            // Otherwise only amounts at or above the threshold do.
            $table->decimal('threshold_amount', 18, 2)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
