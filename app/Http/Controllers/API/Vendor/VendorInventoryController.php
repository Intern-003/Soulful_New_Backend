<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;

class VendorInventoryController extends Controller
{

    // GET /vendor/inventory/{vendor_id}
    public function inventory($vendor_id)
    {
        $products = Product::where('vendor_id',$vendor_id)
            ->select('id','name','sku','stock','price')
            ->get();

        return response()->json([
            'success'=>true,
            'total_products'=>$products->count(),
            'data'=>$products
        ]);
    }



    // GET /vendor/products/low-stock/{vendor_id}
    public function lowStock($vendor_id)
    {
        $lowStockProducts = Product::where('vendor_id',$vendor_id)
            ->where('stock','<',10)
            ->select('id','name','sku','stock')
            ->get();

        return response()->json([
            'success'=>true,
            'low_stock_count'=>$lowStockProducts->count(),
            'data'=>$lowStockProducts
        ]);
    }

}