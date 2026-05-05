<?php

namespace Dcat\Admin\Traits;

use Dcat\Admin\Support\Helper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

trait HasPermissions
{
    protected $allPermissions;

    /**
     * Get all permissions of user.
     *
     * @return Collection
     */
    public function allPermissions(): Collection
    {
        if ($this->allPermissions) {
            return $this->allPermissions;
        }

        return $this->allPermissions =
            $this->roles
            ->pluck('permissions')
            ->flatten()
            ->keyBy($this->getKeyName());
    }

    /**
     * Get permissions map for fast lookup.
     *
     * @return array
     */
    protected function getPermissionsMap(): array
    {
        return once(function () {
            $slugs = [];
            $ids = [];
            foreach ($this->allPermissions() as $perm) {
                $slugs[$perm->slug] = true;
                $ids[$perm->id] = true;
            }

            return ['slugs' => $slugs, 'ids' => $ids];
        });
    }

    /**
     * Get roles map for fast lookup.
     *
     * @return array
     */
    protected function getRolesMap(): array
    {
        return once(function () {
            $slugs = [];
            $ids = [];
            foreach ($this->roles as $role) {
                $slugs[$role->slug] = true;
                $ids[$role->id] = true;
            }

            return ['slugs' => $slugs, 'ids' => $ids];
        });
    }

    /**
     * Check if user has permission.
     *
     * @param  string|int  $ability
     * @param  array|mixed  $paramters
     * @return bool
     */
    public function can($ability, $paramters = []): bool
    {
        if (! $ability) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        $map = $this->getPermissionsMap();

        return isset($map['slugs'][$ability]) || isset($map['ids'][$ability]);
    }

    /**
     * Check if user has no permission.
     *
     * @param  string  $permission
     * @return bool
     */
    public function cannot(string $permission): bool
    {
        return ! $this->can($permission);
    }

    /**
     * Check if user is administrator.
     *
     * @return bool
     */
    public function isAdministrator(): bool
    {
        /** @var \Dcat\Admin\Models\Role $roleModel */
        $roleModel = config('admin.database.roles_model');

        return $this->isRole($roleModel::ADMINISTRATOR);
    }

    /**
     * Check if user is $role.
     *
     * @param  string|int  $role
     * @return bool
     */
    public function isRole(string|int $role): bool
    {
        $map = $this->getRolesMap();

        return isset($map['slugs'][$role]) || isset($map['ids'][$role]);
    }

    /**
     * Check if user in $roles.
     *
     * @param  string|array|Arrayable  $roles
     * @return bool
     */
    public function inRoles($roles = []): bool
    {
        $roles = Helper::array($roles);

        if (empty($roles)) {
            return false;
        }

        $map = $this->getRolesMap();

        foreach ($roles as $role) {
            if (isset($map['slugs'][$role]) || isset($map['ids'][$role])) {
                return true;
            }
        }

        return false;
    }

    /**
     * If visible for roles.
     *
     * @param  array  $roles
     * @return bool
     */
    public function visible($roles = []): bool
    {
        if (empty($roles)) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        return $this->inRoles($roles);
    }

    /**
     * Detach models from the relationship.
     *
     * @return void
     */
    protected static function bootHasPermissions()
    {
        static::deleting(function ($model) {
            $model->roles()->detach();
        });
    }
}
