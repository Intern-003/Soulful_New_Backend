<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAttributeValueController extends Controller
{

    // =========================
    // STORE ATTRIBUTE VALUE
    // =========================
    public function store(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);

        $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                // ✅ prevent duplicate value per attribute
                Rule::unique('attribute_values', 'value')
                    ->where(fn ($q) => $q->where('attribute_id', $attribute->id))
            ],
            'hex_code' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/']
        ]);

        // =========================
        // SLUG GENERATION (ATTRIBUTE-WISE UNIQUE)
        // =========================
        $baseSlug = Str::slug($request->value);
        $slug = $baseSlug;
        $counter = 1;

        while (
            AttributeValue::where('attribute_id', $attribute->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // =========================
        // CREATE VALUE
        // =========================
        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => $request->value,
            'slug' => $slug,
            // ✅ normalize HEX
            'hex_code' => $request->hex_code ? strtoupper($request->hex_code) : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute value created successfully',
            'data' => $value
        ], 201);
    }

    // =========================
    // DELETE ATTRIBUTE VALUE
    // =========================
    public function deleteAttributeValue($id)
    {
        $value = AttributeValue::find($id);

        if (!$value) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found'
            ], 404);
        }

        // prevent delete if used in variants
        if (
            DB::table('product_variant_attributes')
                ->where('attribute_value_id', $id)
                ->exists()
        ) {
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

    // =========================
    // UPDATE ATTRIBUTE VALUE
    // =========================
    public function updateAttributeValue(Request $request, $id)
    {
        $value = AttributeValue::find($id);

        if (!$value) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found'
            ], 404);
        }

        $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                // ✅ prevent duplicate per attribute (ignore current)
                Rule::unique('attribute_values', 'value')
                    ->where(fn ($q) => $q->where('attribute_id', $value->attribute_id))
                    ->ignore($id)
            ],
            'hex_code' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/']
        ]);

        // =========================
        // SLUG GENERATION (SAFE UPDATE)
        // =========================
        $baseSlug = Str::slug($request->value);
        $slug = $baseSlug;
        $counter = 1;

        while (
            AttributeValue::where('attribute_id', $value->attribute_id)
                ->where('slug', $slug)
                ->where('id', '!=', $id)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // =========================
        // UPDATE VALUE
        // =========================
        $value->update([
            'value' => $request->value,
            'slug' => $slug,
            'hex_code' => $request->hex_code ? strtoupper($request->hex_code) : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute value updated successfully',
            'data' => $value
        ]);
    }
}