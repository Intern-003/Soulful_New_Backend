<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;

class AdminBannerController extends Controller
{

    // POST /admin/banners
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean'
        ]);

        $banner = Banner::create([
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image' => $request->image,
            'link' => $request->link,
            'position' => $request->position ?? 1,
            'status' => $request->status ?? true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => $banner
        ],201);
    }

}