<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\User\CategoryController;
use App\Http\Controllers\API\User\ProductController;
use App\Http\Controllers\API\common\AuthController;
use App\Http\Controllers\API\Common\ProfileController;
use App\Http\Controllers\API\Common\AddressController;
use App\Http\Controllers\API\User\CartController;
use App\Http\Controllers\API\User\WishlistController;
use App\Http\Controllers\API\User\CheckoutController;
use App\Http\Controllers\API\User\OrderController;
use App\Http\Controllers\API\User\VendorStoreController;
use App\Http\Controllers\API\User\WalletController;
use App\Http\Controllers\API\Vendor\VendorWalletController;
use App\Http\Controllers\API\User\ShipmentController;
use App\Http\Controllers\API\Vendor\VendorInventoryController;
use App\Http\Controllers\API\Admin\AdminCategoryController;
use App\Http\Controllers\API\Admin\AdminAttributeController;
use App\Http\Controllers\API\Admin\AdminAttributeValueController;
use App\Http\Controllers\API\Admin\AdminCommissionController;
use App\Http\Controllers\API\Admin\AdminBannerController;
use App\Http\Controllers\API\Admin\AdminRoleController;
use App\Http\Controllers\API\Admin\AdminPermissionController;
use App\Http\Controllers\API\Vendor\VendorProductController;
use App\Http\Controllers\API\Vendor\ProductImageController;
use App\Http\Controllers\API\Vendor\VendorVariantController;
use App\Http\Controllers\API\Vendor\VendorCouponController;
use App\Http\Controllers\API\Vendor\VendorRegisterController;
use App\Http\Controllers\API\Vendor\VendorDocumentController;
use App\Http\Controllers\API\Vendor\VendorOrderController;
use App\Http\Controllers\API\Vendor\ProductQuestionController;
use App\Http\Controllers\API\User\CouponController;
use App\Http\Controllers\API\User\ReviewController;
use App\Http\Controllers\API\Admin\AdminWithdrawController;
use App\Http\Controllers\API\Admin\AdminAnalyticsController;
use App\Http\Controllers\API\Admin\AdminSettingsController;
use App\Http\Controllers\API\Admin\AdminOrderController;
use App\Http\Controllers\API\Admin\AdminVendorDocumentController;
use App\Http\Controllers\API\Admin\AdminVendorController;
use App\Http\Controllers\API\Vendor\VendorDashboardController;
use App\Http\Controllers\API\Admin\AdminDashboardController;
use App\Http\Controllers\API\Admin\AdminReportController;
use App\Http\Controllers\API\User\PaymentController;
use App\Http\Controllers\API\User\SupportController;
use App\Http\Controllers\API\Admin\AdminSupportController;


Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

Route::get('profiles', [ProfileController::class, 'getProfiles']);
Route::get('profile/{id}', [ProfileController::class, 'getProfileById']);
Route::get('addresses', [AddressController::class, 'getAddresses']);
Route::get('carts', [CartController::class, 'getCarts']);
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart', [CartController::class, 'getCart']);
Route::put('/cart/{id}', [CartController::class, 'updateCartItem']);
Route::delete('/cart-item/{id}', [CartController::class, 'deleteCartItem']);
Route::delete('/cart/clear', [CartController::class, 'clearCart']);

    
Route::get('wishlists', [WishlistController::class, 'getWishlists']);



