<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorFollower;

class VendorFollowController extends Controller
{
    public function follow($slug)
{
    $vendor = Vendor::where(
        'store_slug',
        $slug
    )->firstOrFail();

    VendorFollower::firstOrCreate([
        'vendor_id' => $vendor->id,
        'user_id' => auth()->id()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Store followed successfully.'
    ]);
}

public function unfollow($slug)
{
    $vendor = Vendor::where(
        'store_slug',
        $slug
    )->firstOrFail();

    VendorFollower::where([
        'vendor_id' => $vendor->id,
        'user_id' => auth()->id()
    ])->delete();

    return response()->json([
        'success' => true,
        'message' => 'Store unfollowed.'
    ]);
}

public function followers($slug)
{
    $vendor = Vendor::where(
        'store_slug',
        $slug
    )->firstOrFail();

    return response()->json([
        'success' => true,
        'count' => $vendor
            ->followers()
            ->count()
    ]);
}
}
