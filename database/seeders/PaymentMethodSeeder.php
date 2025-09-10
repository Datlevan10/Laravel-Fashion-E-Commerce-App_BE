<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
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
                'payment_method_id' => 'PM003',
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'type' => 'bank_transfer',
                'logo' => 'bank-transfer-logo.png',
                'is_active' => true,
                'transaction_fee_percentage' => 0,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => null,
                'api_config' => [
                    'bank_code' => '970422',
                    'account_number' => '0123456789',
                    'account_name' => 'FASHION STORE'
                ],
                'supported_currencies' => ['VND'],
                'description' => 'Transfer money via bank account',
                'sort_order' => 3,
            ],
            [
                'payment_method_id' => 'PM004',
                'name' => 'Cash on Delivery',
                'code' => 'cod',
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
            ]
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['payment_method_id' => $method['payment_method_id']],
                $method
            );
        }
    }
}