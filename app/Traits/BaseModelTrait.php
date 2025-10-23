<?php

namespace App\Traits;

use App\Http\Helper\FormatHelper;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait BaseModelTrait
{
    /**
     * Custom Find or Fail method.
     *
     * @param int|string $id
     * @return static
     * @throws Exception
     */
    public static function findOrFailCustom(int|string $id): static
    {
        $instance = static::find($id);

        if (!FormatHelper::isNotEmpty($instance)) {
            throw new Exception(Str::ucfirst(class_basename(static::class)) . ' not found');
        }

        return $instance;
    }

    /**
     * Create a new record and immediately return the full model instance.
     *
     * @param array $data
     * @return static
     * @throws Exception
     */
    public static function createAndFind(array $data): static
    {
        $model = static::create($data);

        // Panggil findOrFailCustom biar konsisten dengan validasi not found
        return static::findOrFailCustom($model->id);
    }
}
