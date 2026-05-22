<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\DB;

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
    // ✅ GET BRANDS BY CATEGORY (MAIN LOGIC WITH NESTED SUPPORT)
    // ===============================
    // public function getBrandsByCategory($categoryId)
    // {
    //     // Get all descendant category IDs (including the selected category and all nested children)
    //     $categoryIds = $this->getAllDescendantCategoryIds($categoryId);
        
    //     $brands = Brand::whereHas('subcategories', function ($q) use ($categoryIds) {
    //         $q->whereIn('categories.id', $categoryIds);
    //     })
    //         ->with('subcategories:id,name,parent_id')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $brands
    //     ]);
    // }
 public function getBrandsByCategory($categoryId)
{
    $subcategoryIds = Category::where('parent_id', $categoryId)
        ->pluck('id');

    $brands = Brand::whereHas('subcategories', function ($q) use ($subcategoryIds) {
            $q->whereIn('categories.id', $subcategoryIds);
        })
        ->with('subcategories:id,name,parent_id')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $brands
    ]);
}
    // ===============================
    // ✅ HELPER: GET ALL DESCENDANT CATEGORY IDS (RECURSIVE)
    // ===============================
    private function getAllDescendantCategoryIds($categoryId)
    {
        $ids = collect([$categoryId]);
        
        // Get immediate children
        $children = Category::where('parent_id', $categoryId)->get();
        
        foreach ($children as $child) {
            // Recursively get all descendants
            $ids = $ids->merge($this->getAllDescendantCategoryIds($child->id));
        }
        
        return $ids->unique()->values()->toArray();
    }
    
    // ===============================
    // ✅ GET NESTED CATEGORIES TREE (OPTIONAL)
    // ===============================
    public function getNestedCategories()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        
        return response()->json([
            'success' => true,
            'data' => $this->formatCategoryTree($categories)
        ]);
    }
    
    // ===============================
    // ✅ HELPER: FORMAT CATEGORY TREE
    // ===============================
    private function formatCategoryTree($categories)
    {
        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'children' => $this->formatCategoryTree($category->children)
            ];
        });
    }

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

public function products(Brand $brand)
{
    $products = $brand
        ->products()
        ->with([
            'images:id,product_id,image_url,is_primary'
        ])
        ->latest('id')
        ->paginate(20);

    return response()->json([
        'success' => true,
        'brand' => [
            'id' => $brand->id,
            'name' => $brand->name,
            'logo' => $brand->logo,
        ],
        'data' => $products
    ]);
}


}