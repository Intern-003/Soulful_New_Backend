<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',    // product.create
        'module',  // product
        'action',  // create
    ];

    /**
     * Many-to-Many: Permission ↔ Roles
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Many-to-Many: Permission ↔ Users (Overrides)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot('is_allowed');
    }
    public function getFullNameAttribute()
{
    return "{$this->module}.{$this->action}";
}
}