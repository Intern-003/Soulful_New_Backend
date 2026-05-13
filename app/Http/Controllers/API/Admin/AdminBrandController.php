<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\ImageUploadService;

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
// ===============================
// ✅ STORE
// ===============================
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255|unique:brands,name',
        'slug' => 'nullable|string|unique:brands,slug',
        'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'status' => 'nullable|boolean',
        'subcategory_ids' => 'nullable|array',
        'subcategory_ids.*' => 'exists:categories,id'
    ]);

    // Generate slug if not provided
    $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

    // ================= WEBP LOGO UPLOAD =================
    if ($request->hasFile('logo')) {

        $validated['logo'] = ImageUploadService::uploadWebp(
            $request->file('logo'),
            'brands',
            600,
            80
        );
    }

    // ================= CREATE =================
    $brand = Brand::create($validated);

    // ================= SYNC SUBCATEGORIES =================
    if ($request->filled('subcategory_ids')) {

        $brand->subcategories()->sync($request->subcategory_ids);
    }

    return response()->json([
        'success' => true,
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
 // ===============================
// ✅ UPDATE
// ===============================
public function update(Request $request, Brand $brand)
{
    $validated = $request->validate([
        'name' => 'sometimes|required|string|max:255|unique:brands,name,' . $brand->id,
        'slug' => 'nullable|string|unique:brands,slug,' . $brand->id,
        'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'status' => 'nullable|boolean',
        'subcategory_ids' => 'nullable|array',
        'subcategory_ids.*' => 'exists:categories,id'
    ]);

    // Generate slug if name updated but slug not sent
    if (isset($validated['name']) && !isset($validated['slug'])) {

        $validated['slug'] = Str::slug($validated['name']);
    }

    // ================= WEBP LOGO UPDATE =================
    if ($request->hasFile('logo')) {

        // Delete old logo
        if ($brand->logo) {

            ImageUploadService::deleteImage($brand->logo);
        }

        // Upload new logo
        $validated['logo'] = ImageUploadService::uploadWebp(
            $request->file('logo'),
            'brands',
            600,
            80
        );
    }

    // ================= UPDATE =================
    $brand->update($validated);

    // ================= SYNC SUBCATEGORIES =================
    if ($request->has('subcategory_ids')) {

        $brand->subcategories()->sync($request->subcategory_ids);
    }

    return response()->json([
        'success' => true,
        'message' => 'Brand updated successfully',
        'data' => $brand->load('subcategories'),
        'logo_url' => $brand->logo ? asset($brand->logo) : null
    ]);
}

    // ===============================
    // ✅ DELETE
    // ===============================
// ===============================
// ✅ DELETE
// ===============================
public function destroy(Brand $brand)
{
    // Delete logo if exists
    if ($brand->logo) {

        ImageUploadService::deleteImage($brand->logo);
    }

    // Detach subcategories
    $brand->subcategories()->detach();

    // Delete brand
    $brand->delete();

    return response()->json([
        'success' => true,
        'message' => 'Brand deleted successfully'
    ]);
}
}