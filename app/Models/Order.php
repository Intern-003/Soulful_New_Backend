<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'address_id',
        'coupon_id',
        'subtotal',
        'discount',           // Maps to coupon_discount
        'tax',                // Maps to tax_total
        'shipping_total',
        'grand_total',
        'payment_method',
        'payment_status',
        'order_status',       // Maps to status
        'platform_coupon_discount',
        'vendor_coupon_discount',
        'settlement_status',
        'settled_at',
       // 'shipping_address',
        //'billing_address',
        //'notes',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'return_requested',
        'exchange_requested',
        'return_reason',
        'exchange_reason',
        'exchange_product_id',
        'exchange_variant_id',
        'return_requested_at',
        'exchange_requested_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'platform_coupon_discount' => 'decimal:2',
        'vendor_coupon_discount' => 'decimal:2',
        'settled_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'return_requested' => 'boolean',
        'exchange_requested' => 'boolean',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'notes' => 'string'
    ];

    // Aliases for compatibility
    public function getCouponDiscountAttribute()
    {
        return $this->discount;
    }

    public function getTaxTotalAttribute()
    {
        return $this->tax;
    }

    public function getStatusAttribute()
    {
        return $this->order_status;
    }

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function address() 
    { 
        return $this->belongsTo(Address::class); 
    }
    
    public function items() 
    { 
        return $this->hasMany(OrderItem::class); 
    }
    
    public function payments() 
    { 
        return $this->hasMany(Payment::class); 
    }
    
    public function shipments() 
    { 
        return $this->hasMany(Shipment::class); 
    }
    
    public function statusHistory()
    { 
        return $this->hasMany(OrderStatusHistory::class)->latest(); 
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }
}