<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{

    // POST /admin/categories
public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'parent_id' => 'nullable|exists:categories,id',
        'description' => 'nullable|string',
        'image' => 'nullable|string',
        'position' => 'nullable|integer'
    ]);

    $slug = Str::slug($request->name);

    // Ensure slug is unique
    $count = Category::where('slug','LIKE',$slug.'%')->count();

    if($count > 0){
        $slug = $slug.'-'.($count+1);
    }

    $category = Category::create([
        'parent_id' => $request->parent_id,
        'name' => $request->name,
        'slug' => $slug,
        'description' => $request->description,
        'image' => $request->image,
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
            'description' => 'nullable|string'
        ]);

        $slug = Str::slug($request->name);

        $count = Category::where('slug','LIKE',$slug.'%')->count();

        if($count > 0){
            $slug = $slug.'-'.($count+1);
        }

        $subcategory = Category::create([
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'status' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory created successfully',
            'data' => $subcategory
        ]);
    }

    public function deleteCategory($id)
{
    $category = Category::find($id);

    if (!$category) {
        return response()->json([
            'success' => false,
            'message' => 'Category not found'
        ], 404);
    }

    // Check if category has subcategories
    $hasChildren = Category::where('parent_id', $id)->exists();

    if ($hasChildren) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete category with subcategories. Delete subcategories first.'
        ], 400);
    }

    // (Optional but recommended) check if products exist under this category
    if (Product::where('category_id', $id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Category has products. Cannot delete.'
        ], 400);
    }

    $category->delete();

    return response()->json([
        'success' => true,
        'message' => 'Category deleted successfully'
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

    // Ensure it's a subcategory
    if (is_null($subcategory->parent_id)) {
        return response()->json([
            'success' => false,
            'message' => 'This is not a subcategory'
        ], 400);
    }

            // Optional: check if products exist under subcategory
    if (Product::where('category_id', $id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Subcategory has products. Cannot delete.'
        ], 400);
    }

    $subcategory->delete();

    return response()->json([
        'success' => true,
        'message' => 'Subcategory deleted successfully'
    ]);
}
}