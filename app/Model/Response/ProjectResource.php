<?php

namespace App\Model\Response;

use Illuminate\Http\Request;

class ProjectResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'slug' => $this->slug?->value ?? $this->slug,
            'callback' => $this->callback,
            'value' => $this->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
