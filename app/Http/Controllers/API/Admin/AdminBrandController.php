<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminBrandController extends Controller
{
    // ===============================
    // ✅ GET ALL (ADMIN - NO FILTER)
    // ===============================
    public function index(Request $request)
    {
        $query = Brand::query();

        // 🔍 Search (keep this only)
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $query->latest()->paginate(10)
        );
    }

    // ===============================
    // ✅ GET ONLY ACTIVE (FOR DROPDOWN)
    // ===============================
    public function activeBrands()
    {
        $brands = Brand::where('status', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($brands);
    }

    // ===============================
    // ✅ STORE
    // ===============================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean'
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            $originalName = Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            );

            $extension = $file->getClientOriginalExtension();

            $filename = $request->user()->id . '_' . $originalName . '.' . $extension;

            $destination = public_path('uploads/brands');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $filename);

            $validated['logo'] = 'uploads/brands/' . $filename;
        }

        $brand = Brand::create($validated);

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand,
            'logo_url' => isset($validated['logo']) ? asset($validated['logo']) : null
        ], 201);
    }

    // ===============================
    // ✅ SHOW
    // ===============================
    public function show(Brand $brand)
    {
        return response()->json([
            'data' => $brand,
            'logo_url' => $brand->logo ? asset($brand->logo) : null
        ]);
    }

    // ===============================
    // ✅ UPDATE
    // ===============================
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug,' . $brand->id,
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean'
        ]);

        if (isset($validated['name']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            $originalName = Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            );

            $extension = $file->getClientOriginalExtension();

            $filename = $request->user()->id . '_' . $originalName . '_' . time() . '.' . $extension;

            $destination = public_path('uploads/brands');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            // delete old
            if ($brand->logo && file_exists(public_path($brand->logo))) {
                unlink(public_path($brand->logo));
            }

            $file->move($destination, $filename);

            $validated['logo'] = 'uploads/brands/' . $filename;
        }

        $brand->update($validated);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $brand,
            'logo_url' => $brand->logo ? asset($brand->logo) : null
        ]);
    }

    // ===============================
    // ✅ DELETE
    // ===============================
    public function destroy(Brand $brand)
    {
        if ($brand->logo && file_exists(public_path($brand->logo))) {
            unlink(public_path($brand->logo));
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }
}