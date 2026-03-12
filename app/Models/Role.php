<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'permissions', // legacy JSON permissions (optional)
    ];

    protected $casts = [
        'permissions' => 'array', // JSON column cast to array
    ];

    // ----------------------------
    // Relationship: Role has many Users
    // ----------------------------
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ----------------------------
    // Relationship: Role belongs to many Permissions (via role_permissions pivot)
    // ----------------------------
    public function permissionsList()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    // ----------------------------
    // Check if Role has a permission
    // ----------------------------
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissionsList()->where('name', $permissionName)->exists();
    }
}