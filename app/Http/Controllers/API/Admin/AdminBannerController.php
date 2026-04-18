<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
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

        //dd($request->all());
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'link' => 'nullable|string',
            'layout' => 'nullable|in:grid,highlight,carousel',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            // 'products' => 'nullable|array',
            // 'products.*' => 'exists:products,id'
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id'

        ]);

        $imagePath = null;

        // ✅ STORE IMAGE IN public/uploads/banners
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $destinationPath = public_path('uploads/banners');

            // create folder if not exists
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $fileName);

            $imagePath = 'uploads/banners/' . $fileName;
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

        // sync products
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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
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

        // ✅ UPDATE IMAGE
        if ($request->hasFile('image')) {

            // delete old image
            if ($banner->image && file_exists(public_path($banner->image))) {
                unlink(public_path($banner->image));
            }

            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $destinationPath = public_path('uploads/banners');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $fileName);

            $data['image'] = 'uploads/banners/' . $fileName;
        }

        $banner->update($data);

        // sync products
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

        if ($banner->image && file_exists(public_path($banner->image))) {
            unlink(public_path($banner->image));
        }


        $banner->products()->detach(); // ✅ ADD THIS
        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }
}