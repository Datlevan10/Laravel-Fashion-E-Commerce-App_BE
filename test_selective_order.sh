#!/bin/bash

# Test script for selective cart item ordering
# This script demonstrates how to create an order with only specific cart items

API_URL="http://localhost:8000/api/v1"
TOKEN="YOUR_AUTH_TOKEN_HERE"

echo "========================================="
echo "Testing Selective Cart Item Order Creation"
echo "========================================="
echo ""

# Example 1: Create order with ALL items in cart (old behavior)
echo "1. Creating order with ALL cart items (traditional way):"
echo "---------------------------------------------------------"
curl -X POST "${API_URL}/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "cart_id": "dfg01",
    "customer_id": "001",
    "payment_method": "cod",
    "shipping_address": "123 Test Street"
  }' | jq '.'

echo ""
echo ""

# Example 2: Create order with SPECIFIC cart items only
echo "2. Creating order with SELECTED cart items only (new feature):"
echo "--------------------------------------------------------------"
curl -X POST "${API_URL}/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "cart_id": "dfg01",
    "customer_id": "001",
    "cart_detail_ids": ["cart_detail_id_1"],
    "payment_method": "cod",
    "shipping_address": "123 Test Street"
  }' | jq '.'

echo ""
echo ""

# Example 3: Create order with multiple specific cart items
echo "3. Creating order with MULTIPLE selected cart items:"
echo "----------------------------------------------------"
curl -X POST "${API_URL}/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "cart_id": "dfg01",
    "customer_id": "001",
    "cart_detail_ids": ["cart_detail_id_1", "cart_detail_id_2"],
    "payment_method": "cod",
    "shipping_address": "123 Test Street"
  }' | jq '.'

echo ""
echo "========================================="
echo "Test completed!"
echo "========================================="
echo ""
echo "USAGE NOTES:"
echo "1. Replace 'YOUR_AUTH_TOKEN_HERE' with a valid authentication token"
echo "2. Replace cart_id, customer_id, and cart_detail_ids with actual values from your database"
echo "3. The cart_detail_ids parameter is optional:"
echo "   - If provided: Only those specific items will be included in the order"
echo "   - If omitted: ALL items in the cart will be included (backward compatible)"