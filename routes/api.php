<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\User\CategoryController;
use App\Http\Controllers\API\User\ProductController;
use App\Http\Controllers\API\Common\AuthController;
use App\Http\Controllers\API\User\VendorStoreController;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);



Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile/update', [AuthController::class, 'updateProfile']);
    Route::put('profile/change-password', [AuthController::class, 'changePassword']);
    Route::post('profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [AuthController::class, 'deleteAvatar']);
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail']);
});

Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);


Route::prefix('categories')->group(function () {

    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/children', [CategoryController::class, 'children']);
    Route::get('/{slug}/products', [CategoryController::class,'products']);
});


Route::prefix('products')->group(function () {

    // Special routes FIRST
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/latest', [ProductController::class, 'latest']);
    Route::get('/deals', [ProductController::class, 'deals']);
    Route::get('/best-sellers', [ProductController::class, 'bestSellers']);

    // Listing
    Route::get('/', [ProductController::class, 'index']);

    // Related
    Route::get('/{id}/related', [ProductController::class, 'related']);


    // Product detail LAST
    Route::get('/{id}/images',[ProductController::class,'images']);
    Route::get('/{id}/reviews',[ProductController::class,'reviews']);
    Route::get('/{id}/rating',[ProductController::class,'rating']);
    Route::get('/{slug}', [ProductController::class, 'show']);
    
});

Route::prefix('vendors')->group(function(){

Route::get('/',[VendorStoreController::class,'index']);
Route::get('/{slug}',[VendorStoreController::class,'show']);
Route::get('/{slug}/products',[VendorStoreController::class,'products']);
Route::get('/{slug}/reviews',[VendorStoreController::class,'reviews']);

});
