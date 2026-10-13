<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained();
            $table->date('reservation_date');
            // The token is received as cash but is a liability (buyer advance)
            // until the sale is finalised. It is never profit on its own.
            $table->decimal('token_amount', 18, 2)->default(0);
            $table->decimal('agreed_price', 18, 2)->nullable();
            $table->date('expiry_date')->nullable();
            // active | converted | cancelled | expired
            $table->string('status', 16)->default('active');
            $table->date('cancellation_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('forfeited_amount', 18, 2)->default(0);
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
