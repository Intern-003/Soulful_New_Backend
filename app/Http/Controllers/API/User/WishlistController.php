<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{

    // GET /api/wishlists
    // Get all wishlists (Admin or testing)
    public function getWishlists()
    {
        $wishlists = Wishlist::with('product','user')->get();

        return response()->json([
            'success' => true,
            'data' => $wishlists
        ]);
    }


    // GET /api/wishlist
    // Get logged in user's wishlist
    public function getWishlist(Request $request)
    {
        $userId = $request->user()->id;

        $wishlist = Wishlist::with('product')
            ->where('user_id', $userId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlist
        ]);
    }

}