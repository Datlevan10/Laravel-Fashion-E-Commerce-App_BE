<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\Cart;
use App\Models\OrderDetail;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminReportsController extends Controller
{
    /**
     * Get comprehensive dashboard statistics
     * GET /api/v1/admin/reports/dashboard
     */
    public function dashboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            // Get total customers
            $totalCustomers = Customer::count();
            
            // Get total staff
            $totalStaff = Staff::count();
            
            // Get total products
            $totalProducts = Product::count();
            
            // Get total carts
            $totalCarts = Cart::count();
            
            // Build order query for date range
            $orderQuery = Order::query();
            if ($startDate) {
                $orderQuery->where('order_date', '>=', $startDate);
            }
            if ($endDate) {
                $orderQuery->where('order_date', '<=', $endDate);
            }
            
            // Get order statistics
            $orderStats = $orderQuery->select('order_status', DB::raw('COUNT(*) as count'))
                ->groupBy('order_status')
                ->get()
                ->pluck('count', 'order_status')
                ->toArray();
            
            $totalOrders = array_sum($orderStats);
            
            // Calculate revenue (only confirmed/shipped/delivered with completed payments)
            $revenueQuery = Order::whereIn('order_status', ['confirmed', 'shipped', 'delivered'])
                ->whereHas('paymentTransactions', function ($q) {
                    $q->where('status', 'completed');
                });
                
            if ($startDate) {
                $revenueQuery->where('order_date', '>=', $startDate);
            }
            if ($endDate) {
                $revenueQuery->where('order_date', '<=', $endDate);
            }
            
            $totalRevenue = $revenueQuery->sum('total_price');
            $revenueOrderCount = $revenueQuery->count();
            $averageOrderValue = $revenueOrderCount > 0 ? $totalRevenue / $revenueOrderCount : 0;

            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_customers' => $totalCustomers,
                    'total_staff' => $totalStaff,
                    'total_products' => $totalProducts,
                    'total_carts' => $totalCarts,
                    'total_orders' => $totalOrders,
                    'order_statistics' => [
                        'pending' => $orderStats['pending'] ?? 0,
                        'confirmed' => $orderStats['confirmed'] ?? 0,
                        'shipped' => $orderStats['shipped'] ?? 0,
                        'delivered' => $orderStats['delivered'] ?? 0,
                        'cancelled' => $orderStats['cancelled'] ?? 0,
                    ],
                    'revenue' => [
                        'total_revenue' => round($totalRevenue, 2),
                        'total_orders' => $revenueOrderCount,
                        'average_order_value' => round($averageOrderValue, 2),
                        'currency' => 'VND'
                    ],
                    'date_range' => [
                        'start_date' => $startDate ?? Order::min('order_date'),
                        'end_date' => $endDate ?? Order::max('order_date'),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Failed to get dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product reports
     * GET /api/v1/admin/reports/products
     */
    public function products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:revenue,quantity,orders',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $limit = $request->limit ?? 10;
            $sortBy = $request->sort_by ?? 'revenue';

            // Build query for product sales
            $query = OrderDetail::select(
                'order_details.product_id',
                'order_details.product_name',
                DB::raw('COUNT(DISTINCT order_details.order_id) as order_count'),
                DB::raw('SUM(order_details.quantity) as total_quantity'),
                DB::raw('SUM(order_details.total_price) as total_revenue'),
                DB::raw('AVG(order_details.unit_price) as avg_price')
            )
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->whereIn('orders.order_status', ['confirmed', 'shipped', 'delivered'])
            ->whereHas('order.paymentTransactions', function ($q) {
                $q->where('status', 'completed');
            });

            if ($startDate) {
                $query->where('orders.order_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('orders.order_date', '<=', $endDate);
            }

            $query->groupBy('order_details.product_id', 'order_details.product_name');

            // Apply sorting
            switch ($sortBy) {
                case 'quantity':
                    $query->orderBy('total_quantity', 'desc');
                    break;
                case 'orders':
                    $query->orderBy('order_count', 'desc');
                    break;
                case 'revenue':
                default:
                    $query->orderBy('total_revenue', 'desc');
                    break;
            }

            $topProducts = $query->limit($limit)->get();

            // Get total products sold in period
            $totalStats = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.order_id')
                ->whereIn('orders.order_status', ['confirmed', 'shipped', 'delivered'])
                ->whereHas('order.paymentTransactions', function ($q) {
                    $q->where('status', 'completed');
                });

            if ($startDate) {
                $totalStats->where('orders.order_date', '>=', $startDate);
            }
            if ($endDate) {
                $totalStats->where('orders.order_date', '<=', $endDate);
            }

            $totals = $totalStats->select(
                DB::raw('COUNT(DISTINCT order_details.product_id) as unique_products'),
                DB::raw('SUM(order_details.quantity) as total_items_sold'),
                DB::raw('SUM(order_details.total_price) as total_revenue')
            )->first();

            return response()->json([
                'message' => 'Product reports retrieved successfully',
                'data' => [
                    'top_products' => $topProducts->map(function ($product) {
                        return [
                            'product_id' => $product->product_id,
                            'product_name' => $product->product_name,
                            'order_count' => $product->order_count,
                            'total_quantity' => $product->total_quantity,
                            'total_revenue' => round($product->total_revenue, 2),
                            'average_price' => round($product->avg_price, 2),
                        ];
                    }),
                    'summary' => [
                        'unique_products_sold' => $totals->unique_products ?? 0,
                        'total_items_sold' => $totals->total_items_sold ?? 0,
                        'total_revenue' => round($totals->total_revenue ?? 0, 2),
                    ],
                    'parameters' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'limit' => $limit,
                        'sort_by' => $sortBy,
                    ],
                    'currency' => 'VND'
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get product reports', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Failed to get product reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer reports
     * GET /api/v1/admin/reports/customers
     */
    public function customers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $limit = $request->limit ?? 10;

            // Get top customers by revenue
            $query = Customer::select(
                'customers.customer_id',
                'customers.customer_name',
                'customers.email',
                DB::raw('COUNT(orders.order_id) as total_orders'),
                DB::raw('SUM(orders.total_price) as total_spent'),
                DB::raw('AVG(orders.total_price) as avg_order_value'),
                DB::raw('MAX(orders.order_date) as last_order_date')
            )
            ->join('orders', 'customers.customer_id', '=', 'orders.customer_id')
            ->whereIn('orders.order_status', ['confirmed', 'shipped', 'delivered'])
            ->whereHas('orders.paymentTransactions', function ($q) {
                $q->where('status', 'completed');
            });

            if ($startDate) {
                $query->where('orders.order_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('orders.order_date', '<=', $endDate);
            }

            $topCustomers = $query
                ->groupBy('customers.customer_id', 'customers.customer_name', 'customers.email')
                ->orderBy('total_spent', 'desc')
                ->limit($limit)
                ->get();

            // Get new vs returning customers
            $newCustomersCount = Customer::query();
            $returningCustomersCount = Customer::whereHas('orders', function ($q) {
                $q->havingRaw('COUNT(*) > 1');
            });

            if ($startDate) {
                $newCustomersCount->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $newCustomersCount->where('created_at', '<=', $endDate);
            }

            return response()->json([
                'message' => 'Customer reports retrieved successfully',
                'data' => [
                    'top_customers' => $topCustomers->map(function ($customer) {
                        return [
                            'customer_id' => $customer->customer_id,
                            'customer_name' => $customer->customer_name,
                            'email' => $customer->email,
                            'total_orders' => $customer->total_orders,
                            'total_spent' => round($customer->total_spent, 2),
                            'average_order_value' => round($customer->avg_order_value, 2),
                            'last_order_date' => $customer->last_order_date,
                        ];
                    }),
                    'summary' => [
                        'total_customers' => Customer::count(),
                        'new_customers' => $newCustomersCount->count(),
                        'returning_customers' => $returningCustomersCount->count(),
                    ],
                    'parameters' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'limit' => $limit,
                    ],
                    'currency' => 'VND'
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get customer reports', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Failed to get customer reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales trend reports
     * GET /api/v1/admin/reports/sales-trend
     */
    public function salesTrend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $startDate = $request->start_date ?? Carbon::now()->subDays(30)->format('Y-m-d');
            $endDate = $request->end_date ?? Carbon::now()->format('Y-m-d');
            $groupBy = $request->group_by ?? 'day';

            $dateFormat = match($groupBy) {
                'month' => '%Y-%m',
                'week' => '%Y-%u',
                'day' => '%Y-%m-%d',
                default => '%Y-%m-%d'
            };

            $salesData = Order::select(
                DB::raw("DATE_FORMAT(order_date, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_price) as revenue'),
                DB::raw('AVG(total_price) as avg_order_value')
            )
            ->whereIn('order_status', ['confirmed', 'shipped', 'delivered'])
            ->whereHas('paymentTransactions', function ($q) {
                $q->where('status', 'completed');
            })
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<=', $endDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

            return response()->json([
                'message' => 'Sales trend retrieved successfully',
                'data' => [
                    'trend' => $salesData->map(function ($item) {
                        return [
                            'period' => $item->period,
                            'order_count' => $item->order_count,
                            'revenue' => round($item->revenue, 2),
                            'average_order_value' => round($item->avg_order_value, 2),
                        ];
                    }),
                    'summary' => [
                        'total_revenue' => round($salesData->sum('revenue'), 2),
                        'total_orders' => $salesData->sum('order_count'),
                        'average_daily_revenue' => round($salesData->avg('revenue'), 2),
                    ],
                    'parameters' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'group_by' => $groupBy,
                    ],
                    'currency' => 'VND'
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get sales trend', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Failed to get sales trend',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}