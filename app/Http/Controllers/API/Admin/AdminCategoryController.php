<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

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
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'position' => 'nullable|integer'
        ]);

        // ✅ FIXED SLUG
        $slug = $this->generateUniqueSlug($request->name);

        // ✅ IMAGE UPLOAD
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/categories'), $filename);
            $imagePath = 'uploads/categories/'.$filename;
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
    public function storeSubcategory(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        // ✅ FIXED SLUG
        $slug = $this->generateUniqueSlug($request->name);

        // ✅ IMAGE UPLOAD (FIXED ISSUE HERE)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/categories'), $filename);
            $imagePath = 'uploads/categories/'.$filename;
        }

        $subcategory = Category::create([
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath, // ✅ NOW WORKS
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

        if (Product::where('category_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Subcategory has products. Cannot delete.'
            ], 400);
        }

        // ✅ DELETE IMAGE SAFELY
        if ($subcategory->image && file_exists(public_path($subcategory->image))) {
            unlink(public_path($subcategory->image));
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
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean'
        ]);

        // ✅ FIXED SLUG
        if ($request->has('name') && $request->name != $category->name) {
            $category->slug = $this->generateUniqueSlug($request->name, $id);
            $category->name = $request->name;
        }

        if ($request->has('parent_id')) $category->parent_id = $request->parent_id;
        if ($request->has('description')) $category->description = $request->description;

        // ✅ IMAGE UPDATE
        if ($request->hasFile('image')) {

            if ($category->image && file_exists(public_path($category->image))) {
                unlink(public_path($category->image));
            }

            $file = $request->file('image');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/categories'), $filename);

            $category->image = 'uploads/categories/'.$filename;
        }

        if ($request->has('position')) $category->position = $request->position;
        if ($request->has('status')) $category->status = $request->status;

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
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'sometimes|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean'
        ]);

        // ✅ FIXED SLUG
        if ($request->has('name') && $request->name != $subcategory->name) {
            $subcategory->slug = $this->generateUniqueSlug($request->name, $id);
            $subcategory->name = $request->name;
        }

        if ($request->has('parent_id')) $subcategory->parent_id = $request->parent_id;
        if ($request->has('description')) $subcategory->description = $request->description;

        // ✅ IMAGE UPDATE (FIXED ISSUE)
        if ($request->hasFile('image')) {

            if ($subcategory->image && file_exists(public_path($subcategory->image))) {
                unlink(public_path($subcategory->image));
            }

            $file = $request->file('image');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/categories'), $filename);

            $subcategory->image = 'uploads/categories/'.$filename;
        }

        if ($request->has('status')) $subcategory->status = $request->status;

        $subcategory->save();

        return response()->json([
            'success' => true,
            'message' => 'Subcategory updated successfully',
            'data' => $subcategory
        ]);
    }

}