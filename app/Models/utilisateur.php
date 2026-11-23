<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'Fullname',
        'password',
        'status',
        'role',
        'is_system',
        'remember_token', // added for reset flag
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_system' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================
    public function permissions()
    {
        return $this->hasMany(UserPermission::class, 'user_id');
    }

    // ==================== ROLE CHECKS ====================
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isEmployee()
    {
        return $this->role === 'employee';
    }

    public function isSuperAdmin()
    {
        return $this->role === 'superadmin';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isInactive()
    {
        return $this->status === 'inactive';
    }

    public function isSystem()
    {
        return $this->is_system === true;
    }

    // ==================== PERMISSION METHODS ====================
    public function hasValidPermission(string $pageSlug): bool
    {
        return $this->permissions()
            ->where('page_slug', $pageSlug)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function grantPermission(string $pageSlug, ?int $durationMinutes = null, ?Utilisateur $granter = null): UserPermission
    {
        $expiresAt = $durationMinutes ? now()->addMinutes($durationMinutes) : null;
        return UserPermission::updateOrCreate(
            ['user_id' => $this->id, 'page_slug' => $pageSlug],
            ['expires_at' => $expiresAt, 'granted_by' => $granter?->id]
        );
    }

    public function revokePermission(string $pageSlug): void
    {
        $this->permissions()->where('page_slug', $pageSlug)->delete();
    }

    // ==================== PASSWORD RESET (using remember_token) ====================
    public function requestPasswordReset(): void
    {
        $this->remember_token = 'reset_requested';
        $this->save();
    }

    public function clearPasswordReset(): void
    {
        $this->remember_token = null;
        $this->save();
    }

    public function hasPasswordResetRequest(): bool
    {
        return $this->remember_token === 'reset_requested';
    }

    // ==================== SCOPES ====================
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeCanLogin($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNonSystem($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}