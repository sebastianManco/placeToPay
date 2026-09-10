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
     * Check if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        if (($this->attributes['role'] ?? null) === 'admin') {
            return true;
        }

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
        return $this->attributes['Direction'] ?? $this->attributes['direction'] ?? null;
    }

    /**
     * Set the user direction.
     */
    public function setDirectionAttribute(?string $value): void
    {
        $this->attributes['Direction'] = $value;
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

