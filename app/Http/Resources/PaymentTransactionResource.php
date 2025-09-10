<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'order_id' => $this->order_id,
            'payment_method_id' => $this->payment_method_id,
            'amount' => $this->amount,
            'fee_amount' => $this->fee_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'qr_code_url' => $this->qr_code_url,
            'qr_code_payload' => $this->qr_code_payload,
            'reference_number' => $this->reference_number,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'gateway_response' => $this->gateway_response,
            'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Include related models when loaded
            'order' => new OrderResource($this->whenLoaded('order')),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
        ];
    }
}