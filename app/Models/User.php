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
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role_id',
        'avatar',
        'email_verified_at',
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
    ];

    // ----------------------------
    // Relationship: User belongs to Role
    // ----------------------------
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // ----------------------------
    // Check if user has a permission
    // ----------------------------
    public function hasPermission(string $module, string $action): bool
    {
        if (!$this->role || !$this->role->permissions) return false;

        $permissions = json_decode($this->role->permissions, true);

        return isset($permissions[$module]) && in_array($action, $permissions[$module]);
    }
}