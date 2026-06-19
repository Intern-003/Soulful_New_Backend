<?php
// app/Models/CommissionLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLog extends Model
{
    protected $table = 'commission_logs';

    protected $fillable = [
        'vendor_id',
        'old_rate',
        'new_rate',
        'old_type',
        'new_type',
        'changed_by',
        'reason'
    ];

    protected $casts = [
        'old_rate' => 'decimal:2',
        'new_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the vendor whose commission was changed
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the admin who changed the commission
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get formatted old rate
     */
    public function getFormattedOldRateAttribute()
    {
        if ($this->old_type === 'percentage') {
            return $this->old_rate . '%';
        }
        return '₹' . number_format($this->old_rate, 2);
    }

    /**
     * Get formatted new rate
     */
    public function getFormattedNewRateAttribute()
    {
        if ($this->new_type === 'percentage') {
            return $this->new_rate . '%';
        }
        return '₹' . number_format($this->new_rate, 2);
    }

    /**
     * Get change direction (increase/decrease)
     */
    public function getChangeDirectionAttribute()
    {
        if ($this->old_rate < $this->new_rate) {
            return 'increase';
        } elseif ($this->old_rate > $this->new_rate) {
            return 'decrease';
        }
        return 'no_change';
    }

    /**
     * Get change amount
     */
    public function getChangeAmountAttribute()
    {
        return abs($this->new_rate - $this->old_rate);
    }

    /**
     * Scope for a specific vendor
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}