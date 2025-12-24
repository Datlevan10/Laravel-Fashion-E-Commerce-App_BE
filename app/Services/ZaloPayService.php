<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZaloPayService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('services.zalopay');
    }

    /**
     * Create ZaloPay payment for an order
     */
    public function createPayment(Order $order, string $description = null)
    {
        DB::beginTransaction();

        try {
            // Generate unique app_trans_id (format: yymmdd_random)
            $appTransId = date('ymd') . '_' . time();
            
            // Get or create ZaloPay payment method
            $paymentMethod = PaymentMethod::where('code', 'zalopay')->first();
            if (!$paymentMethod) {
                $paymentMethod = $this->initializePaymentMethod();
            }
            
            // Check if transaction already exists
            $existingTransaction = PaymentTransaction::where('order_id', $order->order_id)
                ->where('payment_method_id', $paymentMethod->payment_method_id)
                ->whereIn('status', ['pending', 'processing'])
                ->first();
                
            if ($existingTransaction) {
                // Return existing transaction with order URL
                return [
                    'success' => true,
                    'transaction_id' => $existingTransaction->transaction_id,
                    'order_url' => $existingTransaction->gateway_response['order_url'] ?? null,
                    'app_trans_id' => $existingTransaction->reference_number,
                    'message' => 'Using existing pending transaction'
                ];
            }
            
            // Build ZaloPay request payload
            $embedData = json_encode([
                'order_id' => $order->order_id,
                'merchant_info' => 'Fashion Store',
                'redirect_url' => config('app.url') . '/payment/success'
            ]);
            
            $items = json_encode([[
                'itemid' => $order->order_id,
                'itemname' => $description ?: "Payment for order {$order->order_id}",
                'itemprice' => intval($order->total_price),
                'itemquantity' => 1
            ]]);
            
            $appTime = round(microtime(true) * 1000);
            $amount = intval($order->total_price);
            
            // Build payload for MAC generation
            $payload = [
                'app_id' => intval($this->config['app_id']),
                'app_trans_id' => $appTransId,
                'app_user' => 'user_' . ($order->user_id ?? 'guest'),
                'amount' => $amount,
                'app_time' => $appTime,
                'embed_data' => $embedData,
                'item' => $items,
                'description' => $description ?: "Payment for order {$order->order_id}",
                'bank_code' => '',
                'callback_url' => $this->config['callback_url']
            ];
            
            // Generate MAC signature
            $macData = $payload['app_id'] . '|' . $payload['app_trans_id'] . '|' . $payload['app_user'] . '|' . 
                       $payload['amount'] . '|' . $payload['app_time'] . '|' . $payload['embed_data'] . '|' . $payload['item'];
            $payload['mac'] = hash_hmac('sha256', $macData, $this->config['key1']);
            
            // Call ZaloPay API
            $response = $this->callApi($this->config['create_url'], $payload);
            
            if (!$response || !isset($response['return_code'])) {
                throw new \Exception('Invalid response from ZaloPay API');
            }
            
            if ($response['return_code'] !== 1) {
                throw new \Exception($response['return_message'] ?? 'ZaloPay API error');
            }
            
            // Calculate fees
            $feeAmount = ($amount * $paymentMethod->transaction_fee_percentage / 100) + $paymentMethod->transaction_fee_fixed;
            
            // Create payment transaction
            $transaction = PaymentTransaction::create([
                'transaction_id' => 'TXN_' . Str::upper(Str::random(10)),
                'order_id' => $order->order_id,
                'payment_method_id' => $paymentMethod->payment_method_id,
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'currency' => 'VND',
                'status' => 'pending',
                'reference_number' => $appTransId,
                'gateway_transaction_id' => $appTransId,
                'gateway_response' => $response
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction->transaction_id,
                'order_url' => $response['order_url'] ?? null,
                'zp_trans_token' => $response['zp_trans_token'] ?? null,
                'app_trans_id' => $appTransId,
                'qr_code' => $response['qr_code'] ?? null
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ZaloPay payment creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->order_id
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Query payment status from ZaloPay
     */
    public function queryPaymentStatus(string $appTransId)
    {
        try {
            // Generate MAC for query
            $macData = $this->config['app_id'] . '|' . $appTransId . '|' . $this->config['key1'];
            $mac = hash_hmac('sha256', $macData, $this->config['key1']);
            
            $queryPayload = [
                'app_id' => intval($this->config['app_id']),
                'app_trans_id' => $appTransId,
                'mac' => $mac
            ];
            
            // Call ZaloPay Query API
            $response = $this->callApi($this->config['query_url'], $queryPayload);
            
            if (!$response || !isset($response['return_code'])) {
                throw new \Exception('Invalid response from ZaloPay Query API');
            }
            
            // Map ZaloPay status to internal status
            $status = $this->mapZaloPayStatus($response['return_code']);
            
            // Update transaction if exists
            $this->updateTransactionStatus($appTransId, $status, $response);
            
            return [
                'success' => true,
                'status' => $status,
                'zp_trans_id' => $response['zp_trans_id'] ?? null,
                'amount' => $response['amount'] ?? null,
                'message' => $response['return_message'] ?? 'Query successful',
                'raw_response' => $response
            ];
            
        } catch (\Exception $e) {
            Log::error('ZaloPay query failed', [
                'error' => $e->getMessage(),
                'app_trans_id' => $appTransId
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate ZaloPay callback
     */
    public function validateCallback(array $callbackData)
    {
        try {
            $jsonData = $callbackData['data'] ?? null;
            $reqMac = $callbackData['mac'] ?? null;
            
            if (!$jsonData || !$reqMac) {
                throw new \Exception('Missing data or mac in callback');
            }
            
            // Verify MAC using KEY2
            $mac = hash_hmac('sha256', $jsonData, $this->config['key2']);
            
            if (!hash_equals($mac, $reqMac)) {
                throw new \Exception('Invalid callback MAC');
            }
            
            // Parse callback data
            $data = json_decode($jsonData, true);
            
            if (!$data) {
                throw new \Exception('Invalid JSON data');
            }
            
            return [
                'valid' => true,
                'data' => $data
            ];
            
        } catch (\Exception $e) {
            Log::warning('ZaloPay callback validation failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'valid' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process validated callback
     */
    public function processCallback(array $callbackData)
    {
        DB::beginTransaction();
        
        try {
            $appTransId = $callbackData['app_trans_id'] ?? null;
            $amount = $callbackData['amount'] ?? 0;
            
            if (!$appTransId) {
                throw new \Exception('Missing app_trans_id');
            }
            
            // Find transaction
            $transaction = PaymentTransaction::where('gateway_transaction_id', $appTransId)
                ->orWhere('reference_number', $appTransId)
                ->first();
            
            if (!$transaction) {
                // Log but don't throw error to avoid retries
                Log::warning('Transaction not found for callback', ['app_trans_id' => $appTransId]);
                DB::commit();
                return true;
            }
            
            // Check for idempotency
            if ($transaction->status === 'completed') {
                DB::commit();
                return true;
            }
            
            // Validate amount
            if (abs($transaction->amount - $amount) > 0.01) {
                throw new \Exception('Amount mismatch');
            }
            
            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'gateway_response' => array_merge(
                    $transaction->gateway_response ?? [],
                    ['callback' => $callbackData]
                ),
                'processed_at' => Carbon::now(),
                'failure_reason' => null
            ]);
            
            // Update order
            if ($transaction->order_id) {
                Order::where('order_id', $transaction->order_id)
                    ->update(['order_status' => 'confirmed']);
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ZaloPay callback processing failed', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData
            ]);
            throw $e;
        }
    }
    
    /**
     * Initialize ZaloPay payment method
     */
    private function initializePaymentMethod()
    {
        return PaymentMethod::create([
            'payment_method_id' => 'PM_ZALOPAY',
            'name' => 'ZaloPay',
            'code' => 'zalopay',
            'type' => 'digital_wallet',
            'logo' => '/images/payment-methods/zalopay-logo.png',
            'is_active' => true,
            'transaction_fee_percentage' => 0,
            'transaction_fee_fixed' => 0,
            'minimum_amount' => 1000,
            'maximum_amount' => 100000000,
            'api_config' => [
                'app_id' => $this->config['app_id'],
                'endpoint' => 'https://sb-openapi.zalopay.vn/v2'
            ],
            'supported_currencies' => ['VND'],
            'description' => 'ZaloPay E-Wallet - Fast & Secure',
            'sort_order' => 5
        ]);
    }
    
    /**
     * Call ZaloPay API
     */
    private function callApi(string $url, array $payload)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            throw new \Exception('Failed to connect to ZaloPay API');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Map ZaloPay status to internal status
     */
    private function mapZaloPayStatus(int $returnCode)
    {
        switch ($returnCode) {
            case 1:
                return 'completed';
            case 2:
                return 'failed';
            case 3:
            default:
                return 'pending';
        }
    }
    
    /**
     * Update transaction status based on query result
     */
    private function updateTransactionStatus(string $appTransId, string $status, array $response)
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $appTransId)
            ->orWhere('reference_number', $appTransId)
            ->first();
            
        if (!$transaction || $status === 'pending') {
            return;
        }
        
        $transaction->update([
            'status' => $status,
            'gateway_response' => array_merge(
                $transaction->gateway_response ?? [],
                ['query_result' => $response]
            ),
            'processed_at' => $status === 'completed' ? Carbon::now() : null,
            'failure_reason' => $status === 'failed' ? ($response['return_message'] ?? 'Payment failed') : null
        ]);
        
        // Update order if exists
        if ($transaction->order && $status !== 'pending') {
            $orderStatus = $status === 'completed' ? 'confirmed' : 'cancelled';
            Order::where('order_id', $transaction->order_id)->update(['order_status' => $orderStatus]);
        }
    }
}