<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'vendor_id',
        'creator_id',

        'product_name',      // ✅ MUST be here
        'product_sku',       // ✅ MUST be here
        'price',
        'quantity',
        'total',
        'mrp',
        'selling_price',
        // Coupon snapshot
        'coupon_discount',
        'coupon_funded_by',
        'vendor_coupon_share',
        'admin_coupon_share',
        // Tax snapshot
        'tax_rate',
        'tax_amount',
        'tax_breakdown',
        // Shipping snapshot
        'shipping_mode',
        'shipping_charge',
        // Commission snapshot
        'commission_type',
        'commission_rate',
        'commission_amount',
        // Final calculations
        'final_price',
        'vendor_payout',
        // Settlement tracking
        'settlement_status',
        'settled_at',
        'shipment_id',
        'shipped_at',
        'delivered_at',
        'eligible_for_settlement_at',
        'return_requested',
        'return_reason',
        'return_requested_at',
        'status'
    ];
protected $casts = [
    'vendor_id' => 'string',
    'creator_id' => 'integer',
];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }  // ✅ Add this relationship

    public function vendorTransactions()
    {
        return $this->hasMany(VendorTransaction::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }
    public function settlement()
    {
        return $this->belongsTo(Settlement::class, 'settlement_id');
    }

    public function earlySettlementRequest()
    {
        return $this->belongsTo(EarlySettlementRequest::class, 'early_settlement_request_id');
    }
}