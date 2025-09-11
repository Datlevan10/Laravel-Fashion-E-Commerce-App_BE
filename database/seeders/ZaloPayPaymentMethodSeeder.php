<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class ZaloPayPaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zaloPayMethod = PaymentMethod::where('code', 'zalopay')->first();
        
        if (!$zaloPayMethod) {
            PaymentMethod::create([
                'payment_method_id' => 'PM' . uniqid(),
                'name' => 'ZaloPay',
                'code' => 'zalopay',
                'type' => 'digital_wallet',
                'logo' => '/images/payment-methods/zalopay-logo.png',
                'is_active' => true,
                'transaction_fee_percentage' => 2.5,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 50000000, // 50 million VND
                'api_config' => [
                    'app_id' => '2553',
                    'key1' => 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL',
                    'key2' => 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz',
                    'endpoint' => 'https://sb-openapi.zalopay.vn/v2'
                ],
                'supported_currencies' => ['VND'],
                'description' => 'Thanh toán qua ví điện tử ZaloPay - Nhanh chóng, an toàn',
                'sort_order' => 3,
            ]);
        }
    }
}