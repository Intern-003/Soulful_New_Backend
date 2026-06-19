<?php

namespace App\Helpers;

class VendorHelper
{
    /**
     * Generate a vendor ID for individual creators
     * Format: creator_{user_id}
     */
    public static function getVendorIdForCreator($creatorId)
    {
        return 'creator_' . $creatorId;
    }

    /**
     * Check if a vendor ID is for an individual creator
     */
    public static function isCreatorVendor($vendorId)
    {
        return is_string($vendorId) && strpos($vendorId, 'creator_') === 0;
    }

    /**
     * Extract creator ID from vendor ID
     */
    public static function getCreatorIdFromVendorId($vendorId)
    {
        if (self::isCreatorVendor($vendorId)) {
            return (int) str_replace('creator_', '', $vendorId);
        }
        return null;
    }

    /**
     * Get display name for vendor
     */
    public static function getVendorDisplayName($vendorId, $vendor = null)
    {
        if (self::isCreatorVendor($vendorId)) {
            $creatorId = self::getCreatorIdFromVendorId($vendorId);
            return "Individual Seller #{$creatorId}";
        }
        
        if ($vendor && $vendor->store_name) {
            return $vendor->store_name;
        }
        
        return "Vendor #{$vendorId}";
    }
}