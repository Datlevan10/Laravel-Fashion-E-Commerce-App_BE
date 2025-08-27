<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'category_name' => $this->category->category_name ?? null,
            'product_name' => $this->product_name,
            'description' => $this->description,
            'color' => $this->color,
            'size' => $this->size,
            'image' => $this->formatImageUrls($this->image),
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'total_review' => $this->total_review,
            'average_review' => $this->average_review,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Format image URLs to ensure they are properly accessible
     */
    private function formatImageUrls($images)
    {
        if (!$images || !is_array($images)) {
            return $images;
        }

        return array_map(function ($image) {
            if (is_array($image) && isset($image['url'])) {
                // Ensure the URL starts with /storage and is properly formatted
                $url = $image['url'];
                if (!str_starts_with($url, '/storage/')) {
                    $url = '/storage/' . ltrim($url, '/');
                }
                return ['url' => $url];
            }
            return $image;
        }, $images);
    }
}
