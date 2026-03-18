<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductQuestion;

class ProductQuestionController extends Controller
{

    public function store(Request $request, $id)
{
    $request->validate([
        'question' => 'required|string|max:1000'
    ]);

    $question = ProductQuestion::create([
        'product_id' => $id,
        'user_id' => auth()->id(), // optional (null if not logged in)
        'question' => $request->question
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Question submitted successfully',
        'data' => $question
    ], 201);
}

    public function answer(Request $request, $id)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'answer' => 'required|string'
        ]);

        $question = ProductQuestion::findOrFail($id);

        $question->update([
            'answer' => $request->answer,
            'answered_by' => $request->vendor_id,
            'answered_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Answer submitted successfully',
            'data' => $question
        ]);
    }

    public function index($id)
{
    $questions = ProductQuestion::where('product_id', $id)
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'data' => $questions
    ]);
}         
}