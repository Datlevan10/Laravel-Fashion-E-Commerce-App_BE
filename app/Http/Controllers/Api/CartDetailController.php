<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\CartDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartDetailResource;

class CartDetailController extends Controller
{
    // method GET
    public function index()
    {
        $cart_details = CartDetail::get();
        if ($cart_details->count() > 0) {
            return response()->json([
                'message' => 'Get cart detail success',
                'data' => CartDetailResource::collection($cart_details)
            ], 200);
        } else {
            return response()->json(['message' => 'No record available'], 200);
        }
    }

    // method GET CartDetail by cart_id
    public function getCartDetailByCartId($cart_id)
    {
        $cart_details = CartDetail::where('cart_id', $cart_id)->get();

        if ($cart_details->count() > 0) {
            return response()->json([
                'message' => 'Get cart details by cart_id successfully',
                'data' => CartDetailResource::collection($cart_details)
            ], 200);
        } else {
            return response()->json(['message' => 'No cart details found for this cart_id'], 404);
        }
    }

    // method GET all CartDetail by customer_id
    public function getAllCartDetailByCustomerId($customer_id)
    {
        $cart_details = CartDetail::where('customer_id', $customer_id)->get();

        if ($cart_details->count() > 0) {
            return response()->json([
                'message' => 'Get cart details by customer_id successfully',
                'data' => CartDetailResource::collection($cart_details)
            ], 200);
        } else {
            return response()->json(['message' => 'No cart details found for this customer_id'], 404);
        }
    }

    // method GET CartDetails with is_checked_out = false for specific customer
    public function getNotOrderedCartDetailByCustomerId($customer_id)
    {
        try {
            // CRITICAL FIX: Filter by BOTH customer_id AND is_checked_out status
            $uncheckedCartDetails = CartDetail::where('customer_id', $customer_id)
                                             ->where('is_checked_out', false)
                                             ->get();

            if ($uncheckedCartDetails->count() > 0) {
                return response()->json([
                    'message' => 'Get unchecked cart details for customer successfully',
                    'customer_id' => $customer_id, // Include customer_id for verification
                    'data' => CartDetailResource::collection($uncheckedCartDetails)
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No unchecked cart details found for this customer',
                    'customer_id' => $customer_id,
                    'data' => []
                ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get unchecked cart details for customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customer_id
            ]);

            return response()->json([
                'message' => 'Failed to get unchecked cart details',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // method GET Detail with cart_detail_id
    public function show($cart_detail_id)
    {
        try {
            $cart_detail = CartDetail::where('cart_detail_id', $cart_detail_id)->first();
            if (!$cart_detail) {
                return response()->json([
                    'message' => 'Cart detail not found',
                    'cart_detail_id' => $cart_detail_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get cart detail success with cart_detail_id',
                'data' => new CartDetailResource($cart_detail)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get cart detail information', [
                'error' => $e->getMessage(),
                'cart_detail_id' => $cart_detail_id
            ]);

            return response()->json([
                'message' => 'Failed to get cart detail information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // method DELETE a cart item by cart_detail_id
    public function deleteItemInCart($cart_detail_id)
    {
        $cartDetail = CartDetail::find($cart_detail_id);

        if (!$cartDetail) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        $cart_id = $cartDetail->cart_id;

        $cartDetail->delete();

        $cartTotal = CartDetail::where('cart_id', $cart_id)->sum('total_price');
        $cart = Cart::find($cart_id);

        if ($cart) {
            $cart->total_price = $cartTotal;
            $cart->save();
        }

        return response()->json(['message' => 'Item removed from cart and total price updated successfully'], 200);
    }

    // method POST - Create cart detail (if needed)
    public function store(Request $request)
    {
        // This method can be implemented later if needed
        return response()->json(['message' => 'Method not implemented yet'], 501);
    }

    // method PUT - Update cart item quantity
    public function update(Request $request, $cart_detail_id)
    {
        try {
            // Validate the request
            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            // Find the cart detail
            $cartDetail = CartDetail::where('cart_detail_id', $cart_detail_id)->first();

            if (!$cartDetail) {
                return response()->json([
                    'message' => 'Cart item not found',
                    'cart_detail_id' => $cart_detail_id
                ], 404);
            }

            // Update quantity and recalculate total_price
            $newQuantity = $request->input('quantity');
            
            // Check if product has sufficient stock for the new quantity
            $product = \App\Models\Product::find($cartDetail->product_id);
            if ($product && $product->quantity_in_stock !== null && $product->quantity_in_stock < $newQuantity) {
                return response()->json([
                    'message' => 'Insufficient stock available',
                    'available_quantity' => $product->quantity_in_stock,
                    'requested_quantity' => $newQuantity
                ], 400);
            }
            
            $cartDetail->quantity = $newQuantity;
            $cartDetail->total_price = $cartDetail->unit_price * $newQuantity;
            $cartDetail->save();

            // Update the cart's overall total_price
            $cart_id = $cartDetail->cart_id;
            $cartTotal = CartDetail::where('cart_id', $cart_id)->sum('total_price');
            $cart = Cart::where('cart_id', $cart_id)->first();

            if ($cart) {
                $cart->total_price = $cartTotal;
                $cart->save();
            }

            return response()->json([
                'message' => 'Cart item quantity updated successfully',
                'data' => new CartDetailResource($cartDetail),
                'cart_total' => $cartTotal
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update cart item quantity', [
                'error' => $e->getMessage(),
                'cart_detail_id' => $cart_detail_id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to update cart item quantity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
