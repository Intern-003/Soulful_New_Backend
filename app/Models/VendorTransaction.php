<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_wallet_id',  // Changed from vendor_id
        'order_item_id',
        'amount',
        'commission',
        'net_amount',
        'status',
        'description',  // Added for better tracking
        'type'          // Added: 'credit' or 'debit'
    ];

    public function wallet()
    {
        return $this->belongsTo(VendorWallet::class, 'vendor_wallet_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
    // public function withdrawRequest()
    // {
    //     return $this->belongsTo(WithdrawRequest::class);
    // }
    // public function vendor()
    // {
    //     return $this->hasOneThrough(
    //         Vendor::class,
    //         VendorWallet::class,
    //         'id',
    //         'id',
    //         'vendor_wallet_id',
    //         'vendor_id'
    //     );
    // }
}