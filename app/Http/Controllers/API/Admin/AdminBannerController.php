<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{

 // ----------------------------
 // Get All Banners
 // GET /admin/banners
 // ----------------------------
public function getBanners()
{
    $banners = Banner::orderBy('position', 'asc')->get();

    return response()->json([
        'success' => true,
        'data' => $banners
    ]);
}
 // ----------------------------
 // Get Single Banner
 // GET /admin/banners/{id}
 // ----------------------------
public function getBanner($id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $banner
    ]);
}

    // POST /admin/banners
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean'
        ]);

        $banner = Banner::create([
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image' => $request->image,
            'link' => $request->link,
            'position' => $request->position ?? 1,
            'status' => $request->status ?? true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => $banner
        ],201);
    }

    public function deleteBanner($id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found'
        ], 404);
    }

    // ✅ Delete image from storage
    if ($banner->image) {
        $path = str_replace(url('/storage/'), 'public/', $banner->image);

        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    $banner->delete();

    return response()->json([
        'success' => true,
        'message' => 'Banner deleted successfully'
    ]);
}   

public function updateBanner(Request $request, $id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found'
        ], 404);
    }

    $request->validate([
        'title' => 'sometimes|string|max:255',
        'subtitle' => 'nullable|string|max:255',
        'image' => 'nullable|string',
        'link' => 'nullable|string',
        'position' => 'nullable|integer',
        'status' => 'sometimes|boolean'
    ]);

    $data = $request->only([
        'title',
        'subtitle',
        'link',
        'position',
        'status'
    ]);

    // ✅ Handle image update
    if ($request->has('image')) {

        // delete old image
        if ($banner->image) {
            $oldPath = str_replace(url('/storage/'), 'public/', $banner->image);

            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $data['image'] = $request->image;
    }

    $banner->update($data);

    return response()->json([
        'success' => true,
        'message' => 'Banner updated successfully',
        'data' => $banner
    ]);
}


}