<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminBrandController extends Controller
{
    // ===============================
    // ✅ GET ALL (ADMIN)
    // ===============================
    public function index(Request $request)
    {
        $query = Brand::with([
            'subcategories:id,name,parent_id'
        ]);

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $query->latest()->paginate(10)
        );
    }

    // ===============================
    // ✅ GET ONLY ACTIVE (PUBLIC)
    // ===============================
    public function activeBrands()
    {
        return response()->json(
            Brand::where('status', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
        );
    }

    // ===============================
    // ✅ GET BRANDS BY CATEGORY (MAIN LOGIC)
    // ===============================
    public function getBrandsByCategory($categoryId)
{
    // Check if this category has children
    $childIds = Category::where('parent_id', $categoryId)->pluck('id');

    if ($childIds->count() > 0) {
        // 🔥 Parent category → use children
        $subcategoryIds = $childIds;
    } else {
        // 🔥 Subcategory → use itself
        $subcategoryIds = collect([$categoryId]);
    }

    $brands = Brand::whereHas('subcategories', function ($q) use ($subcategoryIds) {
        $q->whereIn('categories.id', $subcategoryIds);    })
    ->with('subcategories:id,name,parent_id')
    ->get();

    return response()->json([
        'data' => $brands
    ]);
}
    // ===============================
    // ✅ STORE
    // ===============================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'exists:categories,id'
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        // ================= FILE UPLOAD =================
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            $filename = time() . '_' . Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            ) . '.' . $file->getClientOriginalExtension();

            $destination = public_path('uploads/brands');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $filename);

            $validated['logo'] = 'uploads/brands/' . $filename;
        }

        // ================= CREATE =================
        $brand = Brand::create($validated);

        // ================= SYNC SUBCATEGORIES =================
        if ($request->subcategory_ids) {
            $brand->subcategories()->sync($request->subcategory_ids);
        }

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand->load('subcategories'),
            'logo_url' => $brand->logo ? asset($brand->logo) : null
        ], 201);
    }

    // ===============================
    // ✅ SHOW
    // ===============================
    public function show(Brand $brand)
    {
        return response()->json([
            'data' => $brand->load('subcategories'),
            'logo_url' => $brand->logo ? asset($brand->logo) : null
        ]);
    }

    // ===============================
    // ✅ UPDATE
    // ===============================
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug,' . $brand->id,
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'exists:categories,id'
        ]);

        if (isset($validated['name']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // ================= FILE UPDATE =================
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            $filename = time() . '_' . Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            ) . '.' . $file->getClientOriginalExtension();

            $destination = public_path('uploads/brands');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            // delete old logo
            if ($brand->logo && file_exists(public_path($brand->logo))) {
                unlink(public_path($brand->logo));
            }

            $file->move($destination, $filename);

            $validated['logo'] = 'uploads/brands/' . $filename;
        }

        $brand->update($validated);

        // ================= SYNC SUBCATEGORIES =================
        if ($request->has('subcategory_ids')) {
            $brand->subcategories()->sync($request->subcategory_ids);
        }

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $brand->load('subcategories'),
            'logo_url' => $brand->logo ? asset($brand->logo) : null
        ]);
    }

    // ===============================
    // ✅ DELETE
    // ===============================
    public function destroy(Brand $brand)
    {
        if ($brand->logo && file_exists(public_path($brand->logo))) {
            unlink(public_path($brand->logo));
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }
}