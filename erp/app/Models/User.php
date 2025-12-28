<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
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
        ];
    }

    /**
     * Get the tenant that this user belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if user is a superadmin (no tenant assigned).
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('superadmin');
    }

    /**
     * Check if user is a tenant admin.
     */
    public function isTenantAdmin(): bool
    {
        return $this->tenant_id !== null && $this->hasRole('tenant_admin');
    }

    /**
     * Check if user is a tenant staff member.
     */
    public function isTenantStaff(): bool
    {
        return $this->tenant_id !== null && $this->hasRole('tenant_staff');
    }

    /**
     * Check if user belongs to a specific tenant.
     */
    public function belongsToTenant(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenant_id === $tenantId;
    }

    /**
     * Determine if the user can access a Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->isSuperAdmin();
        }

        if ($panel->getId() === 'tenant') {
            return $this->tenant_id !== null;
        }

        return false;
    }
}
