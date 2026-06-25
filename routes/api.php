<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\User\CategoryController;
use App\Http\Controllers\API\User\ProductController;
use App\Http\Controllers\API\Common\AuthController;
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
use App\Http\Controllers\API\Admin\AdminUserController;
use App\Http\Controllers\API\Admin\AdminLogController;
use App\Http\Controllers\API\Admin\AdminBrandController;
use App\Http\Controllers\API\Admin\AdminProductController;
use App\Http\Controllers\API\Common\OtpController;
use App\Http\Controllers\API\Common\BannerController;
use App\Http\Controllers\API\Admin\AdminCouponController;
use App\Http\Controllers\API\Vendor\VendorPayoutController;
use App\Http\Controllers\API\Vendor\StoreSettingsController;
use App\Http\Controllers\API\Vendor\VendorStoreBannerController;
use App\Http\Controllers\API\Vendor\VendorStoreSectionController;
use App\Http\Controllers\API\Vendor\StoreManagementController;
use App\Http\Controllers\API\User\VendorFollowController;
/*
|--------------------------------------------------------------------------
| API Routes - PUBLIC ROUTES (No authentication required)
|--------------------------------------------------------------------------
*/

// ==================== PUBLIC AUTH ROUTES ====================
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('auth/verify-register', [AuthController::class, 'verifyRegister']);
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);

// ==================== PUBLIC CATEGORY ROUTES ====================
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{slug}/products', [CategoryController::class, 'products']);
    Route::get('/{id}/children', [CategoryController::class, 'children']);
    //Route::get('/id/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}', [CategoryController::class, 'show']); // ✅ Correct
});

// ==================== PUBLIC PRODUCT ROUTES ====================
Route::prefix('products')->group(function () {
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/latest', [ProductController::class, 'latest']);
    Route::get('/deals', [ProductController::class, 'deals']);
    Route::get('/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/related', [ProductController::class, 'relatedBulk']);
    Route::get('/{id}/related', [ProductController::class, 'related']);
    Route::get('/{id}/images', [ProductController::class, 'images']);
    Route::get('/{id}/reviews', [ProductController::class, 'reviews']);
    Route::get('/{id}/rating', [ProductController::class, 'rating']);
    Route::get('/{identifier}', [ProductController::class, 'show'])->where('identifier', '[0-9a-zA-Z\-]+');
});

// ==================== PUBLIC VENDOR STORE ROUTES ====================
Route::prefix('vendors')->group(function () {
    Route::get('/', [VendorStoreController::class, 'index']);
    Route::get('/{slug}', [VendorStoreController::class, 'show']);
    Route::get('/{slug}/products', [VendorStoreController::class, 'products']);
    Route::get('/{slug}/reviews', [VendorStoreController::class, 'reviews']);
    Route::get('/{slug}/homepage',[VendorStoreController::class, 'homepage']); 
});


Route::middleware('auth:sanctum')->group(function () {
Route::post( '/vendors/{slug}/follow', [VendorFollowController::class, 'follow']);
Route::delete('/vendors/{slug}/follow',[VendorFollowController::class, 'unfollow']);});
Route::get('/vendors/{slug}/followers',[VendorFollowController::class, 'followers']);

        /*
        |--------------------------------------------------------------------------
        | Store Management
        |--------------------------------------------------------------------------
        */
