<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class VendorProductController extends Controller
{

    // POST /vendor/products
    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0'
        ]);

        $slug = Str::slug($request->name);

        // ensure slug unique
        $count = Product::where('slug','LIKE',$slug.'%')->count();
        if($count > 0){
            $slug = $slug.'-'.($count+1);
        }

        $product = Product::create([
            'vendor_id' => $request->vendor_id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'status' => true,
            'is_approved' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ],201);
    }

}