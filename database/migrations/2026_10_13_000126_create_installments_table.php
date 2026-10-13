<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            // Running total of confirmed buyer payments applied to this installment.
            // A schedule row is a plan, not a receipt: it never moves cash by itself.
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('late_fee', 18, 2)->default(0);
            // pending | partial | paid | waived
            $table->string('status', 16)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sale_id', 'installment_no']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
