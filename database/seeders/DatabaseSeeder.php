<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        // The platform administrator. Nothing else on the platform works
        // until somebody can approve the first account.
        $email = config('pms.super_admin_email');

        if (! User::where('email', $email)->exists()) {
            User::create([
                'tenant_id' => null,
                'name' => 'Super Admin',
                'email' => $email,
                'password' => 'password',
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->command?->warn("Super Admin created: {$email} / password");
            $this->command?->warn('Change this password immediately after the first login.');
        }
    }
}
