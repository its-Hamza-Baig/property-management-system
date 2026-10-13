<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_payments', function (Blueprint $table) {
            // Which ledger account this receipt was credited to when it was
            // posted: buyer_advance before the sale is finalised, or
            // buyer_receivable afterwards.
            //
            // Recording it makes finalisation and cancellation exact. Without
            // it the code has to guess, and a receipt taken before
            // finalisation can be left stranded as both an advance and an
            // outstanding receivable.
            $table->string('credited_to', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_payments', function (Blueprint $table) {
            $table->dropColumn('credited_to');
        });
    }
};
