<?php

namespace App\Support;

/**
 * The catalogue of configurable account entitlements.
 *
 * Adding a future plan feature means adding a key here and a default on the
 * plan. No schema change and no rewrite, which is what the client asked for
 * when free accounts later become paid plans.
 */
class Entitlements
{
    public const MAX_PROPERTIES = 'max_properties';
    public const MAX_STAFF = 'max_staff';
    public const DOCUMENTS_ENABLED = 'documents_enabled';
    public const MAX_STORAGE_MB = 'max_storage_mb';
    public const MAX_FILE_SIZE_MB = 'max_file_size_mb';
    public const ALLOWED_FILE_TYPES = 'allowed_file_types';
    public const EXPORTS_ENABLED = 'exports_enabled';
    public const INVESTOR_PORTAL_ENABLED = 'investor_portal_enabled';
    public const MAX_INVESTOR_PORTAL_USERS = 'max_investor_portal_users';

    /**
     * key => [label, type, default, help]
     * Type is one of: int, bool, csv. An int value of -1 means unlimited.
     *
     * @return array<string, array{label:string,type:string,default:mixed,help:string}>
     */
    public static function definitions(): array
    {
        return [
            self::MAX_PROPERTIES => [
                'label' => 'Maximum active properties',
                'type' => 'int',
                'default' => 25,
                'help' => 'Sold properties do not count towards this limit. Use -1 for unlimited.',
            ],
            self::MAX_STAFF => [
                'label' => 'Maximum staff accounts',
                'type' => 'int',
                'default' => 3,
                'help' => 'Deactivated staff do not count. Use -1 for unlimited.',
            ],
            self::DOCUMENTS_ENABLED => [
                'label' => 'Document uploads',
                'type' => 'bool',
                'default' => false,
                'help' => 'Off by default. Enable only for pilot accounts while storage is limited.',
            ],
            self::MAX_STORAGE_MB => [
                'label' => 'Maximum document storage (MB)',
                'type' => 'int',
                'default' => 200,
                'help' => 'Total size of all uploaded files for this account.',
            ],
            self::MAX_FILE_SIZE_MB => [
                'label' => 'Maximum single file size (MB)',
                'type' => 'int',
                'default' => 5,
                'help' => 'Applies to each individual upload.',
            ],
            self::ALLOWED_FILE_TYPES => [
                'label' => 'Permitted file types',
                'type' => 'csv',
                'default' => 'pdf,jpg,jpeg,png',
                'help' => 'Comma separated list of extensions.',
            ],
            self::EXPORTS_ENABLED => [
                'label' => 'Excel and PDF export',
                'type' => 'bool',
                'default' => true,
                'help' => 'Turn off to restrict exporting on a limited plan.',
            ],
            self::INVESTOR_PORTAL_ENABLED => [
                'label' => 'Investor portal',
                'type' => 'bool',
                'default' => false,
                'help' => 'Allows the account holder to give selected investors a read-only login.',
            ],
            self::MAX_INVESTOR_PORTAL_USERS => [
                'label' => 'Maximum investor portal logins',
                'type' => 'int',
                'default' => 5,
                'help' => 'Use -1 for unlimited.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /** Cast a stored string value to the type declared for the key. */
    public static function cast(string $key, mixed $value): mixed
    {
        $type = self::definitions()[$key]['type'] ?? 'int';

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'csv' => array_values(array_filter(array_map(
                static fn ($part) => strtolower(trim($part)),
                explode(',', (string) $value)
            ))),
            default => (int) $value,
        };
    }
}
