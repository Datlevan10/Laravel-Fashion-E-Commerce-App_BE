<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\PaymentMethod;

class QRPaymentService
{
    /**
     * Generate QR code payload and URL for payment
     */
    public function generateQRCode(PaymentTransaction $transaction): array
    {
        $paymentMethod = $transaction->paymentMethod;
        
        switch ($paymentMethod->code) {
            case 'momo':
                return $this->generateMoMoQR($transaction);
            case 'vnpay':
                return $this->generateVNPayQR($transaction);
            case 'zalopay':
                return $this->generateZaloPayQR($transaction);
            case 'bank_transfer':
                return $this->generateBankTransferQR($transaction);
            default:
                return $this->generateGenericQR($transaction);
        }
    }

    /**
     * Generate MoMo QR code
     */
    private function generateMoMoQR(PaymentTransaction $transaction): array
    {
        // MoMo QR format: https://developer.momo.vn/v3/docs/payment/api/wallet/onetime
        $payload = json_encode([
            'partnerCode' => config('services.momo.partner_code', 'DEMO'),
            'requestId' => $transaction->transaction_id,
            'amount' => (string) $transaction->amount,
            'orderId' => $transaction->order_id,
            'orderInfo' => "Payment for order {$transaction->order_id}",
            'redirectUrl' => config('app.url') . "/api/payments/momo/callback",
            'ipnUrl' => config('app.url') . "/api/payments/momo/ipn",
            'extraData' => base64_encode(json_encode([
                'transaction_id' => $transaction->transaction_id,
                'order_id' => $transaction->order_id
            ]))
        ]);

        return [
            'qr_code_payload' => $payload,
            'qr_code_url' => $this->generateQRImageUrl($payload),
            'payment_url' => "https://test-payment.momo.vn/v2/gateway/pay?t=" . base64_encode($payload)
        ];
    }

    /**
     * Generate VNPay QR code
     */
    private function generateVNPayQR(PaymentTransaction $transaction): array
    {
        // VNPay QR format: https://sandbox.vnpayment.vn/apis/docs/huong-dan-tich-hop/
        $payload = json_encode([
            'vnp_TmnCode' => config('services.vnpay.tmn_code', 'DEMO'),
            'vnp_Amount' => $transaction->amount * 100, // VNPay uses cents
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => request()->ip(),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => "Payment for order {$transaction->order_id}",
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => config('app.url') . "/api/payments/vnpay/callback",
            'vnp_TxnRef' => $transaction->transaction_id,
            'vnp_Version' => '2.1.0'
        ]);

        return [
            'qr_code_payload' => $payload,
            'qr_code_url' => $this->generateQRImageUrl($payload),
            'payment_url' => "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?" . http_build_query(json_decode($payload, true))
        ];
    }

    /**
     * Generate Bank Transfer QR code
     */
    private function generateBankTransferQR(PaymentTransaction $transaction): array
    {
        // Vietnamese QR standard for bank transfer
        $bankInfo = $transaction->paymentMethod->api_config ?? [
            'bank_code' => '970422', // MB Bank example
            'account_number' => '0123456789',
            'account_name' => 'FASHION STORE'
        ];

        $payload = json_encode([
            'bank_code' => $bankInfo['bank_code'],
            'account_number' => $bankInfo['account_number'],
            'account_name' => $bankInfo['account_name'],
            'amount' => $transaction->amount,
            'description' => "Thanh toan don hang {$transaction->order_id}",
            'transaction_id' => $transaction->transaction_id
        ]);

        // VietQR format for bank transfer
        $vietQRPayload = $this->generateVietQRPayload([
            'bank_code' => $bankInfo['bank_code'],
            'account_number' => $bankInfo['account_number'],
            'amount' => $transaction->amount,
            'description' => "TT {$transaction->order_id}"
        ]);

        return [
            'qr_code_payload' => $vietQRPayload,
            'qr_code_url' => $this->generateQRImageUrl($vietQRPayload),
            'bank_info' => $bankInfo
        ];
    }

    /**
     * Generate generic QR code for other payment methods
     */
    private function generateGenericQR(PaymentTransaction $transaction): array
    {
        $payload = json_encode([
            'transaction_id' => $transaction->transaction_id,
            'order_id' => $transaction->order_id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'payment_method' => $transaction->paymentMethod->code,
            'timestamp' => now()->toISOString()
        ]);

        return [
            'qr_code_payload' => $payload,
            'qr_code_url' => $this->generateQRImageUrl($payload)
        ];
    }

    /**
     * Generate VietQR payload for Vietnamese banks
     */
    private function generateVietQRPayload(array $data): string
    {
        // VietQR format: bank_code|account_number|amount|description
        return implode('|', [
            $data['bank_code'] ?? '',
            $data['account_number'] ?? '',
            $data['amount'] ?? '',
            $data['description'] ?? ''
        ]);
    }

