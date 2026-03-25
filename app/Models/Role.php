<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Many-to-Many: Role ↔ Users
     */
    // One Role → Many Users
    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }



    public function vendors()
    {
        return $this->hasMany(Vendor::class);
    }
    /**
     * Many-to-Many: Role ↔ Permissions
     */
    // public function permissions()
    // {
    //     return $this->belongsToMany(Permission::class, 'role_permissions');
    // }

    // /**
    //  * Check if role has a specific permission
    //  */
    // public function hasPermission(string $permissionName): bool
    // {
    //     return $this->permissions()
    //         ->where('name', $permissionName)
    //         ->exists();
    // }

     // 🔗 Role → Permissions (Many to Many)
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }
}