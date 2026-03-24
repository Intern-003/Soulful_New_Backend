<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'avatar',
        'email_verified_at',
        'last_login_at',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'status' => 'boolean',
    ];

    // ----------------------------
    // 🔹 RBAC RELATIONS
    // ----------------------------

    /**
     * Many-to-Many: User ↔ Roles
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Many-to-Many: User ↔ Permissions (Overrides)
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('is_allowed');
    }

    // ----------------------------
    // 🔹 BUSINESS RELATIONS
    // ----------------------------

    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ----------------------------
    // 🔥 CORE PERMISSION LOGIC
    // ----------------------------

public function hasPermission(string $permissionName): bool
{
    // 1. Super Admin → full access
    if ($this->roles->contains('name', 'super_admin')) {
        return true;
    }

    // 2. Get permission record
    $permission = Permission::where('name', $permissionName)->first();

    if (!$permission) {
        return false;
    }

    // 3. Check USER override (highest priority)
    $userPermission = $this->permissions()
        ->where('permission_id', $permission->id)
        ->first();

    if ($userPermission) {
        return (bool) $userPermission->pivot->is_allowed;
    }

    // 4. Check ROLE permissions
    foreach ($this->roles as $role) {
        if ($role->permissions->contains('id', $permission->id)) {
            return true;
        }
    }

    // 5. Default deny
    return false;
}

    // ----------------------------
    // 🔹 Helper: Active User
    // ----------------------------

    public function isActive(): bool
    {
        return $this->status === true;
    }
}