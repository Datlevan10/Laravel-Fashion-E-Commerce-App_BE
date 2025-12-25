<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentTransactionResource;
use App\Http\Resources\PaymentMethodResource;
use App\Services\QRPaymentService;
use App\Services\ZaloPayService;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $qrPaymentService;
    protected $zaloPayService;

    public function __construct(QRPaymentService $qrPaymentService, ZaloPayService $zaloPayService)
    {
        $this->qrPaymentService = $qrPaymentService;
        $this->zaloPayService = $zaloPayService;
    }

    /**
     * Get all payment methods
     */
    public function getPaymentMethods()
    {
        try {
            $paymentMethods = PaymentMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'message' => 'Get payment methods success',
                'data' => PaymentMethodResource::collection($paymentMethods)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get payment methods', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to get payment methods', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Initialize payment methods (for setup/testing)
     * POST /api/payments/initialize
     */
    public function initializePaymentMethods()
    {
        try {
            // Check if any payment methods exist
            $existingCount = PaymentMethod::count();
            
            if ($existingCount > 0) {
                $paymentMethods = PaymentMethod::all();
                return response()->json([
                    'message' => 'Payment methods already exist',
                    'count' => $existingCount,
                    'data' => PaymentMethodResource::collection($paymentMethods)
                ], 200);
            }

            // Create basic payment methods
            $methods = [
                [
                    'payment_method_id' => 'PM001',
                    'name' => 'MoMo E-Wallet',
                    'code' => 'momo',
                    'type' => 'digital_wallet',
                    'logo' => 'momo-logo.png',
                    'is_active' => true,
                    'transaction_fee_percentage' => 0.5,
                    'transaction_fee_fixed' => 0,
                    'minimum_amount' => 10000,
                    'maximum_amount' => 50000000,
                    'api_config' => [
                        'partner_code' => 'DEMO',
                        'access_key' => 'demo_access_key',
                        'secret_key' => 'demo_secret_key',
                        'endpoint' => 'https://test-payment.momo.vn'
                    ],
                    'supported_currencies' => ['VND'],
                    'description' => 'Pay with MoMo E-Wallet',
                    'sort_order' => 1,
                ],
                [
                    'payment_method_id' => 'PM002',
                    'name' => 'VNPay',
                    'code' => 'vnpay',
                    'type' => 'digital_wallet',
                    'logo' => 'vnpay-logo.png',
                    'is_active' => true,
                    'transaction_fee_percentage' => 0.8,
                    'transaction_fee_fixed' => 0,
                    'minimum_amount' => 5000,
                    'maximum_amount' => 100000000,
                    'api_config' => [
                        'tmn_code' => 'DEMO',
                        'hash_secret' => 'demo_hash_secret',
                        'endpoint' => 'https://sandbox.vnpayment.vn'
                    ],
                    'supported_currencies' => ['VND'],
                    'description' => 'Pay with VNPay',
                    'sort_order' => 2,
                ],
                [
                    'payment_method_id' => 'PM004',
                    'name' => 'Cash on Delivery',
                    'code' => 'cash_on_delivery',
                    'type' => 'cash_on_delivery',
                    'logo' => 'cod-logo.png',
                    'is_active' => true,
                    'transaction_fee_percentage' => 0,
                    'transaction_fee_fixed' => 15000,
                    'minimum_amount' => 0,
                    'maximum_amount' => 5000000,
                    'api_config' => null,
                    'supported_currencies' => ['VND'],
                    'description' => 'Pay when receiving goods',
                    'sort_order' => 4,
                ],
                [
                    'payment_method_id' => 'PM005',
                    'name' => 'ZaloPay',
                    'code' => 'zalopay',
                    'type' => 'digital_wallet',
                    'logo' => '/images/payment-methods/zalopay-logo.png',
                    'is_active' => true,
                    'transaction_fee_percentage' => 2.5,
                    'transaction_fee_fixed' => 0,
                    'minimum_amount' => 1000,
                    'maximum_amount' => 50000000,
                    'api_config' => [
                        'app_id' => config('services.zalopay.app_id'),
                        'endpoint' => 'https://sb-openapi.zalopay.vn/v2'
                    ],
                    'supported_currencies' => ['VND'],
                    'description' => 'Thanh toán qua ví điện tử ZaloPay - Nhanh chóng, an toàn',
                    'sort_order' => 3,
                ]
            ];

            foreach ($methods as $method) {
                PaymentMethod::create($method);
            }

            $paymentMethods = PaymentMethod::all();
            
            return response()->json([
                'message' => 'Payment methods initialized successfully',
                'count' => count($methods),
                'data' => PaymentMethodResource::collection($paymentMethods)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to initialize payment methods', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Failed to initialize payment methods', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Debug payment methods status
     * GET /api/payments/debug
     */
    public function debugPaymentMethods()
    {
        try {
            $allMethods = PaymentMethod::all();
            $activeMethods = PaymentMethod::where('is_active', true)->get();
            
            return response()->json([
                'message' => 'Payment methods debug info',
                'total_count' => $allMethods->count(),
                'active_count' => $activeMethods->count(),
                'all_methods' => $allMethods->map(function($method) {
                    return [
                        'id' => $method->payment_method_id,
                        'name' => $method->name,
                        'code' => $method->code,
                        'type' => $method->type,
                        'is_active' => $method->is_active,
                        'created_at' => $method->created_at
                    ];
                }),
                'available_codes' => $activeMethods->pluck('code')->toArray()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Debug failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add missing QR code columns to payment_transactions table
     * POST /api/payments/fix-schema
     */
    public function fixPaymentTransactionsSchema()
    {
        try {
            // Check if columns already exist
            if (\Schema::hasColumn('payment_transactions', 'qr_code_url')) {
                return response()->json([
                    'message' => 'QR code columns already exist in payment_transactions table',
                    'columns' => ['qr_code_url', 'qr_code_payload']
                ], 200);
            }

            // Add the missing columns
            \Schema::table('payment_transactions', function ($table) {
                $table->text('qr_code_url')->nullable()->after('gateway_response');
                $table->text('qr_code_payload')->nullable()->after('qr_code_url');
            });

            return response()->json([
                'message' => 'QR code columns added successfully to payment_transactions table',
                'columns' => ['qr_code_url', 'qr_code_payload']
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to fix payment transactions schema', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to fix schema',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create/Generate QR code for the order
     * POST /api/payments/{orderId}/create
     */
    public function createPayment($orderId)
    {
        DB::beginTransaction();

        try {
            $order = Order::where('order_id', $orderId)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Check if payment transaction already exists
            $existingTransaction = PaymentTransaction::where('order_id', $orderId)->first();
            if ($existingTransaction) {
                return response()->json([
                    'message' => 'Payment already exists for this order',
                    'data' => new PaymentTransactionResource($existingTransaction->load(['paymentMethod']))
                ], 200);
            }

            // Find default payment method or create a basic one
            $paymentMethod = PaymentMethod::where('is_active', true)->first();
            if (!$paymentMethod) {
                return response()->json(['message' => 'No active payment method available'], 400);
            }

            $feeAmount = ($order->total_price * $paymentMethod->transaction_fee_percentage / 100) + $paymentMethod->transaction_fee_fixed;

            // Create payment transaction
            $paymentTransaction = PaymentTransaction::create([
                'transaction_id' => 'PAY' . uniqid(),
                'order_id' => $order->order_id,
                'payment_method_id' => $paymentMethod->payment_method_id,
                'amount' => $order->total_price,
                'fee_amount' => $feeAmount,
                'currency' => 'VND',
                'status' => 'pending',
                'reference_number' => $order->order_id,
            ]);

            // Generate QR code
            $qrData = $this->qrPaymentService->generateQRCode($paymentTransaction);
            
            // Check if QR code columns exist before updating
            try {
                if (\Schema::hasColumn('payment_transactions', 'qr_code_url')) {
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

            DB::commit();

            return response()->json([
                'message' => 'Payment QR code generated successfully',
                'data' => new PaymentTransactionResource($paymentTransaction->load(['paymentMethod'])),
                'qr_data' => $qrData
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment QR code', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            return response()->json(['message' => 'Failed to create payment QR code', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment transaction details
     * GET /api/payments/{transactionId}
     */
    public function show($transactionId)
    {
        try {
            $transaction = PaymentTransaction::with(['order', 'paymentMethod'])
                ->where('transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Payment transaction not found'], 404);
            }

            return response()->json([
                'message' => 'Get payment transaction success',
                'data' => new PaymentTransactionResource($transaction)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get payment transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return response()->json(['message' => 'Failed to get payment transaction', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle payment callback from gateway
     * POST /api/payments/callback
     */
    public function handleCallback(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('Payment callback received', ['data' => $request->all()]);

            $transactionId = $request->input('transaction_id') ?? $request->input('vnp_TxnRef') ?? $request->input('orderId');
            $gatewayResponse = $request->all();

            if (!$transactionId) {
                return response()->json(['message' => 'Transaction ID not found in callback'], 400);
            }

            $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                return response()->json(['message' => 'Payment transaction not found'], 404);
            }

            // Validate payment status from gateway
            $paymentStatus = $this->qrPaymentService->validatePaymentStatus(
                $transaction->paymentMethod->code, 
                $gatewayResponse
            );

            // Update transaction
            $transaction->update([
                'status' => $paymentStatus,
                'gateway_response' => $gatewayResponse,
                'gateway_transaction_id' => $request->input('gateway_transaction_id') ?? $request->input('vnp_TransactionNo') ?? $request->input('transId'),
                'processed_at' => $paymentStatus === 'completed' ? Carbon::now() : null,
                'failure_reason' => $paymentStatus === 'failed' ? $request->input('message', 'Payment failed') : null
            ]);

            // Update order status based on payment status
            $order = $transaction->order;
            if ($paymentStatus === 'completed') {
                $order->update(['order_status' => 'confirmed']);
            } elseif ($paymentStatus === 'failed') {
                $order->update(['order_status' => 'cancelled']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment callback processed successfully',
                'data' => [
                    'transaction' => new PaymentTransactionResource($transaction->load(['paymentMethod'])),
                    'status' => $paymentStatus
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment callback processing failed', [
                'error' => $e->getMessage(),
                'callback_data' => $request->all()
            ]);
            return response()->json(['message' => 'Failed to process payment callback', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manual payment confirmation (for testing or admin use)
     * POST /api/payments/{transactionId}/confirm
     */
    public function confirmPayment(Request $request, $transactionId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,failed',
            'gateway_transaction_id' => 'nullable|string',
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
            $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                return response()->json(['message' => 'Payment transaction not found'], 404);
            }

            if ($transaction->status !== 'pending') {
                return response()->json([
                    'message' => 'Payment transaction is not in pending status',
                    'current_status' => $transaction->status
                ], 400);
            }

            $newStatus = $request->status;
            
            $transaction->update([
                'status' => $newStatus,
                'gateway_transaction_id' => $request->gateway_transaction_id,
                'processed_at' => $newStatus === 'completed' ? Carbon::now() : null,
                'failure_reason' => $newStatus === 'failed' ? $request->notes : null,
                'gateway_response' => [
                    'manual_confirmation' => true,
                    'confirmed_by' => auth()->user()->id ?? 'system',
                    'confirmed_at' => Carbon::now()->toISOString(),
                    'notes' => $request->notes
                ]
            ]);

            // Update order status
            $order = $transaction->order;
            if ($newStatus === 'completed') {
                $order->update(['order_status' => 'confirmed']);
            } elseif ($newStatus === 'failed') {
                $order->update(['order_status' => 'cancelled']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment confirmed successfully',
                'data' => new PaymentTransactionResource($transaction->load(['paymentMethod']))
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual payment confirmation failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return response()->json(['message' => 'Failed to confirm payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel payment transaction
     * PATCH /api/payments/{transactionId}/cancel
     */
    public function cancelPayment($transactionId)
    {
        DB::beginTransaction();

        try {
            $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                return response()->json(['message' => 'Payment transaction not found'], 404);
            }

            if (!in_array($transaction->status, ['pending', 'processing'])) {
                return response()->json([
                    'message' => 'Payment transaction cannot be cancelled',
                    'current_status' => $transaction->status
                ], 400);
            }

            $transaction->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelled by user at ' . Carbon::now()->format('Y-m-d H:i:s'),
                'processed_at' => Carbon::now()
            ]);

            // Update order status
            $order = $transaction->order;
            $order->update(['order_status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'message' => 'Payment cancelled successfully',
                'data' => new PaymentTransactionResource($transaction->load(['paymentMethod']))
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment cancellation failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return response()->json(['message' => 'Failed to cancel payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment transactions by order
     * GET /api/payments/order/{orderId}
     */
    public function getPaymentsByOrder($orderId)
    {
        try {
            $order = Order::where('order_id', $orderId)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            $transactions = PaymentTransaction::with(['paymentMethod'])
                ->where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Get payment transactions by order success',
                'data' => PaymentTransactionResource::collection($transactions)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get payments by order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            return response()->json(['message' => 'Failed to get payments', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check payment status
     * GET /api/payments/{transactionId}/status
     */
    public function checkPaymentStatus($transactionId)
    {
        try {
            $transaction = PaymentTransaction::with(['order', 'paymentMethod'])
                ->where('transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Payment transaction not found'], 404);
            }

            return response()->json([
                'message' => 'Payment status retrieved successfully',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'order_id' => $transaction->order_id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'processed_at' => $transaction->processed_at?->format('Y-m-d H:i:s'),
                    'order_status' => $transaction->order->order_status
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            return response()->json(['message' => 'Failed to check payment status', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create ZaloPay payment transaction
     * POST /api/payments/zalopay/create
     */
    public function createZaloPayPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'amount' => 'nullable|numeric|min:1000',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Find order - use find() which works with string primary keys
            $order = Order::find($request->order_id);
            if (!$order) {
                // Try with where clause as fallback
                $order = Order::where('order_id', $request->order_id)->first();
                if (!$order) {
                    DB::rollBack();
                    return response()->json(['message' => 'Order not found'], 404);
                }
            }
            
            // Use order total_price if amount not provided
            if (!$request->has('amount')) {
                $order->total_price = $order->total_price ?? $request->amount;
            }
            
            // Create ZaloPay payment using service
            $result = $this->zaloPayService->createPayment($order, $request->description);
            
            if (!$result['success']) {
                DB::rollBack();
                throw new \Exception($result['message'] ?? 'Failed to create ZaloPay payment');
            }
            
            // If we got existing transaction without URL, delete it and create new one
            if (empty($result['order_url']) && isset($result['message']) && strpos($result['message'], 'existing') !== false) {
                // Delete existing pending transaction
                PaymentTransaction::where('order_id', $order->order_id)
                    ->where('payment_method_id', 'PM005')
                    ->whereIn('status', ['pending'])
                    ->delete();
                    
                // Try again
                $result = $this->zaloPayService->createPayment($order, $request->description);
                
                if (!$result['success']) {
                    DB::rollBack();
                    throw new \Exception($result['message'] ?? 'Failed to create ZaloPay payment');
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'ZaloPay payment created successfully',
                'transaction_id' => $result['transaction_id'],
                'order_url' => $result['order_url'],
                'zp_trans_token' => $result['zp_trans_token'] ?? null,
                'app_trans_id' => $result['app_trans_id'],
                'qr_code' => $result['qr_code'] ?? null
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ZaloPay payment', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json(['message' => 'Failed to create ZaloPay payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Query ZaloPay payment status
     * POST /api/payments/zalopay/query
     */
    public function queryZaloPayStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_trans_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->messages(),
            ], 422);
        }

        try {
            // Query payment status using service
            $result = $this->zaloPayService->queryPaymentStatus($request->app_trans_id);
            
            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to query payment status');
            }
            
            return response()->json([
                'app_trans_id' => $request->app_trans_id,
                'status' => $result['status'],
                'amount' => $result['amount'],
                'zp_trans_id' => $result['zp_trans_id'],
                'message' => $result['message']
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to query ZaloPay status', [
                'error' => $e->getMessage(),
                'app_trans_id' => $request->app_trans_id
            ]);
            return response()->json(['message' => 'Failed to query ZaloPay status', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle ZaloPay callback/webhook
     * POST /api/payments/zalopay/callback
     */
    public function handleZaloPayCallback(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('ZaloPay callback received', ['data' => $request->all()]);
            
            // Validate callback
            $validation = $this->zaloPayService->validateCallback($request->all());
            
            if (!$validation['valid']) {
                Log::warning('Invalid ZaloPay callback', ['message' => $validation['message']]);
                return response()->json([
                    'return_code' => -1,
                    'return_message' => $validation['message'] ?? 'Invalid callback'
                ], 400);
            }
            
            // Process callback
            $this->zaloPayService->processCallback($validation['data']);
            
            // Return success to ZaloPay
            return response()->json([
                'return_code' => 1,
                'return_message' => 'success'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ZaloPay callback processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'callback_data' => $request->all()
            ]);
            
            // Return error to ZaloPay
            return response()->json([
                'return_code' => 0,
                'return_message' => 'Internal server error'
            ], 500);
        }
    }
}