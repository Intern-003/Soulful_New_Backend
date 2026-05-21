<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'user_id', 'amount', 'status', 'requested_at', 'approved_at'];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'amount' => 'decimal:2'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // public function transactions()
    // {
    //     return $this->hasMany(VendorTransaction::class, 'withdraw_request_id');
    // }
    // Helper to get the seller name
    public function getSellerNameAttribute()
    {
        if ($this->vendor) {
            return $this->vendor->store_name;
        }
        if ($this->user) {
            return $this->user->name . ' (Individual Seller)';
        }
        return 'Unknown';
    }
}