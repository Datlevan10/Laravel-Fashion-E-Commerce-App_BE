<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;

class CartController extends Controller
{
    // method GET
    public function index()
    {
        $carts = Cart::get();
        if ($carts->count() > 0) {
            return response()->json([
                'message' => 'Get cart success',
                'data' => CartResource::collection($carts)
            ], 200);
        } else {
            return response()->json(['message' => 'No record available'], 200);
        }
    }

    // method GET Cart by cart_id
    public function getCartByCartId($cart_id)
    {
        $cart = Cart::with('cartDetails')->where('cart_id', $cart_id)->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        return response()->json([
            'message' => 'Get cart by cart_id successfully',
            'data' => new CartResource($cart)
        ], 200);
    }

    // method GET all Cart by customer_id
    public function getAllCartByCustomerId($customer_id)
    {
        $carts = Cart::where('customer_id', $customer_id)->get();

        if ($carts->count() > 0) {
            return response()->json([
                'message' => 'Get cart by customer_id successfully',
                'data' => CartResource::collection($carts)
            ], 200);
        } else {
            return response()->json(['message' => 'No cart found for this customer_id'], 404);
        }
    }

    // method GET Cart by customer_id
    public function getNotOrderedCartByCustomerId($customer_id)
    {
        $cart = Cart::with('cartDetails')->where('customer_id', $customer_id)
            ->where('cart_status', false)
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found for this customer'], 404);
        }

        return response()->json([
            'message' => 'Get cart by customer_id successfully',
            'data' => new CartResource($cart)
        ], 200);
    }

    // method POST
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
            'color' => 'nullable|string',
            'size' => 'required|string'
        ]);

        $customer_id = $request->input('customer_id');
        $product_id = $request->input('product_id');
        $quantity = $request->input('quantity');
        $color = $request->input('color', null);
        $size = $request->input('size');

        $product = Product::findOrFail($product_id);

        $cart = Cart::where('customer_id', $customer_id)
            ->where('cart_status', false)
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'customer_id' => $customer_id,
                'total_price' => 0,
                'cart_status' => false,
            ]);
        }

        $cartDetailQuery = CartDetail::where('cart_id', $cart->cart_id)
            ->where('product_id', $request->product_id)
            ->where('size', $request->size);
        
        if ($request->color) {
            $cartDetailQuery->where('color', $request->color);
        } else {
            $cartDetailQuery->whereNull('color');
        }
        
        $cartDetail = $cartDetailQuery->first();

        if ($cartDetail) {
            $cartDetail->quantity += $quantity;
            $cartDetail->total_price = $cartDetail->quantity * $product->new_price;
            $cartDetail->save();
        } else {
            $image_url = isset($product->image[0]['url']) ? $product->image[0]['url'] : null;

            CartDetail::create([
                'cart_id' => $cart->cart_id,
                'customer_id' => $customer_id,
                'product_id' => $product_id,
                'product_name' => $product->product_name,
                'quantity' => $quantity,
                'color' => $color,
                'size' => $size,
                'image' => $image_url,
                'unit_price' => $product->new_price,
                'total_price' => $quantity * $product->new_price,
            ]);
        }

        $cartTotal = CartDetail::where('cart_id', $cart->cart_id)->sum('total_price');
        $cart->total_price = $cartTotal;
        $cart->save();

        return response()->json(['message' => 'Product added to cart successfully'], 201);
    }

    // method GET Detail with cart_id
    public function show($cart_id)
    {
        try {
            $cart = Cart::where('cart_id', $cart_id)->first();
            if (!$cart) {
                return response()->json([
                    'message' => 'Cart not found',
                    'cart_id' => $cart_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get cart success with cart_id',
                'data' => new CartResource($cart)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get cart information', [
                'error' => $e->getMessage(),
                'cart_id' => $cart_id
            ]);

            return response()->json([
                'message' => 'Failed to get cart information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
