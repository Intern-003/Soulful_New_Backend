<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    protected $fillable = [
        'vendor_id',
        'creator_id',
        'code',
        //'name',
        //'description',
        'type',
        'value',
        'funded_by',
        'vendor_share_percentage',
        'admin_share_percentage',
        'applies_to',
        'category_id',
        'product_id',
        'applicable_vendors',
        'min_order_amount',      // Database column name
        'max_discount',          // Database column name
        'usage_limit',
        'used_count',
        'start_date',            // Database column name
        'expiry_date',           // Database column name
        'status',                // Database column name (use status, not is_active)
        'show_on_listing',
        'usage_limit_per_user'
    ];

    protected $casts = [
        'status' => 'boolean',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime',
        'applicable_vendors' => 'array',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'vendor_share_percentage' => 'decimal:2',
        'admin_share_percentage' => 'decimal:2',
        'show_on_listing' => 'boolean'
    ];

    // Aliases for code compatibility (accessors)
    protected $appends = ['is_active', 'starts_at', 'expires_at', 'minimum_order_amount', 'maximum_discount_amount'];

    // Alias for status
    public function getIsActiveAttribute()
    {
        return $this->status;
    }

    // Alias for start_date
    public function getStartsAtAttribute()
    {
        return $this->start_date;
    }

    // Alias for expiry_date
    public function getExpiresAtAttribute()
    {
        return $this->expiry_date;
    }

    // Alias for min_order_amount
    public function getMinimumOrderAmountAttribute()
    {
        return $this->min_order_amount;
    }

    // Alias for max_discount
    public function getMaximumDiscountAmountAttribute()
    {
        return $this->max_discount;
    }

    // Setter for status
    public function setIsActiveAttribute($value)
    {
        $this->attributes['status'] = $value;
    }

    // Setter for start_date
    public function setStartsAtAttribute($value)
    {
        $this->attributes['start_date'] = $value;
    }

    // Setter for expiry_date
    public function setExpiresAtAttribute($value)
    {
        $this->attributes['expiry_date'] = $value;
    }

    public function vendor() 
    { 
        return $this->belongsTo(Vendor::class); 
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function usageCount()
    {
        return $this->usages()->count();
    }

    public function totalSavings()
    {
        return $this->usages()->sum('discount_amount');
    }

    public function uniqueUsers()
    {
        return $this->usages()->distinct('user_id')->count('user_id');
    }
}