Route::middleware('auth:sanctum')->group(function(){
    Route::get('/checkout/summary', [CheckoutController::class,'summary']);
    Route::get('/checkout/data', [CheckoutController::class,'data']);
    Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
    Route::post('/place-order', [CheckoutController::class, 'checkout']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/address', [AddressController::class, 'getAddress']);
    Route::post('/address', [AddressController::class, 'store']);
    Route::delete('/address/{id}', [AddressController::class, 'deleteAddress']);
    Route::put('/address/{id}', [AddressController::class, 'updateAddress']);
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('wishlist', [WishlistController::class, 'getWishlist']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{id}', [WishlistController::class, 'remove']);
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


Route::post('/vendor/coupons',[VendorCouponController::class,'store']);
Route::put('/vendor/coupons/{id}',[VendorCouponController::class,'update']);
Route::delete('/vendor/coupons/{id}',[VendorCouponController::class,'destroy']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coupon/apply',[CouponController::class,'applyCoupon']);
Route::post('/coupon/remove',[CouponController::class,'removeCoupon']);

});
//Coupon(User level)
Route::get('/coupon/available',[CouponController::class,'availableCoupons']);
Route::post('/coupon/validate',[CouponController::class,'validateCoupon']);


Route::middleware('auth:sanctum')->prefix('orders')->group(function(){

    Route::get('/', [OrderController::class,'index']);
    Route::get('/{id}', [OrderController::class,'show']);
    Route::get('/{id}/track', [OrderController::class,'track']);
    Route::get('/{id}/shipment', [OrderController::class,'shipment']);
    Route::get('/{id}/tracking', [OrderController::class,'tracking']);
    Route::get('/{id}/invoice', [OrderController::class,'invoice']);

    Route::post('/', [OrderController::class,'store']);
    Route::post('/{id}/cancel', [OrderController::class,'cancel']);
    Route::post('/{id}/return', [OrderController::class,'return']);
    Route::post('/{id}/exchange', [OrderController::class,'exchange']);

});


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/payment/create-order', [PaymentController::class, 'createOrder']);
    Route::post('/payment/verify', [PaymentController::class, 'verify']);
    Route::get('/payment/status/{order_id}', [PaymentController::class, 'status']);

});

// Admin
Route::middleware(['auth:sanctum','admin'])->group(function () {
    Route::post('/payment/refund', [PaymentController::class, 'refund']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/wallet', [WalletController::class,'wallet']);
    Route::get('/wallet/transactions', [WalletController::class,'transactions']);

    Route::get('/vendor/wallet', [VendorWalletController::class,'wallet']);
    Route::get('/vendor/wallet/transactions', [VendorWalletController::class,'transactions']);

});

Route::middleware('auth:sanctum')->group(function(){

Route::get('/vendor/dashboard',[VendorDashboardController::class,'dashboard']);

Route::get('/vendor/dashboard/stats',[VendorDashboardController::class,'stats']);

Route::get('/vendor/orders/summary',[VendorDashboardController::class,'ordersSummary']);

});

Route::get('/vendor/inventory/{vendor_id}',[VendorInventoryController::class,'inventory']);

Route::get('/vendor/products/low-stock/{vendor_id}',[VendorInventoryController::class,'lowStock']);


Route::post('/admin/categories',[AdminCategoryController::class,'store']);
Route::post('/admin/subcategories',[AdminCategoryController::class,'storeSubcategory']);
Route::post('/admin/attributes',[AdminAttributeController::class,'store']);
Route::post('/admin/attributes/{id}/values', [AdminAttributeValueController::class,'store']);
Route::post('/admin/commissions',[AdminCommissionController::class,'store']);
Route::post('/admin/banners',[AdminBannerController::class,'store']);
Route::post('/admin/roles',[AdminRoleController::class,'store']);
Route::post('/admin/permissions',[AdminPermissionController::class,'store']);


Route::post('/vendor/products',[VendorProductController::class,'store']);
Route::post('/vendor/products/{id}/images',[ProductImageController::class,'store']);
Route::post('/vendor/products/{id}/variants',[VendorVariantController::class,'store']);

Route::post('/vendor/wallet/withdraw',[VendorWalletController::class,'withdraw']);
Route::post('/vendor/register',[VendorRegisterController::class,'register']);
Route::post('/vendor/documents',[VendorDocumentController::class,'store']);
Route::post('/vendor/orders/{id}/shipment',[VendorOrderController::class,'createShipment']);
Route::post('/products/questions/{id}/answer', [ProductQuestionController::class, 'answer']);
Route::post('/products/{id}/questions', [ProductQuestionController::class, 'store']);
Route::get('/products/{id}/questions', [ProductQuestionController::class, 'index']);
Route::middleware('auth:sanctum')->delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
Route::middleware('auth:sanctum')->delete('/addresses/{id}', [AddressController::class, 'deleteAddress']);
// Route::middleware(['auth:sanctum', 'is_admin'])
//     ->delete('/admin/categories/{id}', [AdminCategoryController::class, 'deleteCategory']);
// Route::middleware(['auth:sanctum', 'is_admin'])
//     ->delete('/admin/subcategories/{id}', [AdminCategoryController::class, 'deleteSubcategory']);
Route::middleware(['auth:sanctum'])
    ->delete('/admin/categories/{id}', [AdminCategoryController::class, 'deleteCategory']);
Route::middleware(['auth:sanctum'])
    ->delete('/admin/subcategories/{id}', [AdminCategoryController::class, 'deleteSubcategory']);
Route::middleware(['auth:sanctum'])
    ->put('/admin/categories/{id}', [AdminCategoryController::class, 'updateCategory']);
Route::middleware(['auth:sanctum'])
    ->put('/admin/subcategories/{id}', [AdminCategoryController::class, 'updateSubcategory']);


Route::delete('/vendor/products/images/{id}', [ProductImageController::class, 'deleteProductImage']);
Route::middleware(['auth:sanctum'])
    ->delete('/admin/attributes/{id}', [AdminAttributeController::class, 'deleteAttribute']);
Route::middleware(['auth:sanctum'])
    ->delete('/admin/attribute-values/{id}', [AdminAttributeValueController::class, 'deleteAttributeValue']);
Route::middleware('auth:sanctum')
    ->delete('/vendor/product-variants/{id}', [VendorVariantController::class, 'deleteVariant']);
Route::middleware('auth:sanctum')
    ->delete('/vendor/products/{id}', [VendorProductController::class, 'deleteProduct']);
Route::middleware('auth:sanctum')
    ->delete('/vendor/coupons/{id}', [VendorCouponController::class, 'destroy']);
Route::middleware('auth:sanctum')
    ->delete('/reviews/{id}', [ReviewController::class, 'deleteReview']);
Route::middleware('auth:sanctum')
    ->delete('/admin/banners/{id}', [AdminBannerController::class, 'deleteBanner']);
Route::middleware('auth:sanctum')
    ->delete('/admin/roles/{id}', [AdminRoleController::class, 'deleteRole']);
Route::middleware('auth:sanctum')
    ->delete('/admin/permissions/{id}', [AdminPermissionController::class, 'deletePermission']);
Route::middleware('auth:sanctum')
    ->put('/admin/attributes/{id}', [AdminAttributeController::class, 'updateAttribute']);
Route::middleware('auth:sanctum')
    ->put('/admin/attribute-values/{id}', [AdminAttributeValueController::class, 'updateAttributeValue']);
Route::middleware('auth:sanctum')
    ->put('/vendor/product-variants/{id}', [VendorVariantController::class, 'updateVariant']); 
Route::middleware('auth:sanctum')
    ->put('/vendor/products/{id}/stock', [VendorProductController::class, 'updateStock']);    
Route::middleware('auth:sanctum')
    ->put('/vendor/products/{id}', [VendorProductController::class, 'updateProduct']);  
Route::middleware('auth:sanctum')
    ->put('/vendor/coupons/{id}', [VendorCouponController::class, 'update']);   
Route::middleware('auth:sanctum')
    ->put('/reviews/{id}', [ReviewController::class, 'updateReview']);
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/admin/withdraw-requests/{id}/approve', [AdminWithdrawController::class, 'approve']);
    Route::put('/admin/withdraw-requests/{id}/reject', [AdminWithdrawController::class, 'reject']);
});
Route::middleware('auth:sanctum')
    ->put('/admin/vendors/{id}/commission', [AdminCommissionController::class, 'updateVendorCommission']);
