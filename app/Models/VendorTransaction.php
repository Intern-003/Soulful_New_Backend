<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_wallet_id',
        'vendor_id',           // ✅ ADD THIS
        'order_item_id',
        'amount',
        'coupon_amount',       // ✅ ADD THIS
        'tax_amount',          // ✅ ADD THIS
        'shipping_amount',     // ✅ ADD THIS
        'commission',
        'commission_rate',     // ✅ ADD THIS
        'net_amount',
        'type',
        'source',              // ✅ ADD THIS
        'description',
        'status',
        'reference_id'         // ✅ ADD THIS
    ];

    protected $casts = [
    'vendor_id' => 'string',
];

    public function wallet()
    {
        return $this->belongsTo(VendorWallet::class, 'vendor_wallet_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
    
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}