<?php

namespace App\Model\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    protected $fillable = ['key', 'value'];

    /** @return array */
    public function getValue(): string
    {
        return $this->value;
    }

    /** @param array|string $value */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
