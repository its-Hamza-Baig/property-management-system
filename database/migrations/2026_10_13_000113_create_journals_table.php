<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('journal_date');
            $table->string('memo');
            // The business record that caused this journal.
            $table->nullableMorphs('source');
            // Reversals and audited corrections never overwrite the original journal.
            // They are new balanced journals that point back at it.
            $table->foreignId('reversal_of_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->boolean('is_adjustment')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['tenant_id', 'journal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
