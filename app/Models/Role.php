<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id');
    }

    /**
     * The users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Determine if the role has a given permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions->contains('slug', $permissionSlug);
    }

    /**
     * Assign a permission to this role.
     */
    public function givePermission(string|Permission $permission): self
    {
        $permissionModel = is_string($permission)
            ? Permission::where('slug', $permission)->firstOrFail()
            : $permission;

        $this->permissions()->syncWithoutDetaching([$permissionModel->id]);

        return $this;
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(string|Permission $permission): self
    {
        $permissionId = is_string($permission)
            ? Permission::where('slug', $permission)->value('id')
            : $permission->id;

        if ($permissionId) {
            $this->permissions()->detach($permissionId);
        }

        return $this;
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(array|Collection $permissions): self
    {
        $ids = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $ids[] = $permission->id;
            } elseif (is_numeric($permission)) {
                $ids[] = (int) $permission;
            } elseif (is_string($permission)) {
                $id = Permission::where('slug', $permission)->value('id');
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        $this->permissions()->sync($ids);

        return $this;
    }

    /**
     * Check if this role is a protected system role.
     */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
