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

/*
|--------------------------------------------------------------------------
| API Routes - ALL ROUTES HAVE PERMISSION MIDDLEWARE
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/products', [AdminProductController::class, 'index']);
    
    // ✅ Single toggle endpoint for approve/reject
    Route::post('/products/{id}/toggle-approval', [AdminProductController::class, 'toggleApproval']);
    
    // Separate endpoint for active/inactive status
    Route::put('/products/{id}/toggle-status', [AdminProductController::class, 'toggleStatus']);
    
    // Bulk operations
    Route::post('/products/bulk-toggle-approval', [AdminProductController::class, 'bulkToggleApproval']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/notifications', [AdminProductController::class, 'notifications']);

    Route::get('/notifications/unread-count', [AdminProductController::class, 'unreadCount']);

    Route::post('/notifications/{id}/read', [AdminProductController::class, 'markAsRead']);

    Route::post('/notifications/read-all', [AdminProductController::class, 'markAllAsRead']);
});

// ==================== PUBLIC AUTH ROUTES (No permission required) ====================
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

// ==================== PUBLIC VIEW ROUTES (With view permissions) ====================
Route::get('profile/{id}', [ProfileController::class, 'getProfileById'])->middleware('permission:profile.view');
Route::get('addresses', [AddressController::class, 'getAddresses'])->middleware('permission:address.view');
Route::get('carts', [CartController::class, 'getCarts'])->middleware('permission:cart.view');
Route::get('wishlists', [WishlistController::class, 'getWishlists'])->middleware('permission:wishlist.view');

// ==================== CART ROUTES (With cart permissions) ====================
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart', [CartController::class, 'getCart']);
Route::put('/cart/{id}', [CartController::class, 'updateCartItem']);
Route::delete('/cart-item/{id}', [CartController::class, 'deleteCartItem']);
Route::delete('/cart/clear', [CartController::class, 'clearCart']);

// ==================== CATEGORY ROUTES (With category permissions) ====================
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/children', [CategoryController::class, 'children']);
    Route::get('/{slug}/products', [CategoryController::class, 'products']);
});

// ==================== PRODUCT ROUTES (With product permissions) ====================
// 🔓 PUBLIC ROUTES
Route::prefix('products')->group(function () {

    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/latest', [ProductController::class, 'latest']);
    Route::get('/deals', [ProductController::class, 'deals']);
    Route::get('/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('/', [ProductController::class, 'index']);

    Route::get('/related', [ProductController::class, 'relatedBulk']); // ✅ HERE

    Route::get('/{id}/related', [ProductController::class, 'related']);
    Route::get('/{id}/images', [ProductController::class, 'images']);
    Route::get('/{id}/reviews', [ProductController::class, 'reviews']);
    Route::get('/{id}/rating', [ProductController::class, 'rating']);

    Route::get('/{slug}', [ProductController::class, 'show']);
});

// ==================== VENDOR STORE ROUTES (With vendor view permissions) ====================
Route::prefix('vendors')->group(function () {
    Route::get('/', [VendorStoreController::class, 'index'])->middleware('permission:vendor.view');
    Route::get('/{slug}', [VendorStoreController::class, 'show'])->middleware('permission:vendor.view');
    Route::get('/{slug}/products', [VendorStoreController::class, 'products'])->middleware('permission:vendor.view');
    Route::get('/{slug}/reviews', [VendorStoreController::class, 'reviews'])->middleware('permission:vendor.view');
});

// ==================== COUPON ROUTES (With coupon permissions) ====================
Route::get('/coupon/available', [CouponController::class, 'availableCoupons'])->middleware('permission:coupon.view');
Route::post('/coupon/validate', [CouponController::class, 'validateCoupon'])->middleware('permission:coupon.view');

// ==================== VENDOR REGISTRATION (Public) ====================
Route::post('/vendor/register', [VendorRegisterController::class, 'register']);

// ==================== ADMIN BANNERS PUBLIC VIEW ====================
Route::get('admin/banners', [AdminBannerController::class, 'getBanners']);
 Route::get('/brands', [AdminBrandController::class, 'index']);

// ==================== AUTHENTICATED ROUTES (All require auth:sanctum + permissions) ====================
Route::middleware(['auth:sanctum'])->group(function () {

    // ==================== USER AUTH & PROFILE (With profile permissions) ====================
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('permission:auth.logout');
    Route::post('auth/refresh-token', [AuthController::class, 'refreshToken'])->middleware('permission:auth.refresh');
    Route::get('auth/me', [AuthController::class, 'me'])->middleware('permission:profile.view');
    Route::get('profile', [ProfileController::class, 'getProfile'])->middleware('permission:profile.view');
    Route::put('profile/update', [ProfileController::class, 'updateProfile'])->middleware('permission:profile.update');
    Route::put('profile/change-password', [AuthController::class, 'changePassword'])->middleware('permission:profile.update');
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('permission:profile.update');
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar'])->middleware('permission:profile.update');
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail'])->middleware('permission:auth.verify');

    // ==================== ADDRESS MANAGEMENT (With address permissions) ====================
    Route::get('/address', [AddressController::class, 'getAddress'])->middleware('permission:address.view');
    Route::post('/address', [AddressController::class, 'store'])->middleware('permission:address.create');
    Route::put('/address/{id}', [AddressController::class, 'updateAddress'])->middleware('permission:address.update');
    Route::delete('/addresses/{id}', [AddressController::class, 'deleteAddress'])->middleware('permission:address.delete');
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefaultAddress'])->middleware('permission:address.update');

    // ==================== WISHLIST (With wishlist permissions) ====================
    Route::get('wishlist', [WishlistController::class, 'getWishlist']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{id}', [WishlistController::class, 'remove']);
    // ==================== CHECKOUT & ORDERS (With order permissions) ====================
    Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
    Route::get('/checkout/data', [CheckoutController::class, 'data']);
    Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
    Route::post('/place-order', [CheckoutController::class, 'checkout']);

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('permission:order.view');
        Route::get('/{id}', [OrderController::class, 'show'])->middleware('permission:order.view');
        Route::get('/{id}/track', [OrderController::class, 'track'])->middleware('permission:order.view');
        Route::get('/{id}/shipment', [OrderController::class, 'shipment'])->middleware('permission:order.view');
        Route::get('/{id}/tracking', [OrderController::class, 'tracking'])->middleware('permission:order.view');
        Route::get('/{id}/invoice', [OrderController::class, 'invoice'])->middleware('permission:order.view');
        Route::get('/{id}/status-history', [OrderController::class, 'statusHistory'])->middleware('permission:order.view');
        Route::post('/', [OrderController::class, 'store'])->middleware('permission:order.create');
        Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->middleware('permission:order.update');
        Route::post('/{id}/return', [OrderController::class, 'return'])->middleware('permission:order.update');
        Route::post('/{id}/exchange', [OrderController::class, 'exchange'])->middleware('permission:order.update');
    });

    // ==================== PAYMENT (With payment permissions) ====================
    Route::post('/payment/create-order', [PaymentController::class, 'createOrder'])->middleware('permission:payment.create');
    Route::post('/payment/verify', [PaymentController::class, 'verify'])->middleware('permission:payment.update');
    Route::get('/payment/status/{order_id}', [PaymentController::class, 'status'])->middleware('permission:payment.view');

    // ==================== WALLET (With wallet permissions) ====================
    Route::get('/wallet', [WalletController::class, 'wallet'])->middleware('permission:wallet.view');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->middleware('permission:wallet.view');
    Route::get('/vendor/wallet', [VendorWalletController::class, 'wallet'])->middleware('permission:wallet.view');
    Route::get('/vendor/wallet/transactions', [VendorWalletController::class, 'transactions'])->middleware('permission:wallet.view');

    // ==================== REVIEWS (With review permissions) ====================
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('permission:review.create');
    Route::put('/reviews/{id}', [ReviewController::class, 'updateReview'])->middleware('permission:review.update');
    Route::delete('/reviews/{id}', [ReviewController::class, 'deleteReview'])->middleware('permission:review.delete');

    // ==================== COUPON (With coupon permissions) ====================
    Route::post('/coupon/apply', [CouponController::class, 'applyCoupon'])->middleware('permission:coupon.apply');
    Route::post('/coupon/remove', [CouponController::class, 'removeCoupon'])->middleware('permission:coupon.apply');

    // ==================== VENDOR DASHBOARD & INVENTORY (With vendor permissions) ====================
    Route::prefix('vendor')->group(function () {
        Route::get('/dashboard', [VendorDashboardController::class, 'dashboard'])->middleware('permission:vendor.dashboard.view');
        Route::get('/dashboard/stats', [VendorDashboardController::class, 'stats'])->middleware('permission:vendor.dashboard.view');
        //Route::get('/orders/summary', [VendorDashboardController::class, 'ordersSummary'])->middleware('permission:vendor.dashboard.view');
        Route::get('/documents', [VendorDocumentController::class, 'index'])->middleware('permission:vendor.documents.view');
        Route::post('/documents', [VendorDocumentController::class, 'store'])->middleware('permission:vendor.documents.create');
        Route::get('/inventory/{vendor_id}', [VendorInventoryController::class, 'inventory'])->middleware('permission:vendor.inventory.view');
        Route::get('/products/low-stock/{vendor_id}', [VendorInventoryController::class, 'lowStock'])->middleware('permission:vendor.inventory.view');
    });

    // ==================== VENDOR CRUD ROUTES (With product permissions) ====================
    Route::post('/vendor/products', [VendorProductController::class, 'store'])->middleware('permission:product.create');
    Route::get('vendor/products/{id}', [VendorProductController::class, 'getProductById'])->middleware('permission:product.show');
    Route::put('vendor/products/{id}', [VendorProductController::class, 'updateProduct'])->middleware('permission:product.update');
    Route::delete('/vendor/products/{id}', [VendorProductController::class, 'deleteProduct'])->middleware('permission:product.delete');

    Route::post('/vendor/products/{id}/images', [ProductImageController::class, 'store'])->middleware('permission:product.create');
    Route::delete('/vendor/products/images/{id}', [ProductImageController::class, 'deleteProductImage'])->middleware('permission:product.delete');

    Route::post('/vendor/products/{id}/variants', [VendorVariantController::class, 'store'])->middleware('permission:variant.create');
    Route::put('/vendor/product-variants/{id}', [VendorVariantController::class, 'updateVariant'])->middleware('permission:variant.update');
    Route::delete('/vendor/product-variants/{id}', [VendorVariantController::class, 'deleteVariant'])->middleware('permission:variant.delete');

    // ==================== VENDOR COUPON MANAGEMENT (With coupon permissions) ====================
    Route::post('/vendor/coupons', [VendorCouponController::class, 'store'])->middleware('permission:coupon.create');
    Route::put('/vendor/coupons/{id}', [VendorCouponController::class, 'update'])->middleware('permission:coupon.update');
    Route::delete('/vendor/coupons/{id}', [VendorCouponController::class, 'destroy'])->middleware('permission:coupon.delete');
    Route::get('/vendor/coupons', [VendorCouponController::class, 'index'])
    ->middleware('permission:coupon.view');

Route::get('/vendor/coupons/{id}', [VendorCouponController::class, 'show'])
    ->middleware('permission:coupon.view');

    // ==================== VENDOR WALLET & ORDERS (With wallet and order permissions) ====================
    Route::post('/wallet/withdraw', [VendorWalletController::class, 'withdraw'])->middleware('permission:wallet.withdraw');
    //Route::post('vendor/orders/{id}/shipment', [VendorOrderController::class, 'createShipment'])->middleware('permission:order.shipment');
    Route::get('vendor/orders/summary', [VendorOrderController::class, 'summary']);
    Route::get('vendor/orders/', [VendorOrderController::class, 'orders']);
    Route::get('vendor/orders/{order_id}', [VendorOrderController::class, 'show']);
    Route::post('vendor/orders/{order_id}/shipment', [VendorOrderController::class, 'createShipment']);
    //Route::patch('vendor/order-items/{item_id}/status', [VendorOrderController::class, 'updateItemStatus']);
    Route::match(['put', 'patch'], '/vendor/order-items/{id}/status', [VendorOrderController::class, 'updateItemStatus']);

    // ==================== PRODUCT QUESTIONS (With question permissions) ====================
    Route::post('/products/{id}/questions', [ProductQuestionController::class, 'store'])->middleware('permission:question.create');
    Route::post('/products/questions/{id}/answer', [ProductQuestionController::class, 'answer'])->middleware('permission:question.answer');
    Route::get('/products/{id}/questions', [ProductQuestionController::class, 'index'])->middleware('permission:question.view');

    // ==================== SUPPORT TICKETS (With support permissions) ====================
    Route::prefix('support')->group(function () {
        Route::get('/tickets', [SupportController::class, 'index'])->middleware('permission:support.view');
        Route::post('/tickets', [SupportController::class, 'store'])->middleware('permission:support.create');
        Route::get('/tickets/{id}', [SupportController::class, 'show'])->middleware('permission:support.view');
        Route::post('/tickets/{id}/reply', [SupportController::class, 'reply'])->middleware('permission:support.update');
    });

    // ==================== ADMIN ROUTES (All with permission middleware) ====================
    Route::prefix('admin')->group(function () {

        // Admin Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [AdminDashboardController::class, 'stats'])->middleware('permission:dashboard.view');
            Route::get('/revenue-chart', [AdminDashboardController::class, 'revenueChart'])->middleware('permission:dashboard.view');
            Route::get('/orders-chart', [AdminDashboardController::class, 'ordersChart'])->middleware('permission:dashboard.view');
        });

        Route::get('/vendors/pending', [AdminDashboardController::class, 'pendingVendors'])->middleware('permission:vendor.view');

        // Admin Category Management
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::post('/subcategories', [AdminCategoryController::class, 'storeSubcategory']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'updateCategory'])->middleware('permission:category.update');
        Route::put('/subcategories/{id}', [AdminCategoryController::class, 'updateSubcategory'])->middleware('permission:category.update');
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'deleteCategory'])->middleware('permission:category.delete');
        Route::delete('/subcategories/{id}', [AdminCategoryController::class, 'deleteSubcategory'])->middleware('permission:category.delete');

        //Admin Attribute Management
        Route::post('/attributes', [AdminAttributeController::class, 'store']);
        Route::put('/attributes/{id}', [AdminAttributeController::class, 'updateAttribute']);
        Route::delete('/attributes/{id}', [AdminAttributeController::class, 'deleteAttribute']);
        Route::post('/attributes/{id}/values', [AdminAttributeValueController::class, 'store']);
        Route::put('/attribute-values/{id}', [AdminAttributeValueController::class, 'updateAttributeValue']);
        Route::delete('/attribute-values/{id}', [AdminAttributeValueController::class, 'deleteAttributeValue']);
       
        // Admin Commission Management
        Route::post('/commissions', [AdminCommissionController::class, 'store'])->middleware('permission:commission.create');
        Route::put('/vendors/{id}/commission', [AdminCommissionController::class, 'updateVendorCommission'])->middleware('permission:commission.update');

        // Admin Withdraw Request Management
        Route::get('/withdraw-requests', [AdminWithdrawController::class, 'getWithdrawRequests'])->middleware('permission:withdraw.view');
        Route::get('/withdraw-requests/{id}', [AdminWithdrawController::class, 'getWithdrawRequest'])->middleware('permission:withdraw.view');
        Route::put('/withdraw-requests/{id}/approve', [AdminWithdrawController::class, 'approve'])->middleware('permission:withdraw.approve');
        Route::put('/withdraw-requests/{id}/reject', [AdminWithdrawController::class, 'reject'])->middleware('permission:withdraw.reject');

        // Admin Banner Management
        Route::get('/banners/{id}', [AdminBannerController::class, 'getBanner']);
        Route::post('/banners', [AdminBannerController::class, 'store'])->middleware('permission:banner.create');
        Route::put('/banners/{id}', [AdminBannerController::class, 'updateBanner'])->middleware('permission:banner.update');
        Route::delete('/banners/{id}', [AdminBannerController::class, 'deleteBanner'])->middleware('permission:banner.delete');




        Route::prefix('brands')->group(function () {

     Route::get('/', [AdminBrandController::class, 'index']);
    Route::post('/', [AdminBrandController::class, 'store']);
    Route::put('/{brand}', [AdminBrandController::class, 'update']);
    Route::delete('/{brand}', [AdminBrandController::class, 'destroy']);
});

        // Admin Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/sales', [AdminAnalyticsController::class, 'sales'])->middleware('permission:analytics.view');
            Route::get('/orders', [AdminAnalyticsController::class, 'orders'])->middleware('permission:analytics.view');
            Route::get('/vendors', [AdminAnalyticsController::class, 'vendors'])->middleware('permission:analytics.view');
            Route::get('/products', [AdminAnalyticsController::class, 'products'])->middleware('permission:analytics.view');
        });

        // Admin Reports
        Route::prefix('reports')->group(function () {
            Route::get('/sales', [AdminReportController::class, 'sales'])->middleware('permission:report.view');
            Route::get('/vendor-sales', [AdminReportController::class, 'vendorSales'])->middleware('permission:report.view');
            Route::get('/product-sales', [AdminReportController::class, 'productSales'])->middleware('permission:report.view');
            Route::get('/customers', [AdminReportController::class, 'customers'])->middleware('permission:report.view');
        });

        // Admin Support Management
        Route::prefix('support')->group(function () {
            Route::get('/', [AdminSupportController::class, 'index'])->middleware('permission:support.view');
            Route::get('/{id}', [AdminSupportController::class, 'show'])->middleware('permission:support.view');
            Route::post('/{id}/reply', [AdminSupportController::class, 'reply'])->middleware('permission:support.reply');
            Route::patch('/{id}/status', [AdminSupportController::class, 'updateStatus'])->middleware('permission:support.update');
        });

        // Admin Role Management
        Route::get('/roles', [AdminRoleController::class, 'index'])->middleware('permission:role.view');
        Route::get('/roles/{id}', [AdminRoleController::class, 'show'])->middleware('permission:role.view');
        Route::post('/roles', [AdminRoleController::class, 'store'])->middleware('permission:role.create');
        Route::put('/roles/{id}', [AdminRoleController::class, 'update'])->middleware('permission:role.update');
        Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy'])->middleware('permission:role.delete');

        // Admin Permission Management
        Route::get('/permissions', [AdminPermissionController::class, 'index'])->middleware('permission:permission.view');
        Route::get('/permissions/{id}', [AdminPermissionController::class, 'show'])->middleware('permission:permission.view');
        Route::post('/permissions', [AdminPermissionController::class, 'store'])->middleware('permission:permission.create');
        Route::put('/permissions/{id}', [AdminPermissionController::class, 'update'])->middleware('permission:permission.update');
        Route::delete('/permissions/{id}', [AdminPermissionController::class, 'destroy'])->middleware('permission:permission.delete');

        // Admin User Management
        Route::get('/users-with-roles', [AdminUserController::class, 'index'])->middleware('permission:user.view');
        Route::post('/users/{id}/assign-role', [AdminUserController::class, 'assignRole'])->middleware('permission:user.assign-role');
        Route::get('/users/profiles', [ProfileController::class, 'getProfiles'])->middleware('permission:profiles.view');

        // Admin Vendor Management
        Route::get('/vendors', [AdminVendorController::class, 'index'])->middleware('permission:vendor.view');
        Route::get('/vendors/{id}', [AdminVendorController::class, 'show'])->middleware('permission:vendor.view');
        Route::put('/vendors/{id}/approve', [AdminVendorController::class, 'approve'])->middleware('permission:vendor.approve');
        Route::put('/vendors/{id}/reject', [AdminVendorController::class, 'reject'])->middleware('permission:vendor.approve');
        Route::put('/vendors/{id}/suspend', [AdminVendorController::class, 'suspend'])->middleware('permission:vendor.update');
        Route::get('/vendors/{id}/documents', [AdminVendorDocumentController::class, 'index'])->middleware('permission:vendor.view');
        Route::put('/documents/{id}/verify', [AdminVendorDocumentController::class, 'verify'])->middleware('permission:vendor.approve');
        Route::put('/documents/{id}/reject', [AdminVendorDocumentController::class, 'reject'])->middleware('permission:vendor.approve');      
        // Admin Order Management
        Route::get('/orders', [AdminOrderController::class, 'index'])->middleware('permission:order.view');
        // Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->middleware('permission:order.view');
        // Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:order.update');

         Route::get('/orders/summary', [AdminOrderController::class, 'summary']);
    // Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::get('/orders/revenue', [AdminOrderController::class, 'revenueByDate']);

        // Admin Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('permission:settings.view');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.update');

        // Admin Logs
        Route::get('/logs', [AdminLogController::class, 'index'])->middleware('permission:log.view');});});
Route::get('/admin/attributes-with-values', [AdminAttributeController::class, 'indexWithValues']);
// ==================== HEALTH CHECK (No permission required) ====================
Route::get('/health', function () {
    return response()->json(['status'=> 'ok', 'timestamp' => now()]);});
Route::get('/brands/active', [AdminBrandController::class, 'activeBrands']);
Route::get('/brands/category/{id}', [AdminBrandController::class, 'getBrandsByCategory']);
    Route::get('brands/{brand}', [AdminBrandController::class, 'show']);
