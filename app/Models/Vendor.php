<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','store_name','store_slug','store_logo','store_banner',
        'description','commission_rate','rating','status','approved_by','approved_at'
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function documents() { return $this->hasMany(VendorDocument::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function orders() { return $this->hasManyThrough(Order::class, OrderItem::class,'vendor_id','id','id','order_id'); }
    public function wallet() { return $this->hasOne(VendorWallet::class); }
    public function transactions() { return $this->hasMany(VendorTransaction::class); }
    public function withdrawRequests() { return $this->hasMany(WithdrawRequest::class); }

    public function role()
{
    return $this->belongsTo(Role::class);
}
    public function hasPermission($permissionName)
{
    if (!$this->role) return false;

    return $this->role->permissions()
        ->where('name', $permissionName)
        ->exists();
}
}