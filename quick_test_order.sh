#!/bin/bash

echo "Testing Order Creation with ZaloPay..."
echo ""

# Simple curl test
curl -s -X POST http://localhost:8080/api/v1/orders \
-H "Content-Type: application/json" \
-d '{"cart_id":"LTUjFdZk","customer_id":"dN74GASL","discount":0,"payment_method":"zalopay","shipping_address":"Test Address","shipping_phone":"0987654321"}' \
| python3 -m json.tool

echo ""
echo "Done!"