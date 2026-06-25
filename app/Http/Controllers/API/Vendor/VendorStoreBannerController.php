<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\VendorStoreBanner;

use App\Services\ImageUploadService;
class VendorStoreBannerController extends Controller
{
    public function store(Request $request)
{
    $vendor = auth()->user()->vendor;

    $request->validate([
        'title' => 'nullable|string|max:255',
        'image' => 'required|image|max:5120',
        'button_text' => 'nullable|string|max:50',
        'button_link' => 'nullable|string|max:255',
    ]);

    $path = ImageUploadService::uploadWebp(
        $request->file('image'),
        'vendors/store-banners',
        1920,
        90
    );

    $banner = VendorStoreBanner::create([
        'vendor_id' => $vendor->id,
        'title' => $request->title,
        'image' => $path,
        'button_text' => $request->button_text,
        'button_link' => $request->button_link,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Banner created successfully.',
        'data' => $banner
    ]);
}



public function index()
{
    $vendor = auth()->user()->vendor;

    return response()->json([
        'success' => true,
        'data' => $vendor->storeBanners()
            ->orderBy('sort_order')
            ->get()
    ]);
}

public function destroy($id)
{
    $vendor = auth()->user()->vendor;

    $banner = VendorStoreBanner::where(
        'vendor_id',
        $vendor->id
    )->findOrFail($id);

    ImageUploadService::deleteImage(
        $banner->image
    );

    $banner->delete();

    return response()->json([
        'success' => true,
        'message' => 'Banner deleted successfully.'
    ]);
}


}
