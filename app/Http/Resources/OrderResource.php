<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'staff_id' => $this->staff_id,
            'order_date' => $this->order_date,
            'payment_method' => $this->payment_method,
            'shipping_address' => $this->shipping_address,
            'notes' => $this->notes,
            'discount' => $this->discount,
            'total_price' => $this->total_price,
            'order_status' => $this->order_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Include payment transaction when loaded
            'payment_transactions' => PaymentTransactionResource::collection($this->whenLoaded('paymentTransactions')),
            'latest_payment_transaction' => new PaymentTransactionResource($this->whenLoaded('latestPaymentTransaction')),
        ];
    }
}
