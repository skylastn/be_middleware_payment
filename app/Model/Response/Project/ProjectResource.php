<?php

namespace App\Model\Response\Project;

use App\Model\Response\ResponseResource;
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
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
