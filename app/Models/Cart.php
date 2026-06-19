<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_token', 
        'coupon_id',
        'coupon_discount',
        'platform_coupon_discount',
        'vendor_coupon_discount',
        'subtotal',
        'shipping_total',
        'tax_total',
        'grand_total',
        'coupon_data',
        'discount_amount'  // Keeping for backward compatibility
    ];

    protected $casts = [
        'coupon_data' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'platform_coupon_discount' => 'decimal:2',
        'vendor_coupon_discount' => 'decimal:2'
    ];

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }

    public function items() 
    { 
        return $this->hasMany(CartItem::class); 
    }

    public function coupon() 
    { 
        return $this->belongsTo(Coupon::class); 
    }
}