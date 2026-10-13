<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_purchase_sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            // Seller-wise split of the purchase price. The sum of these rows must
            // equal the purchase price, which gives seller-wise payable tracking.
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['property_purchase_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_purchase_sellers');
    }
};
