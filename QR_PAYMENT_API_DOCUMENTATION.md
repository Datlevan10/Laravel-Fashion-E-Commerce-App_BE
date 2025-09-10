# QR Payment Integration API Documentation

## Overview
This document describes the QR Payment Integration feature for the Laravel Fashion E-Commerce App. The feature allows customers to create orders and generate QR codes for payment using various payment methods like MoMo, VNPay, and Bank Transfer.

## Database Changes

### Migration
- Added `qr_code_url` and `qr_code_payload` fields to the `payment_transactions` table

### Models
- Created `PaymentTransaction` model
- Created `PaymentMethod` model  
- Updated `Order` model to include payment transaction relationships

## API Endpoints

### Payment Methods
```
GET /api/payments/methods
```
Returns all active payment methods with their configurations.

**Response:**
```json
{
    "message": "Get payment methods success",
    "data": [
        {
            "payment_method_id": "PM001",
            "name": "MoMo E-Wallet",
            "code": "momo",
            "type": "digital_wallet",
            "logo": "momo-logo.png",
            "is_active": true,
            "transaction_fee_percentage": 0.5,
            "transaction_fee_fixed": 0,
            "minimum_amount": 10000,
            "maximum_amount": 50000000,
            "supported_currencies": ["VND"],
            "description": "Pay with MoMo E-Wallet"
        }
    ]
}
```

### Create Order (Updated)
```
POST /api/orders
```
Now requires `payment_method_id` instead of `payment_method` and automatically creates a payment transaction with QR code.

**Request:**
```json
{
    "cart_id": "CART123",
    "customer_id": "CUST123", 
    "payment_method_id": "PM001",
    "shipping_address": "123 Main St",
    "discount": 10
}
```

**Response:**
```json
{
    "message": "Order created successfully from cart",
    "data": {
        "order": {
            "order_id": "ORD123",
            "customer_id": "CUST123",
            "order_date": "2025-09-10 12:00:00",
            "payment_method": "MoMo E-Wallet",
            "total_price": 100000,
            "order_status": "pending"
        },
        "payment_transaction": {
            "transaction_id": "PAY123",
            "order_id": "ORD123",
            "payment_method_id": "PM001",
            "amount": 100000,
            "currency": "VND",
            "status": "pending",
            "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/...",
            "qr_code_payload": "{\"partnerCode\":\"DEMO\",...}"
        }
    }
}
```

### Generate QR Code for Existing Order
```
POST /api/payments/{orderId}/create
```
Creates a payment transaction and generates QR code for an existing order.

### Get Payment Transaction Details
```
GET /api/payments/{transactionId}
```
Returns detailed information about a payment transaction including QR code data.

### Check Payment Status
```
GET /api/payments/{transactionId}/status
```
Returns the current status of a payment transaction and associated order.

**Response:**
```json
{
    "message": "Payment status retrieved successfully",
    "data": {
        "transaction_id": "PAY123",
        "order_id": "ORD123",
        "status": "pending",
        "amount": 100000,
        "currency": "VND",
        "processed_at": null,
        "order_status": "pending"
    }
}
```

### Payment Callback
```
POST /api/payments/callback
POST /api/payments/momo/callback
POST /api/payments/vnpay/callback
```
Handles payment confirmation from payment gateways.

### Manual Payment Confirmation
```
POST /api/payments/{transactionId}/confirm
```
For manual confirmation by admin or testing purposes.

**Request:**
```json
{
    "status": "completed",
    "gateway_transaction_id": "GATEWAY123",
    "notes": "Payment confirmed manually"
}
```

### Cancel Payment
```
PATCH /api/payments/{transactionId}/cancel
```
Cancels a pending payment transaction and updates order status.

### Get Payments by Order
```
GET /api/payments/order/{orderId}
```
Returns all payment transactions for a specific order.

## Status Mapping

The system automatically synchronizes payment and order statuses:

| Payment Status | Order Status |
|---------------|--------------|
| pending       | pending      |
| processing    | pending      |
| completed     | confirmed    |
| failed        | cancelled    |
| cancelled     | cancelled    |

## QR Code Generation

The system supports different QR code formats:

1. **MoMo**: Generates MoMo payment format with deep link
2. **VNPay**: Creates VNPay compatible QR code with payment URL
3. **Bank Transfer**: Uses VietQR standard for bank transfers
4. **Generic**: Default JSON format for other payment methods

## Usage Example

1. **Customer creates order:**
   ```
   POST /api/orders
   {
       "cart_id": "CART123",
       "customer_id": "CUST123",
       "payment_method_id": "PM001"
   }
   ```

2. **Frontend displays QR code using the returned qr_code_url**

3. **Customer scans and pays**

4. **Payment gateway sends callback:**
   ```
   POST /api/payments/momo/callback
   {
       "orderId": "PAY123",
       "resultCode": 0,
       "transId": "GATEWAY456"
   }
   ```

5. **System updates payment and order status automatically**

6. **Frontend can check status:**
   ```
   GET /api/payments/PAY123/status
   ```

## Security Notes

- All payment callbacks should be secured with proper webhook validation
- QR codes contain temporary payment URLs that expire
- Transaction IDs are unique and cannot be reused
- Payment confirmations are logged with timestamps and user information

## Testing

Use the provided PaymentMethodSeeder to create sample payment methods for testing:

```bash
php artisan db:seed --class=PaymentMethodSeeder
```

## Configuration

Add payment gateway configurations to your `.env` file:

```
# MoMo Configuration
MOMO_PARTNER_CODE=your_partner_code
MOMO_ACCESS_KEY=your_access_key
MOMO_SECRET_KEY=your_secret_key

# VNPay Configuration  
VNPAY_TMN_CODE=your_tmn_code
VNPAY_HASH_SECRET=your_hash_secret
```