Route::prefix('vendor')->middleware('auth:sanctum')->group(function () {
Route::get('/store-management', [StoreManagementController::class, 'index'])->middleware('permission:store.view');
Route::put('/store-management/save', [StoreManagementController::class, 'save'])->middleware('permission:store.update');
Route::put('/store-settings',[StoreSettingsController::class, 'update'])->middleware('permission:store.update');
        /*
        |--------------------------------------------------------------------------
        | Store Banners
        |--------------------------------------------------------------------------
        */
Route::get(  '/store-banners', [VendorStoreBannerController::class, 'index'])->middleware('permission:storebanner.view');
Route::post('/store-banners',[VendorStoreBannerController::class, 'store'])->middleware('permission:storebanner.create');
Route::delete('/store-banners/{id}',[VendorStoreBannerController::class, 'destroy'])->middleware('permission:storebanner.delete');
        /*
        |--------------------------------------------------------------------------
        | Store Sections
        |--------------------------------------------------------------------------
        */
Route::get('/store-sections',[VendorStoreSectionController::class, 'index'])->middleware('permission:storesection.view');
Route::post('/store-sections',[VendorStoreSectionController::class, 'store'])->middleware('permission:storesection.create');
Route::delete('/store-sections/{id}',[VendorStoreSectionController::class, 'destroy'])->middleware('permission:storesection.delete');
});

// ==================== PUBLIC COUPON ROUTES ====================
Route::get('/coupon/available', [CouponController::class, 'availableCoupons']);
Route::post('/coupon/validate', [CouponController::class, 'validateCoupon']);

// ==================== PUBLIC BANNER & BRAND ROUTES ====================
Route::get('/banners', [BannerController::class, 'getBanners']);
Route::get('/brands', [AdminBrandController::class, 'index']);
Route::get('/brands/active', [AdminBrandController::class, 'activeBrands']);
Route::get('/brands/category/{id}', [AdminBrandController::class, 'getBrandsByCategory']);
Route::get('brands/{brand}', [AdminBrandController::class, 'show']);
Route::get('/admin/brands/{brand}/products', [AdminBrandController::class, 'products']);


