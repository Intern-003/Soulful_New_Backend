<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;

class WishlistController extends Controller
{

    // GET /api/wishlists
    // Get all wishlists (Admin or testing)
    public function getWishlists()
    {
        $wishlists = Wishlist::with('product', 'user')->get();

        return response()->json([
            'success' => true,
            'data' => $wishlists
        ]);
    }


    // GET /api/wishlist
    // Get logged in user's wishlist
    // public function getWishlist(Request $request)
    // {
    //     $userId = $request->user()->id;

    //     $wishlist = Wishlist::with('product')
    //         ->where('user_id', $userId)
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $wishlist
    //     ]);
    // }
    public function getWishlist(Request $request)
    {
        $userId = $request->user()->id;

        $wishlist = Wishlist::with([
            'product.images' // ✅ load images
        ])
            ->where('user_id', $userId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlist
        ]);
    }



    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Validate request
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        // Check product exists
        $product = Product::find($request->product_id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Prevent duplicate wishlist entry
        $alreadyExists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already in wishlist'
            ], 409);
        }

        // Create wishlist entry
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id
        ]);
        $wishlist->load([
            'product.images' // ✅ include images
        ]);
        // Load product relation
        //$wishlist->load('product');


        return response()->json([
            'success' => true,
            'message' => 'Added to wishlist',
            'data' => $wishlist
        ], 201);
    }


    public function remove(Request $request, $productId)
    {
        $user = $request->user();

        $wishlistItem = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (!$wishlistItem) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found'
            ], 404);
        }

        $wishlistItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from wishlist'
        ]);
    }
}