Route::middleware('auth:sanctum')
    ->put('/admin/banners/{id}', [AdminBannerController::class, 'updateBanner']);    
Route::middleware('auth:sanctum')
    ->put('/admin/roles/{id}', [AdminRoleController::class, 'updateRole']);    
Route::middleware('auth:sanctum')
    ->put('/admin/permissions/{id}', [AdminPermissionController::class, 'updatePermission']);    
Route::put('/addresses/{id}/default', [AddressController::class, 'setDefaultAddress'])
    ->middleware('auth:sanctum');    
Route::get('/orders/{id}/status-history', [OrderController::class, 'statusHistory'])
    ->middleware('auth:sanctum');    
Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('auth:sanctum');    
Route::get('/products/{id}/reviews', [ReviewController::class, 'productReviews']);    
Route::get('/admin/withdraw-requests', [AdminWithdrawController::class, 'index'])
    ->middleware(['auth:sanctum']); // add admin middleware if you have

Route::middleware(['auth:sanctum'])->prefix('admin/analytics')->group(function () {

    Route::get('/sales', [AdminAnalyticsController::class, 'sales']);

    Route::get('/orders', [AdminAnalyticsController::class, 'orders']);

    Route::get('/vendors', [AdminAnalyticsController::class, 'vendors']);

    Route::get('/products', [AdminAnalyticsController::class, 'products']);

});

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    Route::get('/settings', [AdminSettingsController::class, 'index']);

    Route::put('/settings', [AdminSettingsController::class, 'update']);

});

