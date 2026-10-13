<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the models whose only rules are "same workspace" and "holds the
 * right permission". Keeps a dozen near-identical policy classes out of the
 * codebase without weakening the check.
 */
class SimpleTenantPolicy extends BasePolicy
{
    /** @var array<class-string, array{view:string,manage:string}> */
    private static array $map = [];

    /** @param array<class-string, array{view:string,manage:string}> $map */
    public static function configure(array $map): void
    {
        self::$map = $map;
    }

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allow($user, $this->permission($model, 'view'), $model);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allow($user, $this->permission($model, 'manage'), $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allow($user, $this->permission($model, 'manage'), $model);
    }

    private function permission(Model $model, string $kind): string
    {
        return self::$map[$model::class][$kind] ?? 'nonexistent.permission';
    }
}
