<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Entitlements;
use Illuminate\Database\Seeder;

/**
 * The plans the platform starts with.
 *
 * The free plan is the default for every new signup. The others exist so the
 * Super Admin has something to move an account onto later; there is no
 * billing attached to any of them yet, which is exactly what was asked for.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'description' => 'The starting plan for every new account. Documents are off until enabled for a pilot account.',
            'is_default' => true,
            'is_active' => true,
            'limits' => array_merge(Entitlements::defaults(), [
                Entitlements::MAX_PROPERTIES => 25,
                Entitlements::MAX_STAFF => 3,
                Entitlements::DOCUMENTS_ENABLED => false,
                Entitlements::EXPORTS_ENABLED => true,
                Entitlements::INVESTOR_PORTAL_ENABLED => false,
            ]),
        ]);

        Plan::updateOrCreate(['slug' => 'pilot'], [
            'name' => 'Pilot',
            'description' => 'Free plan plus document uploads and the investor portal, for the one or two pilot accounts.',
            'is_default' => false,
            'is_active' => true,
            'limits' => array_merge(Entitlements::defaults(), [
                Entitlements::MAX_PROPERTIES => 100,
                Entitlements::MAX_STAFF => 10,
                Entitlements::DOCUMENTS_ENABLED => true,
                Entitlements::MAX_STORAGE_MB => 500,
                Entitlements::MAX_FILE_SIZE_MB => 10,
                Entitlements::EXPORTS_ENABLED => true,
                Entitlements::INVESTOR_PORTAL_ENABLED => true,
                Entitlements::MAX_INVESTOR_PORTAL_USERS => 20,
            ]),
        ]);

        Plan::updateOrCreate(['slug' => 'unlimited'], [
            'name' => 'Unlimited',
            'description' => 'No counted limits. Intended for internal use and future paid tiers.',
            'is_default' => false,
            'is_active' => true,
            'limits' => array_merge(Entitlements::defaults(), [
                Entitlements::MAX_PROPERTIES => -1,
                Entitlements::MAX_STAFF => -1,
                Entitlements::DOCUMENTS_ENABLED => true,
                Entitlements::MAX_STORAGE_MB => 5000,
                Entitlements::MAX_FILE_SIZE_MB => 20,
                Entitlements::EXPORTS_ENABLED => true,
                Entitlements::INVESTOR_PORTAL_ENABLED => true,
                Entitlements::MAX_INVESTOR_PORTAL_USERS => -1,
            ]),
        ]);
    }
}
