<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{

public function productReviews(Request $request, $id)
{
    // Check product exists
    $productExists = \App\Models\Product::where('id', $id)->exists();

    if (!$productExists) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found'
        ], 404);
    }

    // Get reviews (only approved)
    $reviews = Review::with('user:id,name') // adjust fields if needed
        ->where('product_id', $id)
        ->where('status', 1) // only approved
        ->latest()
        ->paginate(10);

    // Average rating
    $averageRating = Review::where('product_id', $id)
        ->where('status', 1)
        ->avg('rating');

    return response()->json([
        'success' => true,
        'average_rating' => round($averageRating, 1),
        'total_reviews' => $reviews->total(),
        'data' => $reviews
    ]);
}

public function store(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }

    $validated = $request->validate([
        'product_id' => 'required|exists:products,id',
        'rating' => 'required|integer|min:1|max:5',
        'title' => 'nullable|string|max:255',
        'review' => 'nullable|string'
    ]);

    // ❌ Prevent duplicate review (1 user = 1 review per product)
    $existingReview = Review::where('user_id', $user->id)
        ->where('product_id', $validated['product_id'])
        ->first();

    if ($existingReview) {
        return response()->json([
            'success' => false,
            'message' => 'You have already reviewed this product'
        ], 400);
    }

    $review = Review::create([
        'user_id' => $user->id,
        'product_id' => $validated['product_id'],
        'rating' => $validated['rating'],
        'title' => $validated['title'] ?? null,
        'review' => $validated['review'] ?? null,
        'status' => 0 // pending (recommended)
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Review submitted successfully',
        'data' => $review
    ], 201);
}



 // ----------------------------
 // Create Review
 // POST /reviews
 // ----------------------------
// public function store(Request $request)
// {
//     $user = Auth::guard('sanctum')->user();

//     if (!$user) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Unauthorized'
//         ], 401);
//     }

//     $request->validate([
//         'product_id' => 'required|exists:products,id',
//         'rating' => 'required|integer|min:1|max:5',
//         'title' => 'nullable|string|max:255',
//         'review' => 'nullable|string'
//     ]);

//     // ❌ Prevent duplicate review
//     $existingReview = Review::where('product_id', $request->product_id)
//         ->where('user_id', $user->id)
//         ->first();

//     if ($existingReview) {
//         return response()->json([
//             'success' => false,
//             'message' => 'You have already reviewed this product'
//         ], 400);
//     }

//     $review = Review::create([
//         'product_id' => $request->product_id,
//         'user_id' => $user->id,
//         'rating' => $request->rating,
//         'title' => $request->title,
//         'review' => $request->review,
//         'status' => false // ✅ default: pending approval
//     ]);

//     return response()->json([
//         'success' => true,
//         'message' => 'Review submitted successfully (pending approval)',
//         'data' => $review
//     ], 201);
// }

  public function deleteReview($id)
{
    $review = Review::find($id);

    if (!$review) {
        return response()->json([
            'success' => false,
            'message' => 'Review not found'
        ], 404);
    }

    // ✅ Only owner can delete
    if ($review->user_id !== Auth::id()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to delete this review'
        ], 403);
    }

    $review->delete();

    return response()->json([
        'success' => true,
        'message' => 'Review deleted successfully'
    ]);
}

public function updateReview(Request $request, $id)
{
    $review = Review::find($id);

    if (!$review) {
        return response()->json([
            'success' => false,
            'message' => 'Review not found'
        ], 404);
    }

    // ✅ Ownership check
    if ($review->user_id !== Auth::id()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // ❌ Prevent update if already approved (recommended)
    if ($review->status == 1) {
        return response()->json([
            'success' => false,
            'message' => 'Approved reviews cannot be edited'
        ], 400);
    }

    $request->validate([
        'rating' => 'sometimes|integer|min:1|max:5',
        'title' => 'nullable|string|max:255',
        'review' => 'nullable|string'
    ]);

    $review->update($request->only([
        'rating',
        'title',
        'review'
    ]));

    return response()->json([
        'success' => true,
        'message' => 'Review updated successfully',
        'data' => $review
    ]);
}


}
