<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use App\Models\Banner;
use Carbon\Carbon;

class AdminBannerController extends Controller
{
    // ============================
    // ADMIN GET ALL BANNERS (WITH SERVER-SIDE FILTERING & PAGINATION)
    // ============================
    public function getBanners(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        $query = Banner::with([
            'products' => function ($q) {
                $q->select(
                    'products.id',
                    'products.name',
                    'products.price',
                    'products.status'
                )->with([
                    'images' => function ($img) {
                        $img->select(
                            'id',
                            'product_id',
                            'image_url',
                            'is_primary'
                        );
                    }
                ]);
            }
        ]);
        
        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('subtitle', 'LIKE', "%{$search}%");
            });
        }
        
        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }
        
        // Layout filter
        if ($request->has('layout') && $request->layout !== 'all') {
            $query->where('layout', $request->layout);
        }
        
        // Sorting
        $sortField = 'position';
        $sortDirection = 'asc';
        
        if ($request->has('sort_by')) {
            switch ($request->sort_by) {
                case 'position_asc':
                    $sortField = 'position';
                    $sortDirection = 'asc';
                    break;
                case 'position_desc':
                    $sortField = 'position';
                    $sortDirection = 'desc';
                    break;
                case 'title_asc':
                    $sortField = 'title';
                    $sortDirection = 'asc';
                    break;
                case 'title_desc':
                    $sortField = 'title';
                    $sortDirection = 'desc';
                    break;
            }
        }
        
        $query->orderBy($sortField, $sortDirection);
        
        $banners = $query->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'success' => true,
            'data' => $banners->items(),
            'current_page' => $banners->currentPage(),
            'last_page' => $banners->lastPage(),
            'total' => $banners->total(),
            'per_page' => $banners->perPage(),
        ]);
    }
    
    // ============================
    // GET SINGLE - FIXED
    // ============================
    public function getBanner($id)
    {
        $banner = Banner::with([
            'products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price', 'products.status')
                    ->with([
                        'images' => function ($img) {
                            $img->select('id', 'product_id', 'image_url', 'is_primary');
                        }
                    ]);
            }
        ])->find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        // Format dates for frontend
        $startDate = $banner->start_date ? Carbon::parse($banner->start_date)->format('Y-m-d') : null;
        $endDate = $banner->end_date ? Carbon::parse($banner->end_date)->format('Y-m-d') : null;

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
                'status' => (bool) $banner->status,
                'button_text' => $banner->button_text,
                'button_link' => $banner->button_link,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'products' => $banner->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'images' => $product->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'image_url' => $image->image_url,
                                'is_primary' => $image->is_primary
                            ];
                        })
                    ];
                }),
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
            'button_text' => 'nullable|string|max:255',
            'button_link' => 'nullable|string|max:255',
            'layout' => 'nullable|in:hero,grid,products,split,slider',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $imagePath = null;

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
            'button_text' => $request->button_text,
            'button_link' => $request->button_link,
            'layout' => $request->layout ?? 'hero',
            'position' => $request->position ?? 1,
            'status' => $request->status ?? true,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if (!empty($request->product_ids)) {
            $syncData = collect($request->product_ids)
                ->values()
                ->mapWithKeys(fn($id, $i) => [$id => ['position' => $i + 1]])
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
            'button_text' => 'nullable|string|max:255',
            'button_link' => 'nullable|string|max:255',
            'position' => 'nullable|integer',
            'status' => 'sometimes|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = $request->only([
            'title',
            'subtitle',
            'description',
            'button_text',
            'button_link',
            'layout',
            'position',
            'status',
            'start_date',
            'end_date'
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image) {
                ImageUploadService::deleteImage($banner->image);
            }
            $data['image'] = ImageUploadService::uploadWebp(
                $request->file('image'),
                'banners',
                1920,
                85
            );
        }

        $banner->update($data);

        if ($request->has('product_ids')) {
            $syncData = collect($request->product_ids ?? [])
                ->values()
                ->mapWithKeys(fn($id, $i) => [$id => ['position' => $i + 1]])
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

        if ($banner->image) {
            ImageUploadService::deleteImage($banner->image);
        }

        $banner->products()->detach();
        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }
}