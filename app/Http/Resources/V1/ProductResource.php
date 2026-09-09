<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if (! empty($this->image)) {
            $imageUrl = str_starts_with($this->image, 'http')
                ? $this->image
                : Storage::disk('public')->url($this->image);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'formatted_price' => '$' . number_format((float) $this->price, 2, '.', ','),
            'stock' => (int) $this->stock,
            'in_stock' => (int) $this->stock > 0,
            'image' => $this->image,
            'image_url' => $imageUrl,
            'is_active' => (bool) $this->is_active,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
