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
    // ----------------------------
    public function getBanners()
    {
        $banners = Banner::with([
            'products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price')
                  ->with(['primaryImage:id,product_id,image_url']);
            }
        ])
        ->orderBy('position', 'asc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    // ----------------------------
    // Get Single Banner
    // ----------------------------
    public function getBanner($id)
    {
        $banner = Banner::with([
            'products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price')
                  ->with(['primaryImage:id,product_id,image_url']);
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
            'data' => $banner
        ]);
    }

    // ----------------------------
    // Create Banner
    // ----------------------------
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'layout' => 'nullable|in:grid,highlight,carousel',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean',

            'products' => 'nullable|array',
            'products.*' => 'exists:products,id'
        ]);

        $banner = Banner::create([
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image' => $request->image,
            'link' => $request->link,
            'layout' => $request->layout ?? 'grid',
            'position' => $request->position ?? 1,
            'status' => $request->status ?? true
        ]);

        // attach products
        if ($request->has('products')) {
            $syncData = [];

            foreach ($request->products as $index => $productId) {
                $syncData[$productId] = ['position' => $index + 1];
            }

            $banner->products()->sync($syncData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => $banner->load([
                'products' => function ($q) {
                    $q->select('products.id', 'products.name', 'products.price')
                      ->with(['primaryImage:id,product_id,image_url']);
                }
            ])
        ], 201);
    }

    // ----------------------------
    // Update Banner
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
            'image' => 'nullable|string',
            'link' => 'nullable|string',
            'layout' => 'nullable|in:grid,highlight,carousel',
            'position' => 'nullable|integer',
            'status' => 'sometimes|boolean',

            'products' => 'nullable|array',
            'products.*' => 'exists:products,id'
        ]);

        $data = $request->only([
            'title',
            'subtitle',
            'link',
            'layout',
            'position',
            'status'
        ]);

        // image update
        if ($request->has('image')) {

            if ($banner->image) {
                $oldPath = str_replace(url('/storage/'), 'public/', $banner->image);

                if (Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            }

            $data['image'] = $request->image;
        }

        $banner->update($data);

        // sync products
        if ($request->has('products')) {
            $syncData = [];

            foreach ($request->products as $index => $productId) {
                $syncData[$productId] = ['position' => $index + 1];
            }

            $banner->products()->sync($syncData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => $banner->load([
                'products' => function ($q) {
                    $q->select('products.id', 'products.name', 'products.price')
                      ->with(['primaryImage:id,product_id,image_url']);
                }
            ])
        ]);
    }

    // ----------------------------
    // Delete Banner
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
}