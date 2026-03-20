<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use Illuminate\Support\Str;

class AdminAttributeController extends Controller
{

    // POST /admin/attributes
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name'
        ]);

        $slug = Str::slug($request->name);

        // ensure unique slug
        $count = Attribute::where('slug','LIKE',$slug.'%')->count();
        if($count > 0){
            $slug = $slug.'-'.($count+1);
        }

        $attribute = Attribute::create([
            'name' => $request->name,
            'slug' => $slug,
            'status' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute created successfully',
            'data' => $attribute
        ],201);
    }

    public function deleteAttribute($id)
{
    $attribute = Attribute::find($id);

    if (!$attribute) {
        return response()->json([
            'success' => false,
            'message' => 'Attribute not found'
        ], 404);
    }

    // Check if attribute has values
    if ($attribute->values()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete attribute with values. Delete values first.'
        ], 400);
    }

    $attribute->delete();

    return response()->json([
        'success' => true,
        'message' => 'Attribute deleted successfully'
    ]);
}

public function updateAttribute(Request $request, $id)
{
    $attribute = Attribute::find($id);

    if (!$attribute) {
        return response()->json([
            'success' => false,
            'message' => 'Attribute not found'
        ], 404);
    }

    $request->validate([
        'name' => 'required|string|max:255|unique:attributes,name,' . $id
    ]);

    $slug = Str::slug($request->name);

    // Ensure unique slug
    $count = Attribute::where('slug', 'LIKE', $slug . '%')
        ->where('id', '!=', $id)
        ->count();

    if ($count > 0) {
        $slug = $slug . '-' . ($count + 1);
    }

    $attribute->update([
        'name' => $request->name,
        'slug' => $slug
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Attribute updated successfully',
        'data' => $attribute
    ]);
}

}