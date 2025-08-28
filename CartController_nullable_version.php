<?php

// Alternative version that properly handles nullable colors
// Use this AFTER running the database migration

// In the store() method:
$color = $request->input('color'); // Can be null

// Cart detail lookup with proper null handling:
$cartDetailQuery = CartDetail::where('cart_id', $cart->cart_id)
    ->where('product_id', $request->product_id)
    ->where('size', $request->size);

if (is_null($color)) {
    $cartDetailQuery->whereNull('color');
} else {
    $cartDetailQuery->where('color', $color);
}

$cartDetail = $cartDetailQuery->first();

// In CartDetail::create():
CartDetail::create([
    'cart_id' => $cart->cart_id,
    'customer_id' => $customer_id,
    'product_id' => $product_id,
    'product_name' => $product->product_name,
    'quantity' => $quantity,
    'color' => $color, // Can be null after migration
    'size' => $size,
    'image' => $image_url,
    'unit_price' => $product->new_price,
    'total_price' => $quantity * $product->new_price,
]);