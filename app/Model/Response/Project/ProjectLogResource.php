<?php

namespace App\Model\Response\Project;

use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class ProjectLogResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => self::parseLogValue($this->value),
            'ip' => $this->ip,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function parseLogValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) {
                $secondDecoded = json_decode(trim($decoded), true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($secondDecoded) || is_object($secondDecoded))) {
                    return $secondDecoded;
                }

                return $decoded;
            }

            return $decoded;
        }

        return $value;
    }
}
