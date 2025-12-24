<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CartDetail;
use App\Models\PaymentMethod;

echo "================================================\n";
echo "Checking Test Data\n";
echo "================================================\n\n";

// Check Customer
$customerId = 'dN74GASL';
$customer = Customer::where('customer_id', $customerId)->first();

if ($customer) {
    echo "✅ Customer found:\n";
    echo "   ID: {$customer->customer_id}\n";
    echo "   Name: {$customer->name}\n";
    echo "   Phone: {$customer->phone_number}\n";
    echo "   Address: {$customer->address}\n\n";
} else {
    echo "❌ Customer NOT found with ID: $customerId\n\n";
}

// Check Cart
$cartId = '1ubluVrJ';
$cart = Cart::where('cart_id', $cartId)->first();

if ($cart) {
    echo "✅ Cart found:\n";
    echo "   Cart ID: {$cart->cart_id}\n";
    echo "   Customer ID: {$cart->customer_id}\n";
    echo "   Status: " . ($cart->cart_status ? 'Checked out' : 'Active') . "\n";
    
    // Check cart details
    $cartDetails = CartDetail::where('cart_id', $cartId)
        ->where('is_checked_out', false)
        ->get();
    
    echo "   Items in cart (not checked out): {$cartDetails->count()}\n";
    
    if ($cartDetails->count() > 0) {
        $totalPrice = $cartDetails->sum('total_price');
        echo "   Total price: " . number_format($totalPrice) . " VND\n";
        
        echo "\n   Cart items:\n";
        foreach ($cartDetails as $detail) {
            echo "   - {$detail->product_name} (Qty: {$detail->quantity}, Price: " . number_format($detail->total_price) . " VND)\n";
        }
    }
    echo "\n";
} else {
    echo "❌ Cart NOT found with ID: $cartId\n\n";
}

// Check ZaloPay payment method
$zalopay = PaymentMethod::where('code', 'zalopay')->first();

if ($zalopay) {
    echo "✅ ZaloPay payment method found:\n";
    echo "   ID: {$zalopay->payment_method_id}\n";
    echo "   Name: {$zalopay->name}\n";
    echo "   Active: " . ($zalopay->is_active ? 'Yes' : 'No') . "\n";
    echo "   Min amount: " . number_format($zalopay->minimum_amount) . " VND\n";
    echo "   Max amount: " . number_format($zalopay->maximum_amount) . " VND\n\n";
} else {
    echo "❌ ZaloPay payment method NOT found\n\n";
}

// Check all payment methods
echo "Available payment methods:\n";
$methods = PaymentMethod::where('is_active', true)->get();
foreach ($methods as $method) {
    echo "   - {$method->name} (code: {$method->code})\n";
}

echo "\n================================================\n";
echo "Summary:\n";
echo "================================================\n";

if (!$customer) {
    echo "⚠️  Need to create customer with ID: $customerId\n";
}

if (!$cart || $cart->cart_status) {
    echo "⚠️  Need to create active cart with ID: $cartId\n";
}

if ($cart && $cartDetails->count() == 0) {
    echo "⚠️  Cart is empty - need to add items\n";
}

if (!$zalopay) {
    echo "⚠️  Need to initialize ZaloPay payment method\n";
}

echo "\n";