<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null tenant_id means a platform user (Super Admin) with no workspace.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->string('role', 32)->default('staff')->after('password');
            // Granular staff permissions; account holders bypass this list.
            $table->json('permissions')->nullable()->after('role');
            $table->foreignId('investor_id')->nullable()->after('permissions');
            $table->boolean('is_active')->default(true)->after('investor_id');
            $table->string('phone', 32)->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->softDeletes();
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn([
                'tenant_id', 'role', 'permissions', 'investor_id', 'is_active', 'phone',
                'last_login_at', 'deactivated_at', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'deleted_at',
            ]);
        });
    }
};
