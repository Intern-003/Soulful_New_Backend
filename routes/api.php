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

// Auth & public routes
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

Route::get('profiles', [ProfileController::class, 'getProfiles']);
Route::get('profile/{id}', [ProfileController::class, 'getProfileById']);
Route::get('addresses', [AddressController::class, 'getAddresses']);
Route::get('carts', [CartController::class, 'getCarts']);
Route::get('wishlists', [WishlistController::class, 'getWishlists']);

// Cart - PUBLIC routes
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart', [CartController::class, 'getCart']);

// Only update or delete requires auth
// Route::middleware('auth:sanctum')->group(function () {
    Route::put('/cart/{id}', [CartController::class, 'updateCartItem']);
    Route::delete('/cart-item/{id}', [CartController::class, 'deleteCartItem']);
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);
// });

// Categories
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/children', [CategoryController::class, 'children']);
    Route::get('/{slug}/products', [CategoryController::class, 'products']);
});

// Products
Route::prefix('products')->group(function () {
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/latest', [ProductController::class, 'latest']);
    Route::get('/deals', [ProductController::class, 'deals']);
    Route::get('/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}/related', [ProductController::class, 'related']);
    Route::get('/{id}/images', [ProductController::class, 'images']);
    Route::get('/{id}/reviews', [ProductController::class, 'reviews']);
    Route::get('/{id}/rating', [ProductController::class, 'rating']);
    Route::get('/{slug}', [ProductController::class, 'show']);
});

// Vendors
Route::prefix('vendors')->group(function () {
    Route::get('/', [VendorStoreController::class, 'index']);
    Route::get('/{slug}', [VendorStoreController::class, 'show']);
    Route::get('/{slug}/products', [VendorStoreController::class, 'products']);
    Route::get('/{slug}/reviews', [VendorStoreController::class, 'reviews']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // User Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile/update', [AuthController::class, 'updateProfile']);
    Route::put('profile/change-password', [AuthController::class, 'changePassword']);
    Route::post('profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('profile/avatar', [AuthController::class, 'deleteAvatar']);
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail']);

    // Address CRUD
    Route::get('/address', [AddressController::class, 'getAddress']);
    Route::post('/address', [AddressController::class, 'store']);
    Route::put('/address/{id}', [AddressController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [AddressController::class, 'deleteAddress']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefaultAddress']);

    // Cart
    // Route::post('/cart/add', [CartController::class, 'addToCart']);
    // Route::get('/cart', [CartController::class, 'getCart']);
    // Route::put('/cart/{id}', [CartController::class, 'updateCartItem']);
    // Route::delete('/cart-item/{id}', [CartController::class, 'deleteCartItem']);
    // Route::delete('/cart/clear', [CartController::class, 'clearCart']);

    // Wishlist
    Route::get('wishlist', [WishlistController::class, 'getWishlist']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{id}', [WishlistController::class, 'remove']);

    // Checkout & Orders
    Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
    Route::get('/checkout/data', [CheckoutController::class, 'data']);
    Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
    Route::post('/place-order', [CheckoutController::class, 'checkout']);

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/track', [OrderController::class, 'track']);
        Route::get('/{id}/shipment', [OrderController::class, 'shipment']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::get('/{id}/invoice', [OrderController::class, 'invoice']);
        Route::get('/{id}/status-history', [OrderController::class, 'statusHistory']);

        Route::post('/', [OrderController::class, 'store']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/return', [OrderController::class, 'return']);
        Route::post('/{id}/exchange', [OrderController::class, 'exchange']);
    });

    // Payment
    Route::post('/payment/create-order', [PaymentController::class, 'createOrder']);
    Route::post('/payment/verify', [PaymentController::class, 'verify']);
    Route::get('/payment/status/{order_id}', [PaymentController::class, 'status']);

    // Wallet
    Route::get('/wallet', [WalletController::class, 'wallet']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    Route::get('/vendor/wallet', [VendorWalletController::class, 'wallet']);
    Route::get('/vendor/wallet/transactions', [VendorWalletController::class, 'transactions']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'updateReview']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'deleteReview']);
});

// Coupon routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coupon/apply', [CouponController::class, 'applyCoupon']);
    Route::post('/coupon/remove', [CouponController::class, 'removeCoupon']);
});

Route::get('/coupon/available', [CouponController::class, 'availableCoupons']);
Route::post('/coupon/validate', [CouponController::class, 'validateCoupon']);

// Vendor Dashboard & Inventory
Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    Route::get('/dashboard', [VendorDashboardController::class, 'dashboard']);
    Route::get('/dashboard/stats', [VendorDashboardController::class, 'stats']);
    Route::get('/orders/summary', [VendorDashboardController::class, 'ordersSummary']);
    Route::get('/documents', [VendorDocumentController::class, 'index']);
    Route::post('/documents', [VendorDocumentController::class, 'store']);
    Route::get('/inventory/{vendor_id}', [VendorInventoryController::class, 'inventory']);
    Route::get('/products/low-stock/{vendor_id}', [VendorInventoryController::class, 'lowStock']);
});

