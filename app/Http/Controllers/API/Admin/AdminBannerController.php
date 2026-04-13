<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBannerController extends Controller
{
    // ----------------------------
    // GET ALL BANNERS
    // ----------------------------
    public function getBanners()
    {
        $banners = Banner::orderBy('position', 'asc')->get()->map(function ($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'link' => $banner->link,
                'position' => $banner->position,
                'status' => $banner->status,
                'image_url' => $banner->image
                    ? asset('storage/' . $banner->image)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    // ----------------------------
    // GET SINGLE BANNER
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
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'link' => $banner->link,
                'position' => $banner->position,
                'status' => $banner->status,
                'image_url' => $banner->image
                    ? asset('storage/' . $banner->image)
                    : null,
            ]
        ]);
    }

    // ----------------------------
    // STORE BANNER (WITH IMAGE UPLOAD)
    // ----------------------------
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'link' => 'nullable|string',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create([
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'link' => $request->link,
            'position' => $request->position ?? 1,
            'status' => $request->status ?? true,
            'image' => $imagePath
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'link' => $banner->link,
                'position' => $banner->position,
                'status' => $banner->status,
                'image_url' => $imagePath ? asset('storage/' . $imagePath) : null,
            ]
        ], 201);
    }

    // ----------------------------
    // UPDATE BANNER (WITH IMAGE REPLACE)
    // ----------------------------
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
            'link' => 'nullable|string',
            'position' => 'nullable|integer',
            'status' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $data = $request->only([
            'title',
            'subtitle',
            'link',
            'position',
            'status'
        ]);

        // ----------------------------
        // IMAGE UPDATE LOGIC
        // ----------------------------
        if ($request->hasFile('image')) {

            // delete old image safely
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            // store new image
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'link' => $banner->link,
                'position' => $banner->position,
                'status' => $banner->status,
                'image_url' => $banner->image
                    ? asset('storage/' . $banner->image)
                    : null,
            ]
        ]);
    }

    // ----------------------------
    // DELETE BANNER
    // ----------------------------
    public function deleteBanner($id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        // delete image from storage
        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }
}