<?php
// app/Models/EarlySettlementRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EarlySettlementRequest extends Model
{
    protected $table = 'early_settlement_requests';

    protected $fillable = [
        'vendor_id',
        'order_item_ids',
        'total_amount',
        'reason',
        'status',
        'requested_at',
        'processed_at',
        'processed_by',
        'admin_notes'
    ];

    protected $casts = [
        'order_item_ids' => 'array',
        'total_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the vendor who requested early settlement
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the admin who processed the request
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the order items in this request
     */
    public function orderItems()
    {
        return OrderItem::whereIn('id', $this->order_item_ids ?? [])->get();
    }

    /**
     * Check if request is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if request is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Approve the early settlement request
     */
    public function approve($adminId, $notes = null)
    {
        $this->update([
            'status' => 'approved',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'admin_notes' => $notes
        ]);
    }

    /**
     * Reject the early settlement request
     */
    public function reject($adminId, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'admin_notes' => $notes
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}