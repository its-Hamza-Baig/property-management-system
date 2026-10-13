<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // approval_request | approval_decision | installment_due |
            // installment_overdue | security | backup_failed
            $table->string('type', 48);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->morphs('remindable');
            $table->date('reference_date');
            $table->timestamp('sent_at');
            // Unique key below is what stops the scheduler sending the same
            // due-date or overdue reminder twice.
            $table->unique(['type', 'remindable_type', 'remindable_id', 'reference_date'], 'reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
