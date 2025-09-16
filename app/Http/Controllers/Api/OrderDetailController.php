<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderDetailResource;

class OrderDetailController extends Controller
{
    // method GET
    public function index()
    {
        $order_details = OrderDetail::get();
        if ($order_details->count() > 0) {
            return response()->json([
                // 'message' => 'Get cart success',
                'data' => OrderDetailResource::collection($order_details)
            ], 200);
        } else {
            return response()->json(['message' => 'No record available'], 200);
        }
    }

    // method GET Detail with order_detail_id
    public function show($order_detail_id)
    {
        try {
            $order_detail = OrderDetail::where('order_detail_id', $order_detail_id)->first();
            if (!$order_detail) {
                return response()->json([
                    'message' => 'Order detail not found',
                    'order_detail_id' => $order_detail_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get order detail success with order_detail_id',
                'data' => new OrderDetailResource($order_detail)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get order detail information', [
                'error' => $e->getMessage(),
                'order_detail_id' => $order_detail_id
            ]);

            return response()->json([
                'message' => 'Failed to get order detail information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // method GET Order Details by order_id
    public function getOrderDetails($order_id)
    {
        try {
            $order_details = OrderDetail::where('order_id', $order_id)->get();
            if ($order_details->count() === 0) {
                return response()->json([
                    'message' => 'No order details found for this order',
                    'order_id' => $order_id
                ], 404);
            }

            return response()->json([
                'data' => OrderDetailResource::collection($order_details)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get order details', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ]);

            return response()->json([
                'message' => 'Failed to get order details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
