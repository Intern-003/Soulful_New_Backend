<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorStoreSection;

class VendorStoreSectionController extends Controller
{
   public function store(Request $request)
{
    $vendor = auth()->user()->vendor;

    $request->validate([
        'title' => 'required|string|max:255',
        'type' => 'required|in:featured_products,new_arrivals,best_sellers,banner,category',
    ]);

    $section = VendorStoreSection::create([
        'vendor_id' => $vendor->id,
        'title' => $request->title,
        'type' => $request->type,
        'sort_order' => 0,
    ]);

    return response()->json([
        'success' => true,
        'data' => $section
    ]);
}

public function index()
{
    $vendor = auth()->user()->vendor;

    return response()->json([
        'success' => true,
        'data' => $vendor
            ->storeSections()
            ->orderBy('sort_order')
            ->get()
    ]);
} 

public function destroy($id)
{
    $vendor = auth()->user()->vendor;

    $section = VendorStoreSection::where(
        'vendor_id',
        $vendor->id
    )->findOrFail($id);

    $section->delete();

    return response()->json([
        'success' => true
    ]);
} 


}
