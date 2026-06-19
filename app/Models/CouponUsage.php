<?php
// app/Models/CouponUsage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;

class CouponUsage extends Model
{
    //use SoftDeletes;

    protected $table = 'coupon_usages';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'breakdown'
        // removed 'used_at' - using created_at instead
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'breakdown' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        //'deleted_at' => 'datetime'
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getFormattedDiscountAmountAttribute()
    {
        return '₹' . number_format($this->discount_amount, 2);
    }

    public function getPlatformShareAttribute()
    {
        return $this->breakdown['platform_share'] ?? 0;
    }

    public function getVendorShareAttribute()
    {
        return $this->breakdown['vendor_share'] ?? 0;
    }

    public function getFundingTypeAttribute()
    {
        return $this->breakdown['funded_by'] ?? ($this->coupon->funded_by ?? 'admin');
    }

    public function isAdminFunded()
    {
        return $this->funding_type === 'admin';
    }

    public function isVendorFunded()
    {
        return $this->funding_type === 'vendor';
    }

    public function isShared()
    {
        return $this->funding_type === 'shared';
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }
}