    /**
     * Generate QR code image URL using external service or local generation
     */
    private function generateQRImageUrl(string $payload): string
    {
        // Using QR Server API for demo purposes
        // In production, consider using a local QR generator library
        $encodedPayload = urlencode($payload);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$encodedPayload}";
    }

    /**
     * Generate ZaloPay payment data
     */
    public function generateZaloPayment(PaymentTransaction $transaction, $description = null): array
    {
        $config = $this->getZaloPayConfig();
        $appTransId = $config['app_id'] . '_' . date('ymd') . '_' . $transaction->transaction_id;
        
        $embedData = json_encode([
            'transaction_id' => $transaction->transaction_id,
            'order_id' => $transaction->order_id
        ]);

        $items = json_encode([
            [
                'itemid' => $transaction->order_id,
                'itemname' => $description ?: "Payment for order {$transaction->order_id}",
                'itemprice' => (int)$transaction->amount,
                'itemquantity' => 1
            ]
        ]);

        $orderData = [
            'app_id' => $config['app_id'],
            'app_user' => $transaction->order->customer_id ?? 'user_' . time(),
            'app_time' => round(microtime(true) * 1000),
            'amount' => (int)$transaction->amount,
            'app_trans_id' => $appTransId,
            'embed_data' => $embedData,
            'item' => $items,
            'description' => $description ?: "Payment for order {$transaction->order_id}",
            'callback_url' => config('app.url') . '/api/payments/zalopay/callback'
        ];

        $orderData['mac'] = $this->generateZaloPayMac($orderData, $config['key1']);

        return array_merge($orderData, [
            'payment_url' => 'zalopayapp://open',
            'qr_code_url' => $this->generateQRImageUrl($appTransId)
        ]);
    }

    /**
     * Generate ZaloPay QR code (for backward compatibility)
     */
    private function generateZaloPayQR(PaymentTransaction $transaction): array
    {
        return $this->generateZaloPayment($transaction);
    }

    /**
     * Query ZaloPay transaction status
     */
    public function queryZaloPayStatus(string $appTransId): array
    {
        $config = $this->getZaloPayConfig();
        
        $queryData = [
            'app_id' => $config['app_id'],
            'app_trans_id' => $appTransId
        ];

        $queryData['mac'] = hash_hmac('sha256', $config['app_id'] . '|' . $appTransId . '|' . $config['key1'], $config['key1']);

        // In production, make HTTP request to ZaloPay query endpoint
        // For demo, return mock response
        return [
            'return_code' => 1, // 1: success, 2: failed, 3: pending
            'return_message' => 'Giao dịch thành công',
            'sub_return_code' => 1,
            'sub_return_message' => '',
            'is_processing' => false,
            'amount' => 0,
            'zp_trans_id' => time()
        ];
    }

    /**
     * Validate ZaloPay callback signature
     */
    public function validateZaloPayCallback(array $callbackData): bool
    {
        $config = $this->getZaloPayConfig();
        
        if (!isset($callbackData['mac'])) {
            return false;
        }

        $reqMac = $callbackData['mac'];
        unset($callbackData['mac']);

        $expectedMac = hash_hmac('sha256', 
            $callbackData['data'] . '|' . $config['key2'], 
            $config['key2']
        );

        return hash_equals($reqMac, $expectedMac);
    }

    /**
     * Get ZaloPay configuration
     */
    private function getZaloPayConfig(): array
    {
        return [
            'app_id' => config('services.zalopay.app_id', '2553'),
            'key1' => config('services.zalopay.key1', 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL'),
            'key2' => config('services.zalopay.key2', 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz'),
            'endpoint' => config('services.zalopay.endpoint', 'https://sb-openapi.zalopay.vn/v2')
        ];
    }

    /**
     * Generate ZaloPay MAC signature
     */
    private function generateZaloPayMac(array $data, string $key): string
    {
        $macData = $data['app_id'] . '|' . $data['app_trans_id'] . '|' . $data['app_user'] . '|' . 
                   $data['amount'] . '|' . $data['app_time'] . '|' . $data['embed_data'] . '|' . $data['item'];
        
        return hash_hmac('sha256', $macData, $key);
    }

    /**
     * Validate payment status from gateway response
     */
    public function validatePaymentStatus(string $gatewayCode, array $response): string
    {
        switch ($gatewayCode) {
            case 'momo':
                return $this->validateMoMoStatus($response);
            case 'vnpay':
                return $this->validateVNPayStatus($response);
            case 'zalopay':
                return $this->validateZaloPayStatus($response);
            default:
                return 'pending';
        }
    }

    /**
     * Validate MoMo payment status
     */
    private function validateMoMoStatus(array $response): string
    {
        if (isset($response['resultCode'])) {
            return $response['resultCode'] == 0 ? 'completed' : 'failed';
        }
        return 'pending';
    }

    /**
     * Validate VNPay payment status
     */
    private function validateVNPayStatus(array $response): string
    {
        if (isset($response['vnp_ResponseCode'])) {
            return $response['vnp_ResponseCode'] == '00' ? 'completed' : 'failed';
        }
        return 'pending';
    }

    /**
     * Validate ZaloPay payment status
     */
    private function validateZaloPayStatus(array $response): string
    {
        if (isset($response['status'])) {
            return $response['status'] == 1 ? 'completed' : 'failed';
        }
        if (isset($response['return_code'])) {
            return $response['return_code'] == 1 ? 'completed' : 'failed';
        }
        return 'pending';
    }
}