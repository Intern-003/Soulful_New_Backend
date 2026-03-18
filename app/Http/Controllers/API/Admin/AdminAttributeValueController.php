<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;


class AdminAttributeValueController extends Controller
{

    // POST /admin/attributes/{id}/values
    public function store(Request $request, $id)
    {
        $request->validate([
            'value' => 'required|string|max:255'
        ]);

        $attribute = Attribute::findOrFail($id);

        $slug = Str::slug($request->value);

        // ensure unique slug
        $count = AttributeValue::where('slug','LIKE',$slug.'%')->count();
        if($count > 0){
            $slug = $slug.'-'.($count+1);
        }

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => $request->value,
            'slug' => $slug
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute value created successfully',
            'data' => $value
        ],201);
    }

    public function deleteAttributeValue($id)
{
    $value = AttributeValue::find($id);

    if (!$value) {
        return response()->json([
            'success' => false,
            'message' => 'Attribute value not found'
        ], 404);
    }

    // Optional: check if used in variants
    if (\DB::table('product_variant_values')->where('attribute_value_id', $id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Value is used in variants. Cannot delete.'
        ], 400);
    }

    $value->delete();

    return response()->json([
        'success' => true,
        'message' => 'Attribute value deleted successfully'
    ]);
}

}