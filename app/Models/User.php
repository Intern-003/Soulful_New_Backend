<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',

        'role_id',

        'status',

        'email_verified_at',

        'phone_verified_at',

        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for arrays / JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [

        'email_verified_at' => 'datetime',

        'phone_verified_at' => 'datetime',

        'last_login_at' => 'datetime',

        'status' => 'boolean',
    ];

    // ----------------------------
    // Relationship: User belongs to Role
    // ----------------------------
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    // ----------------------------
    // Relationship: User has one Vendor
    // ----------------------------
    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function sellerWallet()
    {
        return $this->hasOne(VendorWallet::class, 'user_id');
    }

    // ----------------------------
    // Relationship: User has many Orders
    // ----------------------------
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class, 'user_id');
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

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ----------------------------
    // Check permission
    // ----------------------------
    public function hasPermission($permissionName)
    {
        if (!$this->role) {
            return false;
        }

        return $this->role
            ->permissions()
            ->where('name', $permissionName)
            ->exists();
    }

    // ----------------------------
    // Helper: Active User
    // ----------------------------
    public function isActive(): bool
    {
        return $this->status === true;
    }
}