<?php

namespace App\Model\Response;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseResource extends JsonResource
{
    /**
     * Format a date for API response with timezone offset.
     */
    protected function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
