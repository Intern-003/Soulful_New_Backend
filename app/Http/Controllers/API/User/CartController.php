<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;

class CartController extends Controller
{
    public function getCarts(Request $request)
    {
        $carts = Cart::all();

        return response()->json($carts);
    }

    public function getCart(Request $request)
    {
        // If user is logged in
        $userId = $request->user()->id;

        $cart = Cart::with('items.product', 'items.variant')
            ->where('user_id', $userId)
            ->first(); // usually one active cart per user

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cart
        ]);
    }

  
}
