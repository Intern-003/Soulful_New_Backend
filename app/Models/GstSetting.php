<?php
// app/Models/GstSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GstSetting extends Model
{
    protected $table = 'gst_settings';

    protected $fillable = [
        'vendor_id',
        'gst_number',
        'pan_number',
        'is_gst_registered',
        'gst_type',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'cess_rate',
        'is_interstate_applicable',
        'hsn_codes'
    ];

    protected $casts = [
        'is_gst_registered' => 'boolean',
        'is_interstate_applicable' => 'boolean',
        'cgst_rate' => 'decimal:2',
        'sgst_rate' => 'decimal:2',
        'igst_rate' => 'decimal:2',
        'cess_rate' => 'decimal:2',
        'hsn_codes' => 'array'
    ];

    /**
     * Get the vendor associated with GST settings
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Check if vendor is GST registered
     */
    public function isGstRegistered()
    {
        return $this->is_gst_registered;
    }

    /**
     * Calculate GST for a given amount
     */
    public function calculateGst($amount, $isInterstate = false)
    {
        if ($isInterstate) {
            $igstAmount = ($this->igst_rate / 100) * $amount;
            return [
                'total_tax' => $igstAmount,
                'breakdown' => [
                    'igst' => [
                        'rate' => $this->igst_rate,
                        'amount' => $igstAmount
                    ]
                ]
            ];
        } else {
            $cgstAmount = ($this->cgst_rate / 100) * $amount;
            $sgstAmount = ($this->sgst_rate / 100) * $amount;
            return [
                'total_tax' => $cgstAmount + $sgstAmount,
                'breakdown' => [
                    'cgst' => [
                        'rate' => $this->cgst_rate,
                        'amount' => $cgstAmount
                    ],
                    'sgst' => [
                        'rate' => $this->sgst_rate,
                        'amount' => $sgstAmount
                    ]
                ]
            ];
        }
    }

    /**
     * Get HSN code for a product category
     */
    public function getHsnCode($categoryId)
    {
        if ($this->hsn_codes && isset($this->hsn_codes[$categoryId])) {
            return $this->hsn_codes[$categoryId];
        }
        return null;
    }

    /**
     * Get formatted GST number
     */
    public function getFormattedGstNumberAttribute()
    {
        if (!$this->gst_number) {
            return null;
        }
        // Format: 22AAAAA0000A1Z
        return $this->gst_number;
    }

    /**
     * Scope for global GST settings (admin default)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('vendor_id');
    }

    /**
     * Scope for vendor-specific GST settings
     */
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}