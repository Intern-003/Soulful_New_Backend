<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Services\ImageUploadService;

class AdminCategoryController extends Controller
{

    // ✅ COMMON SLUG GENERATOR (REUSABLE)
    private function generateUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // POST /admin/categories
// POST /admin/categories
public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255|unique:categories,name',
        'parent_id' => 'nullable|exists:categories,id',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'position' => 'nullable|integer'
    ]);

    // Generate Unique Slug
    $slug = $this->generateUniqueSlug($request->name);

    // ================= WEBP IMAGE UPLOAD =================
    $imagePath = null;

    if ($request->hasFile('image')) {

        $imagePath = ImageUploadService::uploadWebp(
            $request->file('image'),
            'categories',
            800,
            80
        );
    }

    $category = Category::create([
        'parent_id' => $request->parent_id,
        'name' => $request->name,
        'slug' => $slug,
        'description' => $request->description,
        'image' => $imagePath,
        'position' => $request->position,
        'status' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Category created successfully',
        'data' => $category
    ]);
}

    // POST /admin/subcategories
// POST /admin/subcategories
public function storeSubcategory(Request $request)
{
    $request->validate([
        'parent_id' => 'required|exists:categories,id',
        'name' => 'required|string|max:255|unique:categories,name',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'
    ]);

    // Generate Unique Slug
    $slug = $this->generateUniqueSlug($request->name);

    // ================= WEBP IMAGE UPLOAD =================
    $imagePath = null;

    if ($request->hasFile('image')) {

        $imagePath = ImageUploadService::uploadWebp(
            $request->file('image'),
            'categories',
            800,
            80
        );
    }

    $subcategory = Category::create([
        'parent_id' => $request->parent_id,
        'name' => $request->name,
        'slug' => $slug,
        'description' => $request->description,
        'image' => $imagePath,
        'status' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Subcategory created successfully',
        'data' => $subcategory
    ]);
}

public function deleteSubcategory($id)
{
    $subcategory = Category::find($id);

    if (!$subcategory) {
        return response()->json([
            'success' => false,
            'message' => 'Subcategory not found'
        ], 404);
    }

    if (is_null($subcategory->parent_id)) {
        return response()->json([
            'success' => false,
            'message' => 'This is not a subcategory'
        ], 400);
    }

    // Prevent delete if products exist
    if (Product::where('category_id', $id)->exists()) {

        return response()->json([
            'success' => false,
            'message' => 'Subcategory has products. Cannot delete.'
        ], 400);
    }

    // Delete image
    if ($subcategory->image) {

        ImageUploadService::deleteImage($subcategory->image);
    }

    $subcategory->delete();

    return response()->json([
        'success' => true,
        'message' => 'Subcategory deleted successfully'
    ]);
}

public function updateCategory(Request $request, $id)
{
    $category = Category::find($id);

    if (!$category) {
        return response()->json([
            'success' => false,
            'message' => 'Category not found'
        ], 404);
    }

    $request->validate([
        'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
        'parent_id' => 'nullable|exists:categories,id',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'position' => 'nullable|integer',
        'status' => 'nullable|boolean'
    ]);

    // Update slug if name changed
    if ($request->has('name') && $request->name != $category->name) {

        $category->slug = $this->generateUniqueSlug($request->name, $id);
        $category->name = $request->name;
    }

    if ($request->has('parent_id')) {
        $category->parent_id = $request->parent_id;
    }

    if ($request->has('description')) {
        $category->description = $request->description;
    }

    // ================= WEBP IMAGE UPDATE =================
    if ($request->hasFile('image')) {

        // Delete old image
        if ($category->image) {

            ImageUploadService::deleteImage($category->image);
        }

        // Upload new image
        $category->image = ImageUploadService::uploadWebp(
            $request->file('image'),
            'categories',
            800,
            80
        );
    }

    if ($request->has('position')) {
        $category->position = $request->position;
    }

    if ($request->has('status')) {
        $category->status = $request->status;
    }

    $category->save();

    return response()->json([
        'success' => true,
        'message' => 'Category updated successfully',
        'data' => $category
    ]);
}

public function updateSubcategory(Request $request, $id)
{
    $subcategory = Category::find($id);

    if (!$subcategory) {
        return response()->json([
            'success' => false,
            'message' => 'Subcategory not found'
        ], 404);
    }

    if (is_null($subcategory->parent_id)) {
        return response()->json([
            'success' => false,
            'message' => 'This is not a subcategory'
        ], 400);
    }

    $request->validate([
        'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
        'parent_id' => 'sometimes|exists:categories,id',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        'status' => 'nullable|boolean'
    ]);

    // Update slug if name changed
    if ($request->has('name') && $request->name != $subcategory->name) {

        $subcategory->slug = $this->generateUniqueSlug($request->name, $id);
        $subcategory->name = $request->name;
    }

    if ($request->has('parent_id')) {
        $subcategory->parent_id = $request->parent_id;
    }

    if ($request->has('description')) {
        $subcategory->description = $request->description;
    }

    // ================= WEBP IMAGE UPDATE =================
    if ($request->hasFile('image')) {

        // Delete old image
        if ($subcategory->image) {

            ImageUploadService::deleteImage($subcategory->image);
        }

        // Upload new image
        $subcategory->image = ImageUploadService::uploadWebp(
            $request->file('image'),
            'categories',
            800,
            80
        );
    }

    if ($request->has('status')) {
        $subcategory->status = $request->status;
    }

    $subcategory->save();

    return response()->json([
        'success' => true,
        'message' => 'Subcategory updated successfully',
        'data' => $subcategory
    ]);
}

}