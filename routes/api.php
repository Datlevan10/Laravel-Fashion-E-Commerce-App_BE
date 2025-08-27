<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AdminController,
    CartController,
    CartDetailController,
    ProductController,
    CategoryController,
    CustomerController,
    EventController,
    NotificationController,
    OrderController,
    OrderDetailController,
    ProductFavoriteController,
    ReportReviewController,
    ReviewController,
    StaffController,
    StoreController,
    StoreSettingController,
    WidgetNoReviewSettingController,
    ProductStatisticController,
    RevenueStatisticController,
    CustomerStatisticController
};

Route::prefix('')->group(function () {
    // Store Routes Store and Store Settings
    Route::apiResource('stores', StoreController::class);
    Route::apiResource('store_settings', StoreSettingController::class);

    // Event Routes
    Route::prefix('events')->group(function () {
        Route::get('/is-active/active', [EventController::class, 'getActiveEvents']);
        Route::get('/is-active/inactive', [EventController::class, 'getInactiveEvents']);
        Route::put('/{event_id}/set-active', [EventController::class, 'activateEvent']);
        Route::put('/{event_id}/set-inactive', [EventController::class, 'deactivateEvent']);
    });
    Route::apiResource('events', EventController::class);


    // Category and Product Routes
    Route::prefix('categories')->group(function () {
        Route::get('/{limit}/limit', [CategoryController::class, 'getLimitedCategories']);
    });
    Route::apiResource('categories', CategoryController::class);


    Route::prefix('products')->group(function () {
        Route::get('/search', [ProductController::class, 'searchProducts']);
        Route::get('filter-by-stars', [ProductController::class, 'filterProductsByStars']);
        Route::get('filter-by-sizes', [ProductController::class, 'filterProductsBySizes']);
        Route::get('filter-by-total-reviews', [ProductController::class, 'filterProductsByTotalReviews']);
        Route::get('filter-by-average-reviews', [ProductController::class, 'filterProductsByAverageReviews']);
        Route::get('filter-by-price', [ProductController::class, 'filterProductsByPrice']);
        Route::get('category/{category_id}', [ProductController::class, 'getProductsByCategoryId']);
        Route::get('category/{category_id}/{limit}/limit', [ProductController::class, 'getLimitedProductsByCategoryId']);
        Route::get('{limit}/limit', [ProductController::class, 'getLimitedProducts']);
    });
    Route::apiResource('products', ProductController::class);

    // User Management Routes (Admin, Staff, Customer)
    Route::apiResource('admins', AdminController::class);
    Route::middleware('api')->post('admins/auth/login', [AdminController::class, 'authenticateLoginAdmin']);
    Route::middleware('auth:sanctum')->post('admins/auth/logout', [AdminController::class, 'logout']);
    Route::post('admins/auth/refresh-token', [AdminController::class, 'refreshAccessToken']);
    Route::apiResource('staffs', StaffController::class);
    Route::middleware('api')->post('staffs/auth/login', [StaffController::class, 'authenticateLoginStaff']);
    Route::middleware('auth:sanctum')->post('staffs/auth/logout', [StaffController::class, 'logout']);
    Route::post('staffs/auth/refresh-token', [StaffController::class, 'refreshAccessToken']);
    
    Route::apiResource('customers', CustomerController::class);
    Route::middleware('api')->post('customers/auth/login', [CustomerController::class, 'authenticateLoginCustomer']);
    Route::middleware('auth:sanctum')->post('customers/auth/logout', [CustomerController::class, 'logout']);
    Route::post('customers/auth/refresh-token', [CustomerController::class, 'refreshAccessToken']);
    // Route::middleware('auth:sanctum')->post('customers/auth/refresh-token', [CustomerController::class, 'refreshAccessToken']);
    Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
        return response()->json($request->user());
    });

    // Product favorite Routes
    Route::apiResource('product_favorites', ProductFavoriteController::class);
    Route::get('product_favorites/customer/{customer_id}', [ProductFavoriteController::class, 'getFavoriteProductByCustomerId']);

    // Cart and CartDetail Routes
    Route::prefix('carts')->group(function () {
        Route::get('/{cart_id}', [CartController::class, 'getCartByCartId']);
        Route::get('/all-carts/customer/{customer_id}', [CartController::class, 'getAllCartByCustomerId']);
        Route::get('/not-ordered-carts/customer/{customer_id}', [CartController::class, 'getNotOrderedCartByCustomerId']);
    });
    Route::apiResource('carts', CartController::class);

    Route::prefix('cart_details')->group(function () {
        Route::get('/cart/{cart_id}', [CartDetailController::class, 'getCartDetailByCartId']);
        Route::get('/customer/{customer_id}/all-cart-details', [CartDetailController::class, 'getAllCartDetailByCustomerId']);
        Route::get('/customer/{customer_id}/not-ordered-cart_details', [CartDetailController::class, 'getNotOrderedCartDetailByCustomerId']);
        Route::delete('/{cart_detail_id}/delete-item-in-cart', [CartDetailController::class, 'deleteItemInCart']);
    });
    Route::resource('cart_details', CartDetailController::class)->except(['destroy']);


    // Order and Order Detail Routes
    Route::prefix('orders')->group(function () {
        Route::put('/{order_id}/status', [OrderController::class, 'updateStatus']);
        Route::put('/{order_id}/cancel', [OrderController::class, 'cancel']);
        Route::get('/customer/{customer_id}', [OrderController::class, 'getOrdersByCustomer']);
        Route::get('/status/{status}', [OrderController::class, 'getOrdersByStatus']);
        Route::get('/history', [OrderController::class, 'getOrderHistory']);
        Route::put('/{order_id}/shipping-address', [OrderController::class, 'updateShippingAddress']);
        Route::put('/{order_id}/tracking', [OrderController::class, 'addTracking']);
        Route::post('/{order_id}/refund', [OrderController::class, 'processRefund']);
    });
    Route::apiResource('orders', OrderController::class);

    Route::apiResource('order_details', OrderDetailController::class);

    // Notification Routes
    Route::prefix('notifications')->group(function () {
        Route::post('/hide/{notification_id}', [NotificationController::class, 'hideNotification']);
    });
    Route::apiResource('notifications', NotificationController::class);

    // Review Routes
    Route::prefix('reviews')->group(function () {
        Route::post('/delete-review', [ReviewController::class, 'destroyMany']);
        Route::get('/product/{product_id}', [ReviewController::class, 'getReviewsByProductId']);
        Route::get('/product/{product_id}/limit', [ReviewController::class, 'getReviewsByProductIdLimit']);
        Route::get('/status/pending', [ReviewController::class, 'getPendingReviews']);
        Route::get('/status/approved', [ReviewController::class, 'getApprovedReviews']);
        Route::get('/status/unpublished', [ReviewController::class, 'getUnpublishedReviews']);
        Route::put('/{review_id}/publish', [ReviewController::class, 'publishReview']);
        Route::put('/{review_id}/unpublish', [ReviewController::class, 'unpublishReview']);
        Route::put('/{review_id}/reply', [ReviewController::class, 'replyReview']);
        Route::put('/{review_id}/feature', [ReviewController::class, 'featureReview']);
        Route::put('/{review_id}/unfeature', [ReviewController::class, 'unFeatureReview']);
        Route::get('/filter-by-most-helpful', [ReviewController::class, 'filterReviewByMostHelpful']);
        Route::get('/filter-by-star', [ReviewController::class, 'filterReviewsByStar']);
        Route::get('/filter-by-highest', [ReviewController::class, 'filterReviewByHighest']);
        Route::get('/filter-by-lowest', [ReviewController::class, 'filterReviewByLowest']);
        Route::get('/filter-by-newest', [ReviewController::class, 'filterReviewByNewest']);
        Route::get('/filter-by-oldest', [ReviewController::class, 'filterReviewByOldest']);
        Route::get('/filter-by-media', [ReviewController::class, 'filterReviewByMedia']);
        Route::post('/post-helpful-count', [ReviewController::class, 'postHelpfulCount']);
    });
    Route::apiResource('reviews', ReviewController::class);

    // Report Review Routes
    Route::prefix('report_reviews')->group(function () {
        Route::put('/{report_id}/reply', [ReportReviewController::class, 'replyReport']);
    });
    Route::apiResource('report_reviews', ReportReviewController::class);

    // Settings Widget No Reviews Routes
    Route::prefix('widget_no_reviews_settings')->group(function () {
        Route::post('/{no_review_setting_id}/reset', [WidgetNoReviewSettingController::class, 'resetDefaultSettings']);
    });
    Route::apiResource('widget_no_reviews_settings', WidgetNoReviewSettingController::class);

    // Statistics Routes
    
    // Product Statistics Routes
    Route::prefix('product-statistics')->group(function () {
        Route::get('/product/{product_id}', [ProductStatisticController::class, 'getByProductId']);
        Route::get('/top-selling/{limit?}', [ProductStatisticController::class, 'getTopSellingProducts']);
        Route::get('/top-revenue/{limit?}', [ProductStatisticController::class, 'getTopRevenueProducts']);
        Route::put('/{product_statistic_id}', [ProductStatisticController::class, 'updateStatistic']);
    });
    Route::apiResource('product-statistics', ProductStatisticController::class);

    // Revenue Statistics Routes
    Route::prefix('revenue-statistics')->group(function () {
        Route::get('/period', [RevenueStatisticController::class, 'getByPeriod']);
        Route::get('/total', [RevenueStatisticController::class, 'getTotalRevenue']);
    });
    Route::apiResource('revenue-statistics', RevenueStatisticController::class);

    // Customer Statistics Routes
    Route::prefix('customer-statistics')->group(function () {
        Route::get('/customer/{customer_id}', [CustomerStatisticController::class, 'getByCustomerId']);
        Route::get('/top-spenders/{limit?}', [CustomerStatisticController::class, 'getTopSpenders']);
        Route::get('/loyalty-tier/{loyalty_tier}', [CustomerStatisticController::class, 'getByLoyaltyTier']);
        Route::put('/{customer_statistic_id}', [CustomerStatisticController::class, 'updateStatistic']);
    });
    Route::apiResource('customer-statistics', CustomerStatisticController::class);

    // Admin Dashboard Routes
    Route::prefix('admin/dashboard')->group(function () {
        Route::get('/customers/total', [AdminController::class, 'getTotalCustomers']);
        Route::get('/categories/total', [AdminController::class, 'getTotalCategories']);
        Route::get('/staff/total', [AdminController::class, 'getTotalStaff']);
        Route::get('/products/total', [AdminController::class, 'getTotalProducts']);
        Route::get('/carts/total', [AdminController::class, 'getTotalCarts']);
        Route::get('/carts/active', [AdminController::class, 'getActiveCarts']);
        Route::get('/orders/statistics', [AdminController::class, 'getOrdersStatistics']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/orders/recent', [AdminController::class, 'getRecentOrders']);
        Route::get('/products/top-selling', [AdminController::class, 'getTopSellingProducts']);
    });

    // Authenticated User Route
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');
});