// Vendor CRUD routes with permission checks
Route::middleware(['auth:sanctum', 'role:vendor'])->group(function () {
    Route::post('/vendor/products', [VendorProductController::class, 'store'])->middleware('permission:product.create');
    Route::put('/vendor/products/{id}', [VendorProductController::class, 'updateProduct'])->middleware('permission:product.update');
    Route::delete('/vendor/products/{id}', [VendorProductController::class, 'deleteProduct'])->middleware('permission:product.delete');

    Route::post('/vendor/products/{id}/images', [ProductImageController::class, 'store'])->middleware('permission:product.create');
    Route::delete('/vendor/products/images/{id}', [ProductImageController::class, 'deleteProductImage'])->middleware('permission:product.delete');

    Route::post('/vendor/products/{id}/variants', [VendorVariantController::class, 'store'])->middleware('permission:variant.create');
    Route::put('/vendor/product-variants/{id}', [VendorVariantController::class, 'updateVariant'])->middleware('permission:variant.update');
    Route::delete('/vendor/product-variants/{id}', [VendorVariantController::class, 'deleteVariant'])->middleware('permission:variant.delete');

    Route::post('/vendor/coupons', [VendorCouponController::class, 'store'])->middleware('permission:coupon.create');
    Route::put('/vendor/coupons/{id}', [VendorCouponController::class, 'update'])->middleware('permission:coupon.update');
    Route::delete('/vendor/coupons/{id}', [VendorCouponController::class, 'destroy'])->middleware('permission:coupon.delete');

    Route::post('/vendor/wallet/withdraw', [VendorWalletController::class, 'withdraw'])->middleware('permission:wallet.withdraw');
    Route::post('/vendor/orders/{id}/shipment', [VendorOrderController::class, 'createShipment'])->middleware('permission:order.shipment');

    Route::post('/products/{id}/questions', [ProductQuestionController::class, 'store'])->middleware('permission:question.create');
    Route::post('/products/questions/{id}/answer', [ProductQuestionController::class, 'answer'])->middleware('permission:question.answer');
    Route::get('/products/{id}/questions', [ProductQuestionController::class, 'index']);
});

