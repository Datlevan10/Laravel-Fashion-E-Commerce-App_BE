#!/bin/bash

ORDER_ID="QI0rNaQz"

echo "Creating ZaloPay payment for order: $ORDER_ID"
echo ""

response=$(curl -s -X POST http://localhost:8080/api/v1/payments/zalopay/create \
-H "Content-Type: application/json" \
-d "{\"order_id\":\"$ORDER_ID\",\"description\":\"Payment for Fashion Store order $ORDER_ID\"}")

echo "Response:"
echo "$response" | python3 -m json.tool

# Extract payment URL if exists
order_url=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('order_url', ''))" 2>/dev/null)

if [ ! -z "$order_url" ]; then
    echo ""
    echo "========================================"
    echo "✅ ZaloPay Payment Created Successfully!"
    echo "========================================"
    echo ""
    echo "🔗 Payment URL:"
    echo "$order_url"
    echo ""
    echo "Open this URL in your browser to complete the payment"
fi