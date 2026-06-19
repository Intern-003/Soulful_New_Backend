<?php
// app/Services/TaxService.php
namespace App\Services;

use App\Models\GstSetting;
use App\Models\Vendor;

class TaxService
{
    /**
     * Calculate tax for vendor based on GST rules
     */
    public function calculateVendorTax($amount, Vendor $vendor, $isInterstate = false)
    {
        $gstSetting = $vendor->gstSetting ?? GstSetting::whereNull('vendor_id')->first();
        
        if (!$gstSetting) {
            return [
                'total_tax' => 0,
                'breakdown' => []
            ];
        }
        
        if ($isInterstate) {
            // Interstate transaction - IGST applies
            $igstRate = $gstSetting->igst_rate;
            $igstAmount = ($igstRate / 100) * $amount;
            
            return [
                'total_tax' => $igstAmount,
                'breakdown' => [
                    'igst' => [
                        'rate' => $igstRate,
                        'amount' => $igstAmount
                    ]
                ]
            ];
        } else {
            // Intrastate - CGST + SGST
            $cgstRate = $gstSetting->cgst_rate;
            $sgstRate = $gstSetting->sgst_rate;
            $cgstAmount = ($cgstRate / 100) * $amount;
            $sgstAmount = ($sgstRate / 100) * $amount;
            
            return [
                'total_tax' => $cgstAmount + $sgstAmount,
                'breakdown' => [
                    'cgst' => [
                        'rate' => $cgstRate,
                        'amount' => $cgstAmount
                    ],
                    'sgst' => [
                        'rate' => $sgstRate,
                        'amount' => $sgstAmount
                    ]
                ]
            ];
        }
    }
    
    /**
     * Calculate tax for single product
     */
    public function calculateProductTax($price, $taxRate)
    {
        $taxAmount = ($taxRate / 100) * $price;
        
        return [
            'total_tax' => $taxAmount,
            'rate' => $taxRate,
            'breakdown' => [
                'cgst' => [
                    'rate' => $taxRate / 2,
                    'amount' => ($taxRate / 2 / 100) * $price
                ],
                'sgst' => [
                    'rate' => $taxRate / 2,
                    'amount' => ($taxRate / 2 / 100) * $price
                ]
            ]
        ];
    }
}