// ==================== HEALTH CHECK ====================
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (All require auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ==================== USER AUTH & PROFILE ====================
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('profile', [ProfileController::class, 'getProfile']);
    Route::put('profile/update', [ProfileController::class, 'updateProfile']);
    Route::put('profile/change-password', [AuthController::class, 'changePassword']);
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail']);

    // ==================== ADDRESS MANAGEMENT ====================
    Route::get('/address', [AddressController::class, 'getAddress']);
    Route::post('/address', [AddressController::class, 'store']);
    Route::put('/address/{id}', [AddressController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [AddressController::class, 'deleteAddress']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefaultAddress']);

    // ==================== CART ROUTES ====================
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::put('/cart/{id}', [CartController::class, 'updateCartItem']);
    Route::delete('/cart-item/{id}', [CartController::class, 'deleteCartItem']);
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);

    // ==================== WISHLIST ====================
    Route::get('wishlist', [WishlistController::class, 'getWishlist']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{id}', [WishlistController::class, 'remove']);

    // ==================== CHECKOUT & ORDERS ====================
    Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
    Route::get('/checkout/data', [CheckoutController::class, 'data']);
    Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
    Route::post('/place-order', [CheckoutController::class, 'checkout']);

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/track', [OrderController::class, 'track']);
        Route::get('/{id}/shipment', [OrderController::class, 'shipment']);
        Route::get('/{id}/shipment-details', [OrderController::class, 'shipmentDetails']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::get('/{id}/invoice', [OrderController::class, 'invoice']);
        Route::get('/{id}/status-history', [OrderController::class, 'statusHistory']);
        Route::post('/', [OrderController::class, 'store']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/return', [OrderController::class, 'return']);
        Route::post('/{id}/exchange', [OrderController::class, 'exchange']);
    });

    // ==================== PAYMENT ====================
    Route::post('/payment/create-order', [PaymentController::class, 'createOrder']);
    Route::post('/payment/verify', [PaymentController::class, 'verify']);
    Route::get('/payment/status/{order_id}', [PaymentController::class, 'status']);

    // ==================== USER WALLET ====================
    Route::get('/wallet', [WalletController::class, 'wallet']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    // ==================== REVIEWS ====================
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'updateReview']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'deleteReview']);

    // ==================== COUPON APPLY/REMOVE ====================
    Route::post('/coupon/apply', [CouponController::class, 'applyCoupon']);
    Route::post('/coupon/remove', [CouponController::class, 'removeCoupon']);

    // ==================== PRODUCT QUESTIONS ====================
    Route::post('/products/{id}/questions', [ProductQuestionController::class, 'store']);
    Route::post('/products/questions/{id}/answer', [ProductQuestionController::class, 'answer']);
    Route::get('/products/{id}/questions', [ProductQuestionController::class, 'index']);

    // ==================== SUPPORT TICKETS ====================
    Route::prefix('support')->group(function () {
        Route::get('/tickets', [SupportController::class, 'index']);
        Route::post('/tickets', [SupportController::class, 'store']);
        Route::get('/tickets/{id}', [SupportController::class, 'show']);
        Route::post('/tickets/{id}/reply', [SupportController::class, 'reply']);
    });

    // ==================== VENDOR REGISTRATION ====================
    Route::post('/vendor/register', [VendorRegisterController::class, 'register']);
    Route::post('/vendor/submit', [VendorRegisterController::class, 'submit']);

    // ==================== NOTIFICATIONS ====================
    Route::get('/notifications', [AdminProductController::class, 'notifications']);
    Route::get('/notifications/unread-count', [AdminProductController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [AdminProductController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [AdminProductController::class, 'markAllAsRead']);
});

/*
|--------------------------------------------------------------------------
| VENDOR ROUTES (With permission middleware)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('vendor')->group(function () {

    // ==================== VENDOR DASHBOARD ====================
    Route::get('/dashboard', [VendorDashboardController::class, 'dashboard'])->middleware('permission:vendor.dashboard.view');
    Route::get('/dashboard/stats', [VendorDashboardController::class, 'stats'])->middleware('permission:vendor.dashboard.view');
  
    
    // ✅ ADD THIS MISSING ROUTE
    Route::get('/dashboard/revenue-chart', [VendorDashboardController::class, 'revenueChart'])
        ->middleware('permission:vendor.dashboard.view');

    // ==================== VENDOR ORDERS ====================
    Route::get('/orders/summary', [VendorDashboardController::class, 'ordersSummary'])
        ->middleware('permission:vendor.dashboard.view');

    // ==================== VENDOR DOCUMENTS ====================
    Route::get('/documents', [VendorDocumentController::class, 'index'])->middleware('permission:vendor.documents.view');
    Route::post('/documents', [VendorDocumentController::class, 'store'])->middleware('permission:vendor.documents.create');
    Route::delete('/documents/{id}', [VendorDocumentController::class, 'destroy'])->middleware('permission:vendor.documents.delete');

    // ==================== VENDOR INVENTORY ====================
    Route::get('/inventory/{vendor_id}', [VendorInventoryController::class, 'inventory'])->middleware('permission:vendor.inventory.view');
    Route::get('/products/low-stock/{vendor_id}', [VendorInventoryController::class, 'lowStock'])->middleware('permission:vendor.inventory.view');

    // ==================== VENDOR PRODUCTS ====================
    Route::get('/products', [VendorProductController::class, 'index'])->middleware('permission:product.view');
    Route::post('/products', [VendorProductController::class, 'store'])->middleware('permission:product.create');
    Route::get('/products/{id}/view', [VendorProductController::class, 'getProductById'])->middleware('permission:product.show');
    Route::put('/products/{id}', [VendorProductController::class, 'updateProduct'])->middleware('permission:product.update');
    Route::delete('/products/{id}', [VendorProductController::class, 'deleteProduct'])->middleware('permission:product.delete');
    Route::post('/products/{id}/stock', [VendorProductController::class, 'updateStock'])->middleware('permission:product.update');

    // ==================== VENDOR PRODUCT IMAGES ====================
    Route::post('/products/{id}/images', [ProductImageController::class, 'store'])->middleware('permission:product.create');
    Route::delete('/products/images/{id}', [ProductImageController::class, 'deleteProductImage'])->middleware('permission:product.delete');

    // ==================== VENDOR PRODUCT VARIANTS ====================
    Route::post('/products/{id}/variants', [VendorVariantController::class, 'store'])->middleware('permission:variant.create');
    Route::put('/product-variants/{id}', [VendorVariantController::class, 'updateVariant'])->middleware('permission:variant.update');
    Route::delete('/product-variants/{id}', [VendorVariantController::class, 'deleteVariant'])->middleware('permission:variant.delete');
    Route::get('/variants/{id}', [VendorVariantController::class, 'getVariant'])->middleware('permission:variant.view');

    // ==================== VENDOR COUPONS ====================
    Route::get('/coupons', [VendorCouponController::class, 'index'])->middleware('permission:coupon.view');
    Route::get('/coupons/stats', [VendorCouponController::class, 'stats'])->middleware('permission:coupon.view');
    Route::post('/coupons', [VendorCouponController::class, 'store'])->middleware('permission:coupon.create');
    Route::get('/coupons/{id}', [VendorCouponController::class, 'show'])->middleware('permission:coupon.view');
    Route::put('/coupons/{id}', [VendorCouponController::class, 'update'])->middleware('permission:coupon.update');
    Route::delete('/coupons/{id}', [VendorCouponController::class, 'destroy'])->middleware('permission:coupon.delete');
    Route::put('/coupons/{id}/toggle-status', [VendorCouponController::class, 'toggleStatus'])->middleware('permission:coupon.update');
    Route::get('/coupons/{id}/usages', [VendorCouponController::class, 'usages'])->middleware('permission:coupon.view');

    // ==================== VENDOR WALLET ====================
    Route::get('/wallet', [VendorWalletController::class, 'wallet'])->middleware('permission:wallet.view');
    Route::get('/wallet/transactions', [VendorWalletController::class, 'transactions'])->middleware('permission:wallet.view');
    Route::get('/wallet/settlements', [VendorWalletController::class, 'settlements'])->middleware('permission:wallet.view');
    Route::get('/wallet/earnings-summary', [VendorWalletController::class, 'earningsSummary'])->middleware('permission:wallet.view');
    Route::get('/wallet/statement', [VendorWalletController::class, 'statement'])->middleware('permission:wallet.view');
    Route::post('/wallet/withdraw', [VendorWalletController::class, 'withdraw'])->middleware('permission:wallet.withdraw');
    Route::get('/withdrawals', [VendorWalletController::class, 'withdrawals'])->middleware('permission:wallet.view');

    // ==================== VENDOR PAYOUT ====================
    Route::get('/payout/dashboard', [VendorPayoutController::class, 'dashboard'])->middleware('permission:wallet.view');
    Route::get('/payout/history', [VendorPayoutController::class, 'history'])->middleware('permission:wallet.view');
    Route::get('/payout/{id}', [VendorPayoutController::class, 'show'])->middleware('permission:wallet.view');
    Route::get('/payout/earnings/report', [VendorPayoutController::class, 'earningsReport'])->middleware('permission:wallet.view');
    Route::get('/payout/tax/summary', [VendorPayoutController::class, 'taxSummary'])->middleware('permission:wallet.view');
    Route::get('/payout/statement/download', [VendorPayoutController::class, 'downloadStatement'])->middleware('permission:wallet.view');
    Route::post('/payout/early-settlement', [VendorPayoutController::class, 'requestEarlySettlement'])->middleware('permission:wallet.withdraw');

    // ==================== VENDOR ORDERS ====================
    Route::get('/orders/summary', [VendorOrderController::class, 'summary'])->middleware('permission:order.view');
    Route::get(
        '/orders/{order_id}/items',
        [VendorOrderController::class, 'getVendorOrderItems']
    )->middleware('permission:order.view');
    Route::get('/orders', [VendorOrderController::class, 'orders'])->middleware('permission:order.view');
    Route::get('/orders/{order_id}', [VendorOrderController::class, 'show'])->middleware('permission:order.view');
    Route::post('/orders/{order_id}/shipment', [VendorOrderController::class, 'createShipment'])->middleware('permission:order.shipment');
    Route::match(['put', 'patch'], '/order-items/{id}/status', [VendorOrderController::class, 'updateItemStatus'])->middleware('permission:order.update');
    Route::post('/orders/bulk-status', [VendorOrderController::class, 'bulkUpdateStatus'])->middleware('permission:order.update');
    Route::get('/shipments', [VendorOrderController::class, 'shipments'])->middleware('permission:order.view');
    Route::put('/shipments/{id}', [VendorOrderController::class, 'updateShipment'])->middleware('permission:order.update');
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (With permission middleware)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // ==================== ADMIN DASHBOARD ====================
    Route::prefix('dashboard')->group(function () {
        // Main stats
        Route::get('/stats', [AdminDashboardController::class, 'stats'])
            ->middleware('permission:dashboard.view');

        // Vendor management
        Route::get('/pending-vendors', [AdminDashboardController::class, 'pendingVendors'])
            ->middleware('permission:vendor.view');

        // Charts & Analytics
        Route::get('/revenue-chart', [AdminDashboardController::class, 'revenueChart'])
            ->middleware('permission:dashboard.view');

        Route::get('/orders-chart', [AdminDashboardController::class, 'ordersChart'])
            ->middleware('permission:dashboard.view');

        // Performance metrics
        Route::get('/top-vendors', [AdminDashboardController::class, 'topVendors'])
            ->middleware('permission:dashboard.view');

        Route::get('/recent-activities', [AdminDashboardController::class, 'recentActivities'])
            ->middleware('permission:dashboard.view');

        // Route::prefix('dashboard')->group(function () {
        //     Route::get('/stats', [AdminDashboardController::class, 'stats'])->middleware('permission:dashboard.view');
        //     Route::get('/revenue-chart', [AdminDashboardController::class, 'revenueChart'])->middleware('permission:dashboard.view');
        //     Route::get('/orders-chart', [AdminDashboardController::class, 'ordersChart'])->middleware('permission:dashboard.view');
    });
    Route::get('/vendors/pending', [AdminDashboardController::class, 'pendingVendors'])->middleware('permission:vendor.view');

    // ==================== ADMIN PRODUCT MANAGEMENT ====================
    Route::get('/products', [AdminProductController::class, 'index'])->middleware('permission:product.view');
    Route::get('/products/{id}', [AdminProductController::class, 'show'])->middleware('permission:product.view');
    Route::post('/products/{id}/toggle-approval', [AdminProductController::class, 'toggleApproval'])->middleware('permission:product.approve');
    Route::post('/products/{id}/toggle-status', [AdminProductController::class, 'toggleStatus'])->middleware('permission:product.update');
    Route::post('/products/bulk-toggle-approval', [AdminProductController::class, 'bulkToggleApproval'])->middleware('permission:product.approve');
    Route::get('/products/pending/approvals', [AdminProductController::class, 'pendingApprovals'])->middleware('permission:product.view');
    Route::get('/products/statistics/summary', [AdminProductController::class, 'statistics'])->middleware('permission:product.view');
    Route::put('/products/{id}/commission', [AdminProductController::class, 'updateCommission'])->middleware('permission:commission.update');
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy'])->middleware('permission:product.delete');

    // ==================== ADMIN CATEGORY MANAGEMENT ====================
    Route::get('/categories', [AdminCategoryController::class, 'index'])->middleware('permission:category.view');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware('permission:category.create');
    Route::post('/subcategories', [AdminCategoryController::class, 'storeSubcategory'])->middleware('permission:category.create');
    Route::put('/categories/{id}', [AdminCategoryController::class, 'updateCategory'])->middleware('permission:category.update');
    Route::put('/subcategories/{id}', [AdminCategoryController::class, 'updateSubcategory'])->middleware('permission:category.update');
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'deleteCategory'])->middleware('permission:category.delete');
    Route::delete('/subcategories/{id}', [AdminCategoryController::class, 'deleteSubcategory'])->middleware('permission:category.delete');

    // ==================== ADMIN ATTRIBUTE MANAGEMENT ====================

    Route::post('/attributes', [AdminAttributeController::class, 'store'])->middleware('permission:attribute.create');
    Route::get('/attributes-with-values', [AdminAttributeController::class, 'indexWithValues'])->middleware('permission:attributevalues.view');
    Route::put('/attributes/{id}', [AdminAttributeController::class, 'updateAttribute'])->middleware('permission:attribute.update');
    Route::delete('/attributes/{id}', [AdminAttributeController::class, 'deleteAttribute'])->middleware('permission:attribute.delete');
    Route::post('/attributes/{id}/values', [AdminAttributeValueController::class, 'store'])->middleware('permission:attribute.create');
    Route::put('/attribute-values/{id}', [AdminAttributeValueController::class, 'updateAttributeValue'])->middleware('permission:attribute.update');
    Route::delete('/attribute-values/{id}', [AdminAttributeValueController::class, 'deleteAttributeValue'])->middleware('permission:attribute.delete');

    // ==================== ADMIN BRAND MANAGEMENT ====================
    Route::prefix('brands')->group(function () {
        Route::post('/', [AdminBrandController::class, 'store'])->middleware('permission:brand.create');
        Route::get('/{brand}', [AdminBrandController::class, 'show'])->middleware('permission:brand.view');
        Route::put('/{brand}', [AdminBrandController::class, 'update'])->middleware('permission:brand.update');
        Route::delete('/{brand}', [AdminBrandController::class, 'destroy'])->middleware('permission:brand.delete');
    });

    // ==================== ADMIN COMMISSION MANAGEMENT ====================
    Route::get('/commissions/dashboard', [AdminCommissionController::class, 'dashboard'])->middleware('permission:commission.view');
    Route::get('/commissions/vendors', [AdminCommissionController::class, 'vendors'])->middleware('permission:commission.view');
    Route::get('/commissions/vendors/{id}', [AdminCommissionController::class, 'showVendor'])->middleware('permission:commission.view');
    Route::post('/commissions/vendors/{id}', [AdminCommissionController::class, 'updateVendorCommission'])->middleware('permission:commission.update');
    Route::post('/commissions/products/{id}', [AdminCommissionController::class, 'updateProductCommission'])->middleware('permission:commission.update');
    Route::get('/commissions/report', [AdminCommissionController::class, 'report'])->middleware('permission:commission.view');
    Route::post('/commissions/settle', [AdminCommissionController::class, 'settleCommission'])->middleware('permission:commission.settle');
    Route::get('/commissions/settlements', [AdminCommissionController::class, 'settlements'])->middleware('permission:commission.view');
    Route::get('/commissions/settlements/{id}', [AdminCommissionController::class, 'showSettlement'])->middleware('permission:commission.view');
    Route::put('/commissions/settlements/{id}', [AdminCommissionController::class, 'updateSettlement'])->middleware('permission:commission.settle');

    // ==================== ADMIN WITHDRAWAL MANAGEMENT ====================
    Route::get('/withdraw-requests', [AdminWithdrawController::class, 'getWithdrawRequests'])->middleware('permission:withdrawal.view');
    Route::get('/withdraw-requests/{id}', [AdminWithdrawController::class, 'getWithdrawRequest'])->middleware('permission:withdrawal.view');
    Route::put('/withdraw-requests/{id}/approve', [AdminWithdrawController::class, 'approve'])->middleware('permission:withdrawal.approve');
    Route::put('/withdraw-requests/{id}/reject', [AdminWithdrawController::class, 'reject'])->middleware('permission:withdrawal.approve');

    // ==================== ADMIN BANNER MANAGEMENT ====================
    Route::get('/banners/{id}', [AdminBannerController::class, 'getBanner'])->middleware('permission:banner.view');
    Route::get('/banners', [AdminBannerController::class, 'getBanners'])->middleware('permission:banner.view');
    Route::post('/banners', [AdminBannerController::class, 'store'])->middleware('permission:banner.create');
    Route::put('/banners/{id}', [AdminBannerController::class, 'updateBanner'])->middleware('permission:banner.update');
    Route::delete('/banners/{id}', [AdminBannerController::class, 'deleteBanner'])->middleware('permission:banner.delete');

    // ==================== ADMIN ANALYTICS ====================
    Route::prefix('analytics')->group(function () {
        Route::get('/sales', [AdminAnalyticsController::class, 'sales'])->middleware('permission:analytics.view');
        Route::get('/orders', [AdminAnalyticsController::class, 'orders'])->middleware('permission:analytics.view');
        Route::get('/vendors', [AdminAnalyticsController::class, 'vendors'])->middleware('permission:analytics.view');
        Route::get('/products', [AdminAnalyticsController::class, 'products'])->middleware('permission:analytics.view');
        Route::get('/customers', [AdminAnalyticsController::class, 'customers'])->middleware('permission:analytics.view');
    });

    // ==================== ADMIN REPORTS ====================
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [AdminReportController::class, 'sales'])->middleware('permission:report.view');
        Route::get('/vendor-sales', [AdminReportController::class, 'vendorSales'])->middleware('permission:report.view');
        Route::get('/product-sales', [AdminReportController::class, 'productSales'])->middleware('permission:report.view');
        Route::get('/customers', [AdminReportController::class, 'customers'])->middleware('permission:report.view');
    });

    // ==================== ADMIN SUPPORT MANAGEMENT ====================
    Route::prefix('support')->group(function () {
        Route::get('/', [AdminSupportController::class, 'index'])->middleware('permission:support.view');
        Route::get('/{id}', [AdminSupportController::class, 'show'])->middleware('permission:support.view');
        Route::post('/{id}/reply', [AdminSupportController::class, 'reply'])->middleware('permission:support.reply');
        Route::patch('/{id}/status', [AdminSupportController::class, 'updateStatus'])->middleware('permission:support.update');
    });

    // ==================== ADMIN ROLE MANAGEMENT ====================
    Route::get('/roles', [AdminRoleController::class, 'index'])->middleware('permission:role.view');
    Route::get('/roles/{id}', [AdminRoleController::class, 'show'])->middleware('permission:role.view');
    Route::post('/roles', [AdminRoleController::class, 'store'])->middleware('permission:role.create');
    Route::put('/roles/{id}', [AdminRoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy'])->middleware('permission:role.delete');

    // ==================== ADMIN PERMISSION MANAGEMENT ====================
    Route::get('/permissions', [AdminPermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::get('/permissions/{id}', [AdminPermissionController::class, 'show'])->middleware('permission:permission.view');
    Route::post('/permissions', [AdminPermissionController::class, 'store'])->middleware('permission:permission.create');
    Route::put('/permissions/{id}', [AdminPermissionController::class, 'update'])->middleware('permission:permission.update');
    Route::delete('/permissions/{id}', [AdminPermissionController::class, 'destroy'])->middleware('permission:permission.delete');

    // ==================== ADMIN USER MANAGEMENT ====================
    Route::get('/users-with-roles', [AdminUserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users/{id}/assign-role', [AdminUserController::class, 'assignRole'])->middleware('permission:user.assign-role');
    Route::get('/users/profiles', [ProfileController::class, 'getProfiles'])->middleware('permission:profiles.view');
    
    Route::get('/users/{id}', [AdminUserController::class, 'show'])
        ->middleware('permission:user.view');
    Route::post('/users', [AdminUserController::class, 'store'])
        ->middleware('permission:user.create');
    Route::put('/users/{id}', [AdminUserController::class, 'update'])
        ->middleware('permission:user.update');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:user.delete');
    Route::post('/users/bulk-status', [AdminUserController::class, 'bulkUpdateStatus'])
        ->middleware('permission:user.update');


    // ==================== ADMIN VENDOR MANAGEMENT ====================
    Route::get('/vendors', [AdminVendorController::class, 'index'])->middleware('permission:vendor.view');
    Route::get('/vendors/{id}', [AdminVendorController::class, 'show'])->middleware('permission:vendor.view');
    Route::put('/vendors/{id}/approve', [AdminVendorController::class, 'approve'])->middleware('permission:vendor.approve');
    Route::put('/vendors/{id}/reject', [AdminVendorController::class, 'reject'])->middleware('permission:vendor.approve');
    Route::put('/vendors/{id}/suspend', [AdminVendorController::class, 'suspend'])->middleware('permission:vendor.update');
    Route::get('/vendors/{id}/documents', [AdminVendorDocumentController::class, 'index'])->middleware('permission:vendor.view');
    Route::put('/documents/{id}/verify', [AdminVendorDocumentController::class, 'verify'])->middleware('permission:vendor.approve');
    Route::put('/documents/{id}/reject', [AdminVendorDocumentController::class, 'reject'])->middleware('permission:vendor.approve');

    // ==================== ADMIN ORDER MANAGEMENT ====================
    Route::get('/orders/summary', [AdminOrderController::class, 'summary'])->middleware('permission:order.view');
    Route::get('/orders', [AdminOrderController::class, 'index'])->middleware('permission:order.view');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->middleware('permission:order.view');
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:order.update');
    Route::get('/orders/{id}/shipments', [AdminOrderController::class, 'getOrderShipments'])->middleware('permission:adminshipment.view');
    Route::post('/orders/marketplace-shipment', [AdminOrderController::class, 'createMarketplaceShipment'])->middleware('permission:adminshipment.create');
    Route::put('/shipments/{id}/status', [AdminOrderController::class, 'updateShipmentStatus'])->middleware('permission:adminshipment.update');

    Route::get('/orders/revenue', [AdminOrderController::class, 'revenueByDate'])->middleware('permission:order.view');
    Route::put('/orders/{id}/payment-status', [AdminOrderController::class, 'updatePaymentStatus'])->middleware('permission:order.update');

    // ==================== ADMIN SETTINGS ====================
    Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('permission:settings.view');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.update');

    // ==================== ADMIN LOGS ====================
    Route::get('/logs', [AdminLogController::class, 'index'])->middleware('permission:log.view');

    // ==================== ADMIN COUPON MANAGEMENT ====================
    Route::get('/coupons', [AdminCouponController::class, 'index'])->middleware('permission:coupon.view');
    Route::get('/coupons/stats', [AdminCouponController::class, 'stats'])->middleware('permission:coupon.view');
    Route::get('/coupons/analytics', [AdminCouponController::class, 'analytics'])->middleware('permission:coupon.view');
    Route::get('/coupons/export', [AdminCouponController::class, 'export'])->middleware('permission:coupon.view');
    Route::post('/coupons', [AdminCouponController::class, 'store'])->middleware('permission:coupon.create');
    Route::post('/coupons/bulk', [AdminCouponController::class, 'bulkAction'])->middleware('permission:coupon.update');
    Route::get('/coupons/{id}', [AdminCouponController::class, 'show'])->middleware('permission:coupon.view');
    Route::put('/coupons/{id}', [AdminCouponController::class, 'update'])->middleware('permission:coupon.update');
    Route::delete('/coupons/{id}', [AdminCouponController::class, 'destroy'])->middleware('permission:coupon.delete');
    // Admin coupon toggle status
    Route::put('/coupons/{id}/toggle-status', [AdminCouponController::class, 'toggleStatus'])->middleware('permission:coupon.toggle');
});