// USER
Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice'])
    ->middleware('auth:sanctum');

// ADMIN
Route::get('/admin/orders/summary', [AdminOrderController::class, 'summary'])
    ->middleware(['auth:sanctum']); // add admin middleware if you have

// VENDOR
Route::get('/vendor/orders/summary', [VendorOrderController::class, 'summary'])
    ->middleware(['auth:sanctum']);

    // Vendor
Route::middleware(['auth:sanctum'])->prefix('vendor')->group(function () {
    Route::get('/documents', [VendorDocumentController::class, 'index']);
    Route::post('/documents', [VendorDocumentController::class, 'store']);
});

// Admin
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    Route::get('/vendor-documents/{id}', [AdminVendorDocumentController::class, 'show']);

    Route::put('/vendors/{id}/approve', [AdminVendorController::class, 'approve']);
    Route::put('/vendors/{id}/reject', [AdminVendorController::class, 'reject']);
});

Route::middleware(['auth:sanctum'])->prefix('vendor/dashboard')->group(function () {

    Route::get('/stats', [VendorDashboardController::class, 'stats']);

    Route::get('/revenue-chart', [VendorDashboardController::class, 'revenueChart']);

});


Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    Route::prefix('dashboard')->group(function () {

        Route::get('/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/revenue-chart', [AdminDashboardController::class, 'revenueChart']);
        Route::get('/orders-chart', [AdminDashboardController::class, 'ordersChart']);

    });

    Route::get('/vendors/pending', [AdminDashboardController::class, 'pendingVendors']);
});


Route::middleware(['auth:sanctum'])->prefix('admin/reports')->group(function () {

    Route::get('/sales', [AdminReportController::class, 'sales']);

    Route::get('/vendor-sales', [AdminReportController::class, 'vendorSales']);

    Route::get('/product-sales', [AdminReportController::class, 'productSales']);

    Route::get('/customers', [AdminReportController::class, 'customers']);
});
    Route::prefix('support')->middleware('auth:sanctum')->group(function() {
    Route::get('/tickets', [SupportController::class, 'index']); // list all tickets
    Route::post('/tickets', [SupportController::class, 'store']); // view ticket
    Route::get('/tickets/{id}', [SupportController::class, 'show']); // admin reply
    Route::post('/tickets/{id}/reply', [SupportController::class, 'reply']); // change status
});

Route::prefix('admin/support')->middleware(['auth:sanctum', 'role:admin'])->group(function() {
    Route::get('/', [AdminSupportController::class, 'index']); // list all tickets
    Route::get('/{id}', [AdminSupportController::class, 'show']); // view ticket
    Route::post('/{id}/reply', [AdminSupportController::class, 'reply']); // admin reply
    Route::patch('/{id}/status', [AdminSupportController::class, 'updateStatus']); // change status
});