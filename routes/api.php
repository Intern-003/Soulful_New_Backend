<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\common\AuthController;
use App\Http\Controllers\API\Common\ProfileController;
use App\Http\Controllers\API\Common\AddressController;
use App\Http\Controllers\API\User\CartController;
use App\Http\Controllers\API\User\WishlistController;
use App\Http\Controllers\API\User\CheckoutController;



Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

Route::get('profiles', [ProfileController::class, 'getProfiles']);
Route::get('profile/{id}', [ProfileController::class, 'getProfileById']);
Route::get('addresses', [AddressController::class, 'getAddresses']);
Route::get('address/{id}', [AddressController::class, 'getAddressById']);
Route::get('carts', [CartController::class, 'getCarts']);
Route::get('cart', [CartController::class, 'getCart']);
Route::get('wishlists', [WishlistController::class, 'getWishlists']);
Route::get('wishlist', [WishlistController::class, 'getWishlist']);

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/checkout/summary', [CheckoutController::class,'summary']);
    Route::get('/checkout/data', [CheckoutController::class,'data']);
});
Route::get('/shipping/methods', [CheckoutController::class,'shippingMethods']);

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