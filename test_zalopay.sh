#!/bin/bash

echo "================================"
echo "ZaloPay Integration Test Script"
echo "================================"
echo ""

# Base URL
BASE_URL="http://localhost:8080/api/v1"

# Test order details
ORDER_ID="ORD_TEST_$(date +%s)"
AMOUNT=50000
DESCRIPTION="Test payment for Fashion Store"

echo "1. Creating test order with ZaloPay payment..."
echo "   Order ID: $ORDER_ID"
echo "   Amount: $AMOUNT VND"
echo ""

# Create ZaloPay payment
response=$(curl -s -X POST "$BASE_URL/payments/zalopay/create" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "'$ORDER_ID'",
    "amount": '$AMOUNT',
    "description": "'$DESCRIPTION'"
  }')

echo "Response from ZaloPay create payment:"
echo "$response" | python3 -m json.tool

# Extract values from response
transaction_id=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('transaction_id', ''))")
order_url=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('order_url', ''))")
app_trans_id=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('app_trans_id', ''))")

if [ ! -z "$order_url" ]; then
    echo ""
    echo "================================"
    echo "Payment created successfully!"
    echo "================================"
    echo ""
    echo "Transaction ID: $transaction_id"
    echo "App Trans ID: $app_trans_id"
    echo ""
    echo "🔗 Payment URL (open in browser):"
    echo "$order_url"
    echo ""
    echo "================================"
    echo "NEXT STEPS:"
    echo "================================"
    echo "1. Open the payment URL above in your browser"
    echo "2. Complete the test payment in ZaloPay Sandbox"
    echo "3. Run the query status command below to check payment status:"
    echo ""
    echo "curl -X POST $BASE_URL/payments/zalopay/query \\"
    echo "  -H \"Content-Type: application/json\" \\"
    echo "  -d '{\"app_trans_id\": \"$app_trans_id\"}'"
    echo ""
else
    echo ""
    echo "❌ Failed to create payment"
    echo "Please check the error message above"
fi

echo ""
echo "================================"
echo "Test completed"
echo "================================"