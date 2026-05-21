<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorWallet extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'user_id', 'balance'];

    public function vendor() 
    { 
        return $this->belongsTo(Vendor::class, 'vendor_id'); 
    }
    
    public function user() 
    { 
        return $this->belongsTo(User::class, 'user_id'); 
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