<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->active) {
            return false;
        }

        // Assigned roles are authoritative, including for administrators.
        // Do not use Gate::can(): Shield's super-admin gate bypasses unchecked permissions.
        if ($this->roles()->exists()) {
            return $this->roles()->whereHas('permissions', fn ($query) => $query
                ->where('name', $permission)->where('guard_name', 'web'))->exists();
        }

        // Support the initial administrator before roles have been installed.
        return $this->is_admin
            || $this->permissions()->where('name', $permission)->where('guard_name', 'web')->exists()
            || in_array($permission, $this->legacy_permissions ?? [], true);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'active',
        'is_admin',
        'legacy_permissions',
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
            'active' => 'boolean',
            'is_admin' => 'boolean',
            'legacy_permissions' => 'array',
        ];
    }
}

