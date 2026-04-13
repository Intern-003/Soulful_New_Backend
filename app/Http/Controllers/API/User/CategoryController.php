<?php

// namespace App\Http\Controllers\API\User;

// use App\Http\Controllers\Controller;
// use App\Models\Category;
// use App\Models\Product;
// use Illuminate\Http\Request;
// class CategoryController extends Controller
// {

//     // GET /categories
//     public function index()
//     {
//         $categories = Category::whereNull('parent_id')
//             ->where('status', 1)
//             ->orderBy('position')
//             ->get();

//         return response()->json([
//             'success' => true,
//             'data' => $categories
//         ]);
//     }


//     // GET /categories/{id}
//     public function show($id)
//     {
//         $category = Category::with('children')
//             ->where('id', $id)
//             ->where('status', 1)
//             ->firstOrFail();

//         return response()->json([
//             'success' => true,
//             'data' => $category
//         ]);
//     }


//     // GET /categories/{id}/children
//     public function children($id)
//     {
//         $children = Category::where('parent_id', $id)
//             ->where('status', 1)
//             ->orderBy('position')
//             ->get();

//         return response()->json([
//             'success' => true,
//             'data' => $children
//         ]);
//     }

//  public function products($slug, Request $request)
// {
//     $category = Category::where('slug', $slug)->firstOrFail();

//     // get subcategories
//     $categoryIds = Category::where('parent_id', $category->id)
//                     ->pluck('id')
//                     ->toArray();

//     $categoryIds[] = $category->id;

//     $query = Product::with(['images','brand'])
//         ->whereIn('category_id', $categoryIds)
//         ->where('status',1)
//         ->where('is_approved',1);

//     if ($request->price_min) {
//         $query->where('price','>=',$request->price_min);
//     }

//     if ($request->price_max) {
//         $query->where('price','<=',$request->price_max);
//     }

//     $products = $query->paginate(10);

//     return response()->json([
//         'success'=>true,
//         'category'=>$category->name,
//         'data'=>$products
//     ]);
// }

// }  





namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // =========================
    // GET ROOT CATEGORIES (with full tree)
    // =========================
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->where('status', 1)
            ->orderBy('position')
            ->get()
            ->map(function ($category) {
                $category->children = $this->getNestedChildren($category->id);
                return $category;
            });

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // =========================
    // GET SINGLE CATEGORY (FULL TREE)
    // =========================
    public function show($id)
    {
        $category = Category::where('id', $id)
            ->where('status', 1)
            ->firstOrFail();

        $category->children = $this->getNestedChildren($category->id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    // =========================
    // GET CHILDREN (RECURSIVE)
    // =========================
    public function children($id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->getNestedChildren($id)
        ]);
    }

    // =========================
    // GET PRODUCTS BY CATEGORY + ALL DESCENDANTS
    // =========================
    public function products($slug, Request $request)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        // get ALL descendant category IDs (recursive)
        $categoryIds = $this->getAllCategoryIds($category->id);

        $query = Product::with(['images', 'brand'])
            ->whereIn('category_id', $categoryIds)
            ->where('status', 1)
            ->where('is_approved', 1);

        if ($request->price_min) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->price_max) {
            $query->where('price', '<=', $request->price_max);
        }

        $products = $query->paginate(10);

        return response()->json([
            'success' => true,
            'category' => $category->name,
            'data' => $products
        ]);
    }

    // =========================
    // RECURSIVE: GET CHILD TREE
    // =========================
    private function getNestedChildren($parentId)
    {
        $children = Category::where('parent_id', $parentId)
            ->where('status', 1)
            ->orderBy('position')
            ->get();

        return $children->map(function ($child) {
            $child->children = $this->getNestedChildren($child->id);
            return $child;
        });
    }

    // =========================
    // RECURSIVE: GET ALL IDS FLAT
    // =========================
    private function getAllCategoryIds($parentId)
    {
        $ids = [$parentId];

        $children = Category::where('parent_id', $parentId)
            ->where('status', 1)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getAllCategoryIds($childId));
        }

        return $ids;
    }
}

