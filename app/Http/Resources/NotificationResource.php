<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $relatedData = null;

        if ($this->type === 'products') {
            $relatedData = $this->product ? [
                'product_id' => $this->product->product_id,
                'product_name' => $this->product->product_name,
                'category_id' => $this->product->category_id,
                // 'category_name' => $this->category->category_name,
                'product_name' => $this->product->product_name,
                'description' => $this->product->description,
                'color' => $this->product->color,
                'size' => $this->product->size,
                'image' => $this->product->image,
                'old_price' => $this->product->old_price,
                'new_price' => $this->product->new_price,
                'total_review' => $this->product->total_review,
                'average_review' => $this->product->average_review,
                'note' => $this->product->note,
            ] : null;
        } elseif ($this->type === 'orders') {
            $relatedData = $this->order ? [
                'order_status' => $this->order->order_status,
                'total_price' => $this->order->total_price,
            ] : null;
        }

        return [
            'notification_id' => $this->notification_id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'related_id' => $this->related_id,
            'product_id' => $this->product_id,
            'message' => $this->message,
            'is_read' => $this->is_read,
            'related_data' => $relatedData,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
