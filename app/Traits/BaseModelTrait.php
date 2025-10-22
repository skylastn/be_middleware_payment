<?php

namespace App\Traits;

use App\Http\Helper\FormatHelper;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

trait BaseModelTrait
{
    /**
     * Custom Find or Fail method
     *
     * @param int|string $id
     * @return static
     */
    public static function findOrFailCustom(int|string $id): static
    {
        $instance = static::find($id);

        if (!FormatHelper::isNotEmpty($instance)) {
            throw new Exception(Str::ucfirst(class_basename(static::class)) . ' not found');
        }

        return $instance;
    }
}
