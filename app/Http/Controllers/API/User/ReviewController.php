<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
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
