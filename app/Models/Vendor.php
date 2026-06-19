<?php
// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Factories\HasFactory;

// class Vendor extends Model
// {
//     use HasFactory;

//     protected $fillable = [
//         'user_id',
//         'store_name',
//         'store_slug',
//         'store_logo',
//         'store_banner',
//         'description',
//         'commission_rate',
//         'rating',
//         'status',
//         'approved_by',
//         'approved_at'
//     ];

//     protected $casts = ['approved_at' => 'datetime'];

//     public function user()
//     {
//         return $this->belongsTo(User::class);
//     }
//     public function documents()
//     {
//         return $this->hasMany(VendorDocument::class);
//     }
//     public function products()
//     {
//         return $this->hasMany(Product::class);
//     }
//     public function orders()
//     {
//         return $this->hasManyThrough(Order::class, OrderItem::class, 'vendor_id', 'id', 'id', 'order_id');
//     }
//     public function sellerWallet()
//     {
//         return $this->hasOne(VendorWallet::class, 'vendor_id');
//     }
//     public function transactions()
//     {
//         return $this->hasManyThrough(VendorTransaction::class, VendorWallet::class, 'vendor_id', 'vendor_wallet_id');
//     }

//     // ✅ UPDATED: Withdraw Requests relationship
//     public function withdrawRequests()
//     {
//         return $this->hasMany(WithdrawRequest::class, 'vendor_id');
//     }

//     public function role()
//     {
//         return $this->belongsTo(Role::class);
//     }
//     public function hasPermission($permissionName)
//     {
//         if (!$this->role)
//             return false;

//         return $this->role->permissions()
//             ->where('name', $permissionName)
//             ->exists();
//     }

//     public function cartItems()
//     {
//         return $this->hasMany(CartItem::class, 'vendor_id');
//     }

//     public function gstSetting()
// {
//     return $this->hasOne(GstSetting::class);
// }

// public function settlements()
// {
//     return $this->hasMany(Settlement::class);
// }

// public function earlySettlementRequests()
// {
//     return $this->hasMany(EarlySettlementRequest::class);
// }

// public function commissionLogs()
// {
//     return $this->hasMany(CommissionLog::class);
// }
// }


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'store_logo',
        'store_banner',
        'description',
        'commission_rate',
        'rating',
        'status',
        'approved_by',
        'approved_at'
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function orders()
    {
        return $this->hasManyThrough(Order::class, OrderItem::class, 'vendor_id', 'id', 'id', 'order_id');
    }
    
    // ✅ ADD THIS RELATIONSHIP
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'vendor_id');
    }
    
    public function sellerWallet()
    {
        return $this->hasOne(VendorWallet::class, 'vendor_id');
    }
    
    public function transactions()
    {
        return $this->hasManyThrough(VendorTransaction::class, VendorWallet::class, 'vendor_id', 'vendor_wallet_id');
    }

    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class, 'vendor_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    public function hasPermission($permissionName)
    {
        if (!$this->role)
            return false;

        return $this->role->permissions()
            ->where('name', $permissionName)
            ->exists();
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'vendor_id');
    }

    public function gstSetting()
    {
        return $this->hasOne(GstSetting::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function earlySettlementRequests()
    {
        return $this->hasMany(EarlySettlementRequest::class);
    }

    public function commissionLogs()
    {
        return $this->hasMany(CommissionLog::class);
    }
}