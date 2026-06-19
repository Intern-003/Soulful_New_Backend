<?php
// app/Services/CommissionService.php

// namespace App\Services;

// use App\Models\Vendor;
// use App\Models\OrderItem;

// class CommissionService
// {
//     /**
//      * Calculate commission for a vendor based on order total
//      */
//     public function calculateVendorCommission($amount, Vendor $vendor)
//     {
//         if ($vendor->commission_type === 'percentage') {
//             return ($vendor->commission_rate / 100) * $amount;
//         }
        
//         // Fixed commission
//         return min($vendor->commission_rate, $amount);
//     }
    
//     /**
//      * Calculate commission for a single order item
//      */
//     public function calculateItemCommission($sellingPrice, $quantity, Vendor $vendor)
//     {
//         $itemTotal = $sellingPrice * $quantity;
        
//         if ($vendor->commission_type === 'percentage') {
//             return ($vendor->commission_rate / 100) * $itemTotal;
//         }
        
//         // Fixed commission per item
//         return min($vendor->commission_rate, $itemTotal);
//     }
    
//     /**
//      * Calculate commission for product with potential override
//      */
//     public function calculateProductCommission($product, $quantity, Vendor $vendor)
//     {
//         $sellingPrice = $product->discount_price ?? $product->price;
//         $itemTotal = $sellingPrice * $quantity;
        
//         // Check if product has custom commission
//         if ($product->vendor_commission !== null) {
//             $rate = $product->vendor_commission;
//             $type = $product->commission_type ?? 'percentage';
            
//             if ($type === 'percentage') {
//                 return ($rate / 100) * $itemTotal;
//             }
//             return min($rate, $itemTotal);
//         }
        
//         // Use vendor default commission
//         return $this->calculateItemCommission($sellingPrice, $quantity, $vendor);
//     }
    
//     /**
//      * Get commission rate for vendor
//      */
//     public function getCommissionRate(Vendor $vendor)
//     {
//         return [
//             'type' => $vendor->commission_type ?? 'percentage',
//             'rate' => $vendor->commission_rate ?? 0
//         ];
//     }
// } 




// app/Services/CommissionService.php

namespace App\Services;

use App\Models\Vendor;
use App\Models\OrderItem;

class CommissionService
{
    /**
     * Calculate commission for a product (supports product-level override)
     */
    public function calculateProductCommission($product, $quantity, $vendor = null)
    {
        $sellingPrice = $product->discount_price ?? $product->price;
        $itemTotal = $sellingPrice * $quantity;
        
        // ✅ PRIORITY 1: Product-level commission override
        if ($product->vendor_commission !== null) {
            $rate = $product->vendor_commission;
            $type = $product->commission_type ?? 'percentage';
            
            if ($type === 'percentage') {
                return ($rate / 100) * $itemTotal;
            }
            return min($rate, $itemTotal);
        }
        
        // ✅ PRIORITY 2: Vendor-level commission
        if ($vendor && $vendor->commission_rate !== null) {
            if ($vendor->commission_type === 'percentage') {
                return ($vendor->commission_rate / 100) * $itemTotal;
            }
            return min($vendor->commission_rate, $itemTotal);
        }
        
        // ✅ PRIORITY 3: Default commission (Admin setting)
        $defaultRate = config('commission.default_rate', 10);
        $defaultType = config('commission.default_type', 'percentage');
        
        if ($defaultType === 'percentage') {
            return ($defaultRate / 100) * $itemTotal;
        }
        return min($defaultRate, $itemTotal);
    }
    
    /**
     * Calculate commission for order item (using product data)
     */
    public function calculateItemCommission($sellingPrice, $quantity, $vendor = null, $product = null)
    {
        $itemTotal = $sellingPrice * $quantity;
        
        // If product is provided, use product-level commission
        if ($product && $product->vendor_commission !== null) {
            $rate = $product->vendor_commission;
            $type = $product->commission_type ?? 'percentage';
            
            if ($type === 'percentage') {
                return ($rate / 100) * $itemTotal;
            }
            return min($rate, $itemTotal);
        }
        
        // Use vendor commission
        if ($vendor && $vendor->commission_rate !== null) {
            if ($vendor->commission_type === 'percentage') {
                return ($vendor->commission_rate / 100) * $itemTotal;
            }
            return min($vendor->commission_rate, $itemTotal);
        }
        
        // Default commission
        $defaultRate = 10;
        return ($defaultRate / 100) * $itemTotal;
    }
    
    /**
     * Get commission rate for a product (with fallback)
     */
    public function getProductCommissionRate($product, $vendor = null)
    {
        if ($product->vendor_commission !== null) {
            return [
                'type' => $product->commission_type ?? 'percentage',
                'rate' => $product->vendor_commission
            ];
        }
        
        if ($vendor && $vendor->commission_rate !== null) {
            return [
                'type' => $vendor->commission_type ?? 'percentage',
                'rate' => $vendor->commission_rate
            ];
        }
        
        return [
            'type' => 'percentage',
            'rate' => 10
        ];
    }
}