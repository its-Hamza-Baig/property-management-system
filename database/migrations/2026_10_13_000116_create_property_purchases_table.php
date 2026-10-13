<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // One purchase record per property. The purchase price is recognised
            // exactly once, when the purchase is confirmed. Later seller payments
            // only settle the payable and never add cost again.
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('purchase_date');
            $table->decimal('purchase_price', 18, 2);
            // draft | pending_approval | confirmed | rejected
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_purchases');
    }
};
