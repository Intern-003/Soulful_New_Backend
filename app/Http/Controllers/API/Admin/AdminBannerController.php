<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class AdminBannerController extends Controller
{

    // ============================
    // GET ACTIVE BANNERS
    // ============================
    public function getBanners()
    {
        $now = Carbon::now();

        $banners = Banner::with([
            'products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price')
                    // ->with(['primaryImage:id,product_id,image_url']);
                    ->with(['images:id,product_id,image_url,is_primary']);
            }
        ])
            ->where('status', true)
            ->orderBy('position', 'asc')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    // ============================
    // GET SINGLE
    // ============================
    public function getBanner($id)
    {

        //$banner = Banner::with(['products:id,name,price'])->find($id);
        $banner = Banner::with([
            'products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price')
                    ->with(['images:id,product_id,image_url,is_primary']);
            }
        ])->find($id);
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
                'description' => $banner->description,
                'image' => $banner->image,
                'layout' => $banner->layout,
                'position' => $banner->position,
                'status' => $banner->status,
                'products' => $banner->products,
            ]
        ]);

    }

    // ============================
    // CREATE
    // ============================
public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'subtitle' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'link' => 'nullable|string',
        'layout' => 'nullable|in:grid,highlight,carousel',
        'position' => 'nullable|integer',
        'status' => 'nullable|boolean',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'product_ids' => 'nullable|array',
        'product_ids.*' => 'exists:products,id'
    ]);

    $imagePath = null;

    // ✅ Upload WebP Banner
    if ($request->hasFile('image')) {

        $imagePath = ImageUploadService::uploadWebp(
            $request->file('image'),
            'banners',
            1920,
            85
        );
    }

    $banner = Banner::create([
        'title' => $request->title,
        'subtitle' => $request->subtitle,
        'description' => $request->description,
        'image' => $imagePath,
        'link' => $request->link,
        'layout' => $request->layout ?? 'grid',
        'position' => $request->position ?? 1,
        'status' => $request->status ?? true,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
    ]);

    // Sync Products
    if (!empty($request->product_ids)) {

        $syncData = collect($request->product_ids)
            ->values()
            ->mapWithKeys(fn($id, $i) => [
                $id => ['position' => $i + 1]
            ])
            ->toArray();

        $banner->products()->sync($syncData);
    }

    return response()->json([
        'success' => true,
        'message' => 'Banner created successfully',
        'data' => $banner
    ], 201);
}

    // ============================
    // UPDATE
    // ============================
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
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'link' => 'nullable|string',
        'position' => 'nullable|integer',
        'status' => 'sometimes|boolean',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
    ]);

    $data = $request->only([
        'title',
        'subtitle',
        'description',
        'layout',
        'link',
        'position',
        'status',
        'start_date',
        'end_date'
    ]);

    // ✅ Update Image
    if ($request->hasFile('image')) {

        // Delete old image
        if ($banner->image) {
            ImageUploadService::deleteImage($banner->image);
        }

        // Upload new image
        $data['image'] = ImageUploadService::uploadWebp(
            $request->file('image'),
            'banners',
            1920,
            85
        );
    }

    $banner->update($data);

    // Sync Products
    if ($request->has('product_ids')) {

        $syncData = collect($request->product_ids ?? [])
            ->values()
            ->mapWithKeys(fn($id, $i) => [
                $id => ['position' => $i + 1]
            ])
            ->toArray();

        $banner->products()->sync($syncData);
    }

    return response()->json([
        'success' => true,
        'message' => 'Banner updated successfully',
        'data' => $banner
    ]);
}

    // ============================
    // DELETE
    // ============================
public function deleteBanner($id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found'
        ], 404);
    }

    // ✅ Delete Banner Image
    if ($banner->image) {
        ImageUploadService::deleteImage($banner->image);
    }

    // Detach Products
    $banner->products()->detach();

    // Delete Banner
    $banner->delete();

    return response()->json([
        'success' => true,
        'message' => 'Banner deleted successfully'
    ]);
}
}