# Frontend Order API - Selective Cart Items

## Endpoint
```
POST /api/v1/orders
```

## Headers
```
Content-Type: application/json
Authorization: Bearer {token}
```

## Request Body Examples

### 1. Create Order with SELECTED Cart Items Only (NEW)
```json
{
  "cart_id": "dfg01",
  "customer_id": "001",
  "cart_detail_ids": ["cd_abc123", "cd_xyz789"],
  "payment_method": "cod",
  "shipping_address": "123 Main Street, District 1, HCMC",
  "discount": 10
}
```

### 2. Create Order with ALL Cart Items (Backward Compatible)
```json
{
  "cart_id": "dfg01",
  "customer_id": "001",
  "payment_method": "cod",
  "shipping_address": "123 Main Street, District 1, HCMC",
  "discount": 10
}
```

## cURL Example for Frontend Testing

### With Specific Cart Items:
```bash
curl -X POST "http://localhost:8000/api/v1/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "cart_id": "dfg01",
    "customer_id": "001",
    "cart_detail_ids": ["cd_item1_id", "cd_item2_id"],
    "payment_method": "cod",
    "shipping_address": "123 Main Street, District 1, HCMC"
  }'
```

## JavaScript/Axios Example
```javascript
// When user selects specific items and clicks "Place Order"
const createOrderWithSelectedItems = async () => {
  const selectedCartDetailIds = getSelectedCartDetailIds(); // Get IDs of checked items
  
  try {
    const response = await axios.post('/api/v1/orders', {
      cart_id: 'dfg01',
      customer_id: '001',
      cart_detail_ids: selectedCartDetailIds, // Array of selected cart_detail_ids
      payment_method: 'cod',
      shipping_address: userAddress,
      discount: discountPercentage
    }, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    console.log('Order created:', response.data);
  } catch (error) {
    console.error('Order creation failed:', error);
  }
};
```

## Key Points for Frontend Team:

1. **cart_detail_ids** is OPTIONAL:
   - If provided: Only those specific items will be included in the order
   - If omitted: ALL items in the cart will be included (old behavior)

2. **Validation**:
   - All provided cart_detail_ids must belong to the specified cart_id
   - Items must not be already checked out
   - If any validation fails, the entire order creation will fail

3. **Response** will include:
   - Order details with only the selected items
   - Payment transaction information
   - Total price calculated from selected items only

## Testing Workflow:
1. Get cart details for a customer
2. Select specific items (get their cart_detail_ids)
3. Send POST request with cart_detail_ids array
4. Verify order contains only selected items