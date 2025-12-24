#!/bin/bash

echo "================================================"
echo "ZaloPay Order Creation & Payment Test"
echo "================================================"
echo ""

# Base URL
BASE_URL="http://localhost:8080/api/v1"

# Test data
CART_ID="LTUjFdZk"
CUSTOMER_ID="dN74GASL"
SHIPPING_ADDRESS="Thị trấn thọ Xuân, Thanh Hoá"
SHIPPING_PHONE="0987654321"

echo "Step 1: Creating order with ZaloPay payment method"
echo "------------------------------------------------"
echo "Cart ID: $CART_ID"
echo "Customer ID: $CUSTOMER_ID"
echo ""

# Create order
order_response=$(curl -s -X POST "$BASE_URL/orders" \
  -H "Content-Type: application/json" \
  -d '{
    "cart_id": "'$CART_ID'",
    "customer_id": "'$CUSTOMER_ID'",
    "discount": 0,
    "payment_method": "zalopay",
    "shipping_address": "'$SHIPPING_ADDRESS'",
    "shipping_phone": "'$SHIPPING_PHONE'"
  }')

echo "Order creation response:"
echo "$order_response" | python3 -m json.tool

# Check if order was created successfully
if echo "$order_response" | grep -q "order_id"; then
    # Extract order_id from response
    order_id=$(echo "$order_response" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['data']['order']['order_id'])" 2>/dev/null)
    
    if [ ! -z "$order_id" ]; then
        echo ""
        echo "✅ Order created successfully!"
        echo "Order ID: $order_id"
        echo ""
        echo "Step 2: Creating ZaloPay payment for order"
        echo "------------------------------------------------"
        
        # Create ZaloPay payment
        payment_response=$(curl -s -X POST "$BASE_URL/payments/zalopay/create" \
          -H "Content-Type: application/json" \
          -d '{
            "order_id": "'$order_id'",
            "description": "Payment for Fashion Store order '$order_id'"
          }')
        
        echo "ZaloPay payment response:"
        echo "$payment_response" | python3 -m json.tool
        
        # Extract payment URL
        order_url=$(echo "$payment_response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('order_url', ''))" 2>/dev/null)
        app_trans_id=$(echo "$payment_response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('app_trans_id', ''))" 2>/dev/null)
        
        if [ ! -z "$order_url" ]; then
            echo ""
            echo "================================================"
            echo "✅ PAYMENT CREATED SUCCESSFULLY!"
            echo "================================================"
            echo ""
            echo "Order ID: $order_id"
            echo "App Trans ID: $app_trans_id"
            echo ""
            echo "🔗 Payment URL (open in browser):"
            echo "$order_url"
            echo ""
            echo "================================================"
            echo "NEXT STEPS:"
            echo "================================================"
            echo "1. Open the payment URL above in your browser"
            echo "2. Complete the test payment in ZaloPay Sandbox"
            echo "3. Check payment status with:"
            echo ""
            echo "curl -X POST $BASE_URL/payments/zalopay/query \\"
            echo "  -H \"Content-Type: application/json\" \\"
            echo "  -d '{\"app_trans_id\": \"$app_trans_id\"}'"
            echo ""
        else
            echo ""
            echo "❌ Failed to create ZaloPay payment"
            echo "Please check the error message above"
        fi
    else
        echo ""
        echo "❌ Could not extract order_id from response"
    fi
else
    echo ""
    echo "❌ Failed to create order"
    echo "Please check the error message above"
    echo ""
    echo "Common issues:"
    echo "1. Cart not found or empty"
    echo "2. Customer not found"
    echo "3. Payment method not active"
    echo ""
fi

echo "================================================"
echo "Test completed"
echo "================================================"