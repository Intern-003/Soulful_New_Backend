<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{
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
