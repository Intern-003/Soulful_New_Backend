<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminBrandController extends Controller
{
    // ✅ GET ALL
    public function index(Request $request)
    {
        $query = Brand::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->latest()->paginate(10));
    }

    // ✅ STORE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean'
        ]);

        // slug generate
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        // ✅ FILE UPLOAD (manual like avatar)
        if ($request->hasFile('logo')) {

            $file = $request->file('logo');

            $filename = time() . '_brand.' . $file->getClientOriginalExtension();

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
            'logo_url' => isset($validated['logo']) ? url($validated['logo']) : null
        ], 201);
    }

    // ✅ SHOW
    public function show(Brand $brand)
    {
        return response()->json([
            'data' => $brand,
            'logo_url' => $brand->logo ? url($brand->logo) : null
        ]);
    }

    // ✅ UPDATE
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug,' . $brand->id,
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean'
        ]);

        // slug update
        if (isset($validated['name']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // ✅ FILE UPDATE (manual)
        if ($request->hasFile('logo')) {

            $file = $request->file('logo');

            $filename = time() . '_brand.' . $file->getClientOriginalExtension();

            $destination = public_path('uploads/brands');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            // delete old logo
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
            'logo_url' => $brand->logo ? url($brand->logo) : null
        ]);
    }

    // ✅ DELETE
    public function destroy(Brand $brand)
    {
        // delete logo file
        if ($brand->logo && file_exists(public_path($brand->logo))) {
            unlink(public_path($brand->logo));
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }
}