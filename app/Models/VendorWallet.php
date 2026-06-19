<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Helpers\VendorHelper;

class VendorWallet extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'user_id', 'balance',
    'pending_balance',
    'available_balance',
    'total_earned',
    'total_withdrawn',
    'total_commission_paid',];

    protected $casts = [
    'vendor_id' => 'string',
    'balance' => 'decimal:2',
    'pending_balance' => 'decimal:2',
    'available_balance' => 'decimal:2',
    'total_earned' => 'decimal:2',
    'total_withdrawn' => 'decimal:2',
    'total_commission_paid' => 'decimal:2',
];

    // public function vendor() 
    // { 
    //     return $this->belongsTo(Vendor::class, 'vendor_id'); 
    // }
    public function vendor()
{
    // Only return vendor relationship if vendor_id is numeric (actual vendor)
    if (is_numeric($this->vendor_id)) {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
    return null;
}
    
    // public function user() 
    // { 
    //     return $this->belongsTo(User::class, 'user_id'); 
    // }

    public function creator()
{
    // Return creator relationship if vendor_id is a creator string
    if (VendorHelper::isCreatorVendor($this->vendor_id)) {
        $creatorId = VendorHelper::getCreatorIdFromVendorId($this->vendor_id);
        return $this->belongsTo(User::class, 'creator_id');
    }
    return null;
}

// Accessor to get display name
public function getDisplayNameAttribute()
{
    return VendorHelper::getVendorDisplayName($this->vendor_id, $this->vendor);
}
    
    public function transactions() 
    { 
        return $this->hasMany(VendorTransaction::class, 'vendor_wallet_id'); 
    }

    public function withdrawRequests() 
    { 
        if ($this->vendor_id) {
            return $this->hasMany(WithdrawRequest::class, 'vendor_id', 'vendor_id');
        }
        return $this->hasMany(WithdrawRequest::class, 'user_id', 'user_id');
    }
}