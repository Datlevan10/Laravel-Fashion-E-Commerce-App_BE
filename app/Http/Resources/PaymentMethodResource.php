<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_method_id' => $this->payment_method_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'logo' => $this->logo,
            'is_active' => $this->is_active,
            'transaction_fee_percentage' => $this->transaction_fee_percentage,
            'transaction_fee_fixed' => $this->transaction_fee_fixed,
            'minimum_amount' => $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount,
            'supported_currencies' => $this->supported_currencies,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}