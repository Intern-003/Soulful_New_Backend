<?php
// app/Models/Settlement.php - FIXED VERSION (without SoftDeletes)

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    // ❌ NO SoftDeletes trait

    protected $table = 'settlements';

    protected $fillable = [
        'settlement_number',
        'vendor_id',
        'period_start',
        'period_end',
        'total_sales',
        'total_commission',
        'total_tax',
        'total_shipping',
        'total_adjustments',
        'settlement_amount',
        'status',
        'payment_method',
        'transaction_id',
        'notes',
        'breakdown',
        'processed_at',
        'processed_by'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_sales' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_shipping' => 'decimal:2',
        'total_adjustments' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'breakdown' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function orderItems()
    {
        if ($this->breakdown && isset($this->breakdown['items'])) {
            $itemIds = collect($this->breakdown['items'])->pluck('id')->toArray();
            return OrderItem::whereIn('id', $itemIds)->get();
        }
        return collect();
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function getFormattedSettlementAmountAttribute()
    {
        return '₹' . number_format($this->settlement_amount, 2);
    }

    public function getPeriodRangeAttribute()
    {
        return $this->period_start->format('d M Y') . ' - ' . $this->period_end->format('d M Y');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}