// Admin routes with permission middleware
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Categories
    Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware('permission:category.create');
    Route::post('/subcategories', [AdminCategoryController::class, 'storeSubcategory'])->middleware('permission:category.create');
    Route::put('/categories/{id}', [AdminCategoryController::class, 'updateCategory'])->middleware('permission:category.update');
    Route::put('/subcategories/{id}', [AdminCategoryController::class, 'updateSubcategory'])->middleware('permission:category.update');
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'deleteCategory'])->middleware('permission:category.delete');
    Route::delete('/subcategories/{id}', [AdminCategoryController::class, 'deleteSubcategory'])->middleware('permission:category.delete');

    // Attributes
    Route::post('/attributes', [AdminAttributeController::class, 'store'])->middleware('permission:attribute.create');
    Route::put('/attributes/{id}', [AdminAttributeController::class, 'updateAttribute'])->middleware('permission:attribute.update');
    Route::delete('/attributes/{id}', [AdminAttributeController::class, 'deleteAttribute'])->middleware('permission:attribute.delete');

    Route::post('/attributes/{id}/values', [AdminAttributeValueController::class, 'store'])->middleware('permission:attribute.create');
    Route::put('/attribute-values/{id}', [AdminAttributeValueController::class, 'updateAttributeValue'])->middleware('permission:attribute.update');
    Route::delete('/attribute-values/{id}', [AdminAttributeValueController::class, 'deleteAttributeValue'])->middleware('permission:attribute.delete');

    // Commissions
    Route::post('/commissions', [AdminCommissionController::class, 'store'])->middleware('permission:commission.create');
    Route::put('/vendors/{id}/commission', [AdminCommissionController::class, 'updateVendorCommission'])->middleware('permission:commission.update');

    // Withdraw Requests
    Route::get('/withdraw-requests', [AdminWithdrawController::class, 'getWithdrawRequests'])->middleware('permission:withdraw.view');
    Route::get('/withdraw-requests/{id}', [AdminWithdrawController::class, 'getWithdrawRequest'])->middleware('permission:withdraw.view');
    Route::put('/withdraw-requests/{id}/approve', [AdminWithdrawController::class, 'approve'])->middleware('permission:withdraw.approve');
    Route::put('/withdraw-requests/{id}/reject', [AdminWithdrawController::class, 'reject'])->middleware('permission:withdraw.reject');

    // Banners
    Route::get('/banners', [AdminBannerController::class, 'getBanners']);
    Route::get('/banners/{id}', [AdminBannerController::class, 'getBanner']);
    Route::post('/banners', [AdminBannerController::class, 'store'])->middleware('permission:banner.create');
    Route::put('/banners/{id}', [AdminBannerController::class, 'updateBanner'])->middleware('permission:banner.update');
    Route::delete('/banners/{id}', [AdminBannerController::class, 'deleteBanner'])->middleware('permission:banner.delete');

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/sales', [AdminAnalyticsController::class, 'sales'])->middleware('permission:analytics.view');
        Route::get('/orders', [AdminAnalyticsController::class, 'orders'])->middleware('permission:analytics.view');
        Route::get('/vendors', [AdminAnalyticsController::class, 'vendors'])->middleware('permission:analytics.view');
        Route::get('/products', [AdminAnalyticsController::class, 'products'])->middleware('permission:analytics.view');
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [AdminDashboardController::class, 'stats'])->middleware('permission:dashboard.view');
        Route::get('/revenue-chart', [AdminDashboardController::class, 'revenueChart'])->middleware('permission:dashboard.view');
        Route::get('/orders-chart', [AdminDashboardController::class, 'ordersChart'])->middleware('permission:dashboard.view');
    });
    Route::get('/vendors/pending', [AdminDashboardController::class, 'pendingVendors'])->middleware('permission:vendor.view');

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [AdminReportController::class, 'sales'])->middleware('permission:report.view');
        Route::get('/vendor-sales', [AdminReportController::class, 'vendorSales'])->middleware('permission:report.view');
        Route::get('/product-sales', [AdminReportController::class, 'productSales'])->middleware('permission:report.view');
        Route::get('/customers', [AdminReportController::class, 'customers'])->middleware('permission:report.view');
    });

    // Support
    Route::prefix('support')->group(function () {
        Route::get('/', [AdminSupportController::class, 'index'])->middleware('permission:support.view');
        Route::get('/{id}', [AdminSupportController::class, 'show'])->middleware('permission:support.view');
        Route::post('/{id}/reply', [AdminSupportController::class, 'reply'])->middleware('permission:support.reply');
        Route::patch('/{id}/status', [AdminSupportController::class, 'updateStatus'])->middleware('permission:support.update');
    });

    // Roles & Permissions
    Route::get('/roles', [AdminRoleController::class, 'index'])->middleware('permission:role.view');
    Route::get('/roles/{id}', [AdminRoleController::class, 'show'])->middleware('permission:role.view');
    Route::post('/roles', [AdminRoleController::class, 'store'])->middleware('permission:role.create');
    Route::put('/roles/{id}', [AdminRoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::get('/permissions', [AdminPermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::get('/permissions/{id}', [AdminPermissionController::class, 'show'])->middleware('permission:permission.view');
    Route::post('/permissions', [AdminPermissionController::class, 'store'])->middleware('permission:permission.create');
    Route::put('/permissions/{id}', [AdminPermissionController::class, 'update'])->middleware('permission:permission.update');
    Route::delete('/permissions/{id}', [AdminPermissionController::class, 'destroy'])->middleware('permission:permission.delete');

    // Users
    Route::get('/users-with-roles', [AdminUserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users/{id}/assign-role', [AdminUserController::class, 'assignRole'])->middleware('permission:user.assign-role');


    //Logs
    Route::get('/logs', [AdminLogController::class, 'index'])->middleware('permission:log.view');
});
