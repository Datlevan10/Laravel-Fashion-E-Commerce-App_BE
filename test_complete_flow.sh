#!/bin/bash

echo "================================================"
echo "Complete ZaloPay Integration Test"
echo "================================================"
echo ""

# Step 1: Create Order
echo "Step 1: Creating Order with ZaloPay..."
echo "---------------------------------------"

order_response=$(curl -s -X POST http://localhost:8080/api/v1/orders \
-H "Content-Type: application/json" \
-d '{"cart_id":"2YMQWuMT","customer_id":"dN74GASL","discount":0,"payment_method":"zalopay","shipping_address":"Test Address","shipping_phone":"0987654321"}')

echo "Order Response:"
echo "$order_response" | python3 -m json.tool

# Extract order_id
order_id=$(echo "$order_response" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['data']['order']['order_id'])" 2>/dev/null)

if [ ! -z "$order_id" ]; then
    echo ""
    echo "✅ Order created successfully!"
    echo "Order ID: $order_id"
    echo ""
    
    # Step 2: Create ZaloPay Payment
    echo "Step 2: Creating ZaloPay Payment..."
    echo "------------------------------------"
    
    payment_response=$(curl -s -X POST http://localhost:8080/api/v1/payments/zalopay/create \
    -H "Content-Type: application/json" \
    -d "{\"order_id\":\"$order_id\",\"description\":\"Payment for Fashion Store order $order_id\"}")
    
    echo "Payment Response:"
    echo "$payment_response" | python3 -m json.tool
    
    # Extract payment URL
    order_url=$(echo "$payment_response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('order_url', ''))" 2>/dev/null)
    app_trans_id=$(echo "$payment_response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('app_trans_id', ''))" 2>/dev/null)
    
    if [ ! -z "$order_url" ]; then
        echo ""
        echo "================================================"
        echo "✅ COMPLETE SUCCESS!"
        echo "================================================"
        echo ""
        echo "Order ID: $order_id"
        echo "App Trans ID: $app_trans_id"
        echo ""
        echo "🔗 ZaloPay Payment URL:"
        echo "$order_url"
        echo ""
        echo "Next Steps:"
        echo "1. Open the URL above in your browser"
        echo "2. Complete payment in ZaloPay Sandbox"
        echo "3. Query status with:"
        echo ""
        echo "curl -X POST http://localhost:8080/api/v1/payments/zalopay/query \\"
        echo "  -H 'Content-Type: application/json' \\"
        echo "  -d '{\"app_trans_id\": \"$app_trans_id\"}'"
        echo ""
    else
        echo ""
        echo "❌ Failed to create ZaloPay payment"
    fi
else
    echo ""
    echo "❌ Failed to create order"
fi

echo "================================================"
echo "Test completed"
echo "================================================"