<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CartDetail;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "================================================\n";
echo "Setting Up Test Order Data\n";
echo "================================================\n\n";

DB::beginTransaction();

try {
    // Check Customer
    $customerId = 'dN74GASL';
    $customer = Customer::where('customer_id', $customerId)->first();
    
    if (!$customer) {
        echo "❌ Customer not found. Please create customer first.\n";
        DB::rollBack();
        exit(1);
    }
    
    echo "✅ Customer found: {$customer->customer_id}\n";
    
    // Check if we should reuse existing cart or create new one
    $existingCart = Cart::where('customer_id', $customerId)
        ->where('cart_status', false)
        ->first();
    
    if ($existingCart) {
        $cart = $existingCart;
        $testCartId = $cart->cart_id;
        echo "✅ Using existing active cart: {$testCartId}\n";
        
        // Clear existing items that are not checked out
        CartDetail::where('cart_id', $testCartId)
            ->where('is_checked_out', false)
            ->delete();
        echo "   Cleared existing cart items\n";
    } else {
        $cart = Cart::create([
            'customer_id' => $customerId,
            'total_price' => 0,
            'cart_status' => false
        ]);
        $testCartId = $cart->cart_id;
        echo "✅ Created new cart: {$testCartId}\n";
    }
    
    // Get some products to add to cart
    $products = Product::whereNotNull('new_price')
        ->where('new_price', '>', 0)
        ->orderBy('new_price', 'asc') // Get cheaper products first
        ->limit(2)
        ->get();
    
    if ($products->count() == 0) {
        echo "❌ No active products found in database\n";
        DB::rollBack();
        exit(1);
    }
    
    echo "✅ Adding products to cart:\n";
    
    $totalPrice = 0;
    foreach ($products as $index => $product) {
        $quantity = 1; // Use 1 unit to avoid price overflow
        $itemTotal = $product->new_price * $quantity;
        $totalPrice += $itemTotal;
        
        // Extract image URL from nested array structure
        $image = null;
        if (is_array($product->image) && !empty($product->image)) {
            if (isset($product->image[0]['url'])) {
                $image = $product->image[0]['url'];
            }
        }
        
        // Extract color from nested array structure
        $color = 'Default';
        if (is_array($product->color) && !empty($product->color)) {
            if (isset($product->color[0]['color_code'])) {
                $color = $product->color[0]['color_code'];
            }
        }
        
        // Extract size from nested array structure  
        $size = 'M';
        if (is_array($product->size) && !empty($product->size)) {
            if (isset($product->size[0]['size'])) {
                $size = $product->size[0]['size'];
            }
        }
        
        // Generate unique cart detail ID
        $cartDetailId = 'CD_TEST_' . uniqid();
        
        CartDetail::create([
            'cart_detail_id' => $cartDetailId,
            'cart_id' => $testCartId,
            'customer_id' => $customerId,
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'quantity' => $quantity,
            'color' => $color,
            'size' => $size,
            'image' => $image,
            'unit_price' => $product->new_price,
            'total_price' => $itemTotal,
            'is_checked_out' => false
        ]);
        
        echo "   - {$product->product_name} x{$quantity} = " . number_format($itemTotal) . " VND\n";
    }
    
    echo "\n✅ Cart total: " . number_format($totalPrice) . " VND\n";
    
    // Update cart total price
    $cart->update(['total_price' => $totalPrice]);
    
    DB::commit();
    
    echo "\n================================================\n";
    echo "✅ Test data setup complete!\n";
    echo "================================================\n\n";
    
    echo "Use this data for testing:\n";
    echo "{\n";
    echo '  "cart_id": "' . $testCartId . '",' . "\n";
    echo '  "customer_id": "' . $customerId . '",' . "\n";
    echo '  "discount": 0,' . "\n";
    echo '  "payment_method": "zalopay",' . "\n";
    echo '  "shipping_address": "Thị trấn thọ Xuân, Thanh Hoá",' . "\n";
    echo '  "shipping_phone": "0987654321"' . "\n";
    echo "}\n\n";
    
    echo "Run the test with:\n";
    echo "./test_order_zalopay.sh\n\n";
    
    echo "Or update test_order_zalopay.sh with:\n";
    echo 'CART_ID="' . $testCartId . '"' . "\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}