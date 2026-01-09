<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CartDetail;
use App\Models\OrderDetail;
use App\Models\PaymentTransaction;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentTransactionResource;
use App\Services\QRPaymentService;
use App\Services\ZaloPayService;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    // method GET
    public function index()
    {
        $orders = Order::get();
        if ($orders->count() > 0) {
            return response()->json([
                'message' => 'Get order success',
                'data' => OrderResource::collection($orders)
            ], 200);
        } else {
            return response()->json(['message' => 'No record available'], 200);
        }
    }

    // method POST
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|exists:carts,cart_id',
            'customer_id' => 'required|exists:customers,customer_id',
            'cart_detail_ids' => 'nullable|array',
            'cart_detail_ids.*' => 'exists:cart_details,cart_detail_id',
            'payment_method_id' => 'required_without:payment_method|exists:payment_methods,payment_method_id',
            'payment_method' => 'required_without:payment_method_id|exists:payment_methods,code',
            'shipping_address' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', [
                'errors' => $validator->messages(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Field is empty or invalid',
                'errors' => $validator->messages(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $customer = Customer::find($request->customer_id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            // Handle both payment_method_id and payment_method (code) formats
            if ($request->payment_method_id) {
                $paymentMethod = PaymentMethod::find($request->payment_method_id);
            } else {
                $paymentMethod = PaymentMethod::where('code', $request->payment_method)->first();
            }
            
            if (!$paymentMethod || !$paymentMethod->is_active) {
                return response()->json(['message' => 'Payment method not found or inactive'], 404);
            }

            // If specific cart_detail_ids are provided, use only those items
            // Otherwise, use all items in the cart that haven't been checked out
            if (!empty($request->cart_detail_ids)) {
                $cartDetails = CartDetail::whereIn('cart_detail_id', $request->cart_detail_ids)
                    ->where('cart_id', $request->cart_id)
                    ->where('is_checked_out', false)
                    ->get();
                
                // Validate that all requested cart_detail_ids belong to the specified cart
                if ($cartDetails->count() !== count($request->cart_detail_ids)) {
                    return response()->json([
                        'message' => 'Some cart items are invalid or already checked out'
                    ], 400);
                }
            } else {
                // Default behavior: use all items in the cart
                $cartDetails = CartDetail::where('cart_id', $request->cart_id)
                    ->where('is_checked_out', false)
                    ->get();
            }

            if ($cartDetails->isEmpty()) {
                return response()->json(['message' => 'No items in cart to checkout'], 400);
            }

            $shippingAddress = $request->shipping_address ?? $customer->address;
            $totalPrice = $cartDetails->sum('total_price');

            // Validate amount against payment method limits
            if ($totalPrice < $paymentMethod->minimum_amount) {
                return response()->json([
                    'message' => 'Amount is below minimum limit for this payment method',
                    'minimum_amount' => $paymentMethod->minimum_amount
                ], 400);
            }

            if ($paymentMethod->maximum_amount && $totalPrice > $paymentMethod->maximum_amount) {
                return response()->json([
                    'message' => 'Amount exceeds maximum limit for this payment method',
                    'maximum_amount' => $paymentMethod->maximum_amount
                ], 400);
            }

            $order = Order::create([
                'order_id' => 'ORD' . uniqid(),
                'customer_id' => $request->customer_id,
                'order_date' => Carbon::now(),
                'payment_method' => $paymentMethod->name,
                'shipping_address' => $shippingAddress,
                'discount' => $request->discount ?? 0,
                'total_price' => $totalPrice,
                'order_status' => 'pending'
            ]);

            // Create order details
            foreach ($cartDetails as $detail) {
                OrderDetail::create([
                    'order_detail_id' => 'OD' . uniqid(),
                    'order_id' => $order->order_id,
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product_name,
                    'quantity' => $detail->quantity,
                    'color' => $detail->color,
                    'size' => $detail->size,
                    'image' => $detail->image,
                    'unit_price' => $detail->unit_price,
                    'total_price' => $detail->total_price,
                ]);

                $detail->update(['is_checked_out' => true]);
            }

            // Calculate transaction fee
            $feeAmount = ($totalPrice * $paymentMethod->transaction_fee_percentage / 100) + $paymentMethod->transaction_fee_fixed;

            // Create payment transaction
            $paymentTransaction = PaymentTransaction::create([
                'transaction_id' => 'PAY' . uniqid(),
                'order_id' => $order->order_id,
                'payment_method_id' => $paymentMethod->payment_method_id,
                'amount' => $totalPrice,
                'fee_amount' => $feeAmount,
                'currency' => 'VND',
                'status' => 'pending',
                'reference_number' => $order->order_id,
            ]);

            // Handle payment method specific logic
            if ($paymentMethod->code === 'zalopay') {
                // For ZaloPay, immediately create the payment and get the order_url
                $zaloPayService = new ZaloPayService();
                $paymentResult = $zaloPayService->createPayment($order, "Payment for order {$order->order_id}");
                
                if ($paymentResult['success'] && isset($paymentResult['order_url'])) {
                    // Update payment transaction with ZaloPay response
                    $paymentTransaction->update([
                        'gateway_response' => $paymentResult,
                        'gateway_transaction_id' => $paymentResult['app_trans_id'] ?? null,
                        'reference_number' => $paymentResult['app_trans_id'] ?? $order->order_id,
                        'qr_code_url' => null, // Remove generic QR for ZaloPay
                    ]);
                    
                    // Reload to get updated data
                    $paymentTransaction->refresh();
                } else {
                    // Log error but don't fail the order creation
                    Log::warning('Failed to create ZaloPay payment URL', [
                        'order_id' => $order->order_id,
                        'error' => $paymentResult['message'] ?? 'Unknown error'
                    ]);
                }
            } elseif (in_array($paymentMethod->code, ['momo', 'vnpay', 'bank_transfer'])) {
                // For other payment methods, generate generic QR code
                $qrService = new QRPaymentService();
                $qrData = $qrService->generateQRCode($paymentTransaction);
                
                // Check if QR code columns exist before updating
                try {
                    if (Schema::hasColumn('payment_transactions', 'qr_code_url')) {
                        $paymentTransaction->update([
                            'qr_code_url' => $qrData['qr_code_url'] ?? null,
                            'qr_code_payload' => $qrData['qr_code_payload'] ?? null,
                        ]);
                    } else {
                        // Store QR data in gateway_response if columns don't exist
                        $paymentTransaction->update([
                            'gateway_response' => array_merge(
                                $paymentTransaction->gateway_response ?? [],
                                [
                                    'qr_code_url' => $qrData['qr_code_url'] ?? null,
                                    'qr_code_payload' => $qrData['qr_code_payload'] ?? null,
                                ]
                            ),
                        ]);
                    }
                } catch (\Exception $e) {
                    // Fallback: Store in gateway_response
                    $paymentTransaction->update([
                        'gateway_response' => array_merge(
                            $paymentTransaction->gateway_response ?? [],
                            [
                                'qr_code_url' => $qrData['qr_code_url'] ?? null,
                                'qr_code_payload' => $qrData['qr_code_payload'] ?? null,
                            ]
                        ),
                    ]);
                }
            }

            $cart = Cart::where('cart_id', $request->cart_id)->first();
            $cart->update(['cart_status' => true]);

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully from cart',
                'data' => [
                    'order' => new OrderResource($order),
                    'payment_transaction' => new PaymentTransactionResource($paymentTransaction->load(['paymentMethod']))
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json(['message' => 'Failed to create order', 'error' => $e->getMessage()], 500);
        }
    }

    // method GET Detail with order_id
    public function show($order_id)
    {
        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json([
                    'message' => 'Order not found',
                    'order_id' => $order_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get order success with order_id',
                'data' => new OrderResource($order)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get order information', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);

            return response()->json([
                'message' => 'Failed to get order information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update order status
    public function updateStatus(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'order_status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Validate status transitions
            $currentStatus = $order->order_status;
            $newStatus = $request->order_status;

            // Check if status transition is valid
            if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
                return response()->json([
                    'message' => "Invalid status transition from {$currentStatus} to {$newStatus}"
                ], 400);
            }

            $order->update([
                'order_status' => $newStatus,
                'notes' => $request->notes ?? $order->notes
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order status updated successfully',
                'data' => new OrderResource($order)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order status update failed', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);
            return response()->json(['message' => 'Failed to update order status', 'error' => $e->getMessage()], 500);
        }
    }

    // Cancel order
    public function cancel($order_id)
    {
        DB::beginTransaction();

        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Only allow cancellation for pending or confirmed orders
            if (!in_array($order->order_status, ['pending', 'confirmed'])) {
                return response()->json([
                    'message' => 'Order cannot be cancelled. Only pending or confirmed orders can be cancelled.'
                ], 400);
            }

            $order->update([
                'order_status' => 'cancelled',
                'notes' => 'Order cancelled at ' . Carbon::now()->format('Y-m-d H:i:s')
            ]);

            // Restore inventory for cancelled order
            $orderDetails = OrderDetail::where('order_id', $order_id)->get();
            foreach ($orderDetails as $detail) {
                $product = Product::find($detail->product_id);
                if ($product && $product->quantity_in_stock !== null) {
                    $product->quantity_in_stock += $detail->quantity;
                    $product->save();
                }
            }
            
            // TODO: Implement refund process if payment was made

            DB::commit();

            return response()->json([
                'message' => 'Order cancelled successfully',
                'data' => new OrderResource($order)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);
            return response()->json(['message' => 'Failed to cancel order', 'error' => $e->getMessage()], 500);
        }
    }

    // Get orders by customer
    public function getOrdersByCustomer($customer_id)
    {
        try {
            $customer = Customer::find($customer_id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            $orders = Order::where('customer_id', $customer_id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found for this customer'], 200);
            }

            return response()->json([
                'message' => 'Get orders by customer success',
                'data' => OrderResource::collection($orders)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get orders by customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customer_id
            ]);
            return response()->json(['message' => 'Failed to get orders', 'error' => $e->getMessage()], 500);
        }
    }

    // Get orders by status
    public function getOrdersByStatus($status)
    {
        $validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses)
            ], 400);
        }

        try {
            $orders = Order::where('order_status', $status)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found with status: ' . $status], 200);
            }

            return response()->json([
                'message' => 'Get orders by status success',
                'data' => OrderResource::collection($orders)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get orders by status', [
                'error' => $e->getMessage(),
                'status' => $status
            ]);
            return response()->json(['message' => 'Failed to get orders', 'error' => $e->getMessage()], 500);
        }
    }

    // Get order history with filters
    public function getOrderHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,customer_id',
            'status' => 'nullable|in:pending,confirmed,shipped,delivered,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_by' => 'nullable|in:order_date,total_price,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $query = Order::query();

            // Apply filters
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->status) {
                $query->where('order_status', $request->status);
            }

            if ($request->start_date) {
                $query->whereDate('order_date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->whereDate('order_date', '<=', $request->end_date);
            }

            // Apply sorting
            $sortBy = $request->sort_by ?? 'created_at';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->per_page ?? 15;
            $orders = $query->paginate($perPage);

            return response()->json([
                'message' => 'Get order history success',
                'data' => OrderResource::collection($orders),
                'pagination' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get order history', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            return response()->json(['message' => 'Failed to get order history', 'error' => $e->getMessage()], 500);
        }
    }

    // Update shipping address
    public function updateShippingAddress(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|min:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Only allow address update for orders not yet shipped
            if (in_array($order->order_status, ['shipped', 'delivered', 'cancelled'])) {
                return response()->json([
                    'message' => 'Cannot update shipping address for orders that are shipped, delivered, or cancelled'
                ], 400);
            }

            $order->update([
                'shipping_address' => $request->shipping_address
            ]);

            return response()->json([
                'message' => 'Shipping address updated successfully',
                'data' => new OrderResource($order)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update shipping address', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);
            return response()->json(['message' => 'Failed to update shipping address', 'error' => $e->getMessage()], 500);
        }
    }

    // Add tracking information
    public function addTracking(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string',
            'carrier' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Only allow tracking for shipped orders
            if ($order->order_status !== 'shipped') {
                return response()->json([
                    'message' => 'Tracking information can only be added to shipped orders'
                ], 400);
            }

            $trackingInfo = json_encode([
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'added_at' => Carbon::now()->toIso8601String()
            ]);

            $order->update([
                'notes' => $order->notes . ' | Tracking: ' . $trackingInfo
            ]);

            return response()->json([
                'message' => 'Tracking information added successfully',
                'data' => new OrderResource($order)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to add tracking information', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);
            return response()->json(['message' => 'Failed to add tracking information', 'error' => $e->getMessage()], 500);
        }
    }

    // Process refund
    public function processRefund(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0',
            'refund_reason' => 'required|string',
            'refund_method' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = Order::where('order_id', $order_id)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Validate refund amount
            if ($request->refund_amount > $order->total_price) {
                return response()->json([
                    'message' => 'Refund amount cannot exceed the order total price'
                ], 400);
            }

            // Only allow refunds for delivered or cancelled orders
            if (!in_array($order->order_status, ['delivered', 'cancelled'])) {
                return response()->json([
                    'message' => 'Refunds can only be processed for delivered or cancelled orders'
                ], 400);
            }

            // Record refund information
            $processedBy = 'system';
            try {
                if ($request->user()) {
                    $processedBy = $request->user()->id;
                }
            } catch (\Exception $e) {
                // If auth is not available, use 'system'
                $processedBy = 'system';
            }
            
            $refundInfo = json_encode([
                'refund_id' => 'REF' . uniqid(),
                'amount' => $request->refund_amount,
                'reason' => $request->refund_reason,
                'method' => $request->refund_method,
                'processed_at' => Carbon::now()->toIso8601String(),
                'processed_by' => $processedBy
            ]);

            $order->update([
                'notes' => $order->notes . ' | Refund: ' . $refundInfo
            ]);

            // TODO: Integrate with payment gateway for actual refund processing
            // TODO: Create refund record in a separate refunds table

            DB::commit();

            return response()->json([
                'message' => 'Refund processed successfully',
                'data' => [
                    'order' => new OrderResource($order),
                    'refund_amount' => $request->refund_amount
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);
            return response()->json(['message' => 'Failed to process refund', 'error' => $e->getMessage()], 500);
        }
    }

    // Helper method to validate status transitions
    private function isValidStatusTransition($currentStatus, $newStatus)
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [], // No transitions from delivered
            'cancelled' => [] // No transitions from cancelled
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Calculate revenue from actual orders
     * GET /api/v1/orders/revenue
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateRevenue(Request $request)
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
            // Build the query for orders with eligible statuses
            $query = Order::whereIn('order_status', ['confirmed', 'shipped', 'delivered']);

            // Apply date filters if provided
            if ($request->start_date) {
                $query->where('order_date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->where('order_date', '<=', $request->end_date);
            }

            // Join with payment_transactions to ensure only completed payments are counted
            $query->whereHas('paymentTransactions', function ($q) {
                $q->where('status', 'completed');
            });

            // Calculate revenue metrics
            $totalRevenue = $query->sum('total_price');
            $orderCount = $query->count();
            $averageOrderValue = $orderCount > 0 ? $totalRevenue / $orderCount : 0;

            // Get additional statistics
            $ordersByStatus = Order::select('order_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as revenue'))
                ->whereIn('order_status', ['confirmed', 'shipped', 'delivered'])
                ->whereHas('paymentTransactions', function ($q) {
                    $q->where('status', 'completed');
                });

            if ($request->start_date) {
                $ordersByStatus->where('order_date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $ordersByStatus->where('order_date', '<=', $request->end_date);
            }

            $ordersByStatus = $ordersByStatus->groupBy('order_status')->get();

            // Calculate date range for display
            $dateRange = [
                'start_date' => $request->start_date ?? Order::min('order_date'),
                'end_date' => $request->end_date ?? Order::max('order_date'),
            ];

            return response()->json([
                'message' => 'Revenue calculated successfully',
                'data' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'total_orders' => $orderCount,
                    'average_order_value' => round($averageOrderValue, 2),
                    'revenue_by_status' => $ordersByStatus,
                    'date_range' => $dateRange,
                    'currency' => 'VND'
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to calculate revenue', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Failed to calculate revenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
