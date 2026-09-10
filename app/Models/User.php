<?php

namespace App\Models;

use App\Traits\HasRolesAndPermissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'last_Name', 
        'email', 
        'phone', 
        'direction',
        'Direction',
        'identification',
        'user_name',
        'user_Name',
        'password',
        'email_verified_at',
        'is_active',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Temporary storage for role assignment during model creation/save.
     */
    protected ?string $transientRole = null;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->transientRole !== null) {
                $roleToSync = $user->transientRole;
                $user->transientRole = null;
                $user->syncRoles([$roleToSync]);
            } elseif ($user->roles()->count() === 0) {
                $user->assignRole('client');
            }
        });
    }

    /**
     * Accessor for legacy role attribute.
     * Returns the primary ACL role slug for backward compatibility.
     *
     * @deprecated Use $user->roles or $user->hasRole() instead.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->slug ?? 'client';
    }

    /**
     * Mutator for legacy role attribute.
     * Assigns the role through the ACL pivot relation.
     *
     * @deprecated Use $user->assignRole() or $user->syncRoles() instead.
     */
    public function setRoleAttribute(?string $value): void
    {
        if ($this->exists) {
            if ($value) {
                $this->syncRoles([$value]);
            }
        } else {
            $this->transientRole = $value;
        }
    }

    /**
     * Check if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Get the user direction.
     */
    public function getDirectionAttribute(): ?string
    {
        return $this->attributes['direction'] ?? $this->attributes['Direction'] ?? null;
    }

    /**
     * Set the user direction.
     */
    public function setDirectionAttribute(?string $value): void
    {
        $this->attributes['direction'] = $value;
    }

    /**
     * Get the user last name.
     */
    public function getLastNameAttribute(): ?string
    {
        return $this->attributes['last_name'] ?? $this->attributes['last_Name'] ?? null;
    }

    /**
     * Set the user last name.
     */
    public function setLastNameAttribute(?string $value): void
    {
        $this->attributes['last_name'] = $value;
    }

    /**
     * Get the user username.
     */
    public function getUserNameAttribute(): ?string
    {
        return $this->attributes['user_name'] ?? $this->attributes['user_Name'] ?? null;
    }

    /**
     * Set the user username.
     */
    public function setUserNameAttribute(?string $value): void
    {
        $this->attributes['user_name'] = $value;
    }

    /**
     * Get the route key for the model.
     * Preserves route model binding on identification while using id as the primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'identification';
    }
}

