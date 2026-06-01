<?php

namespace App\Model\Entity;

use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateway extends Model
{
    use SoftDeletes, HasUuids, BaseModelTrait;

    protected $table = 'payment_gateways';

    protected $fillable = [
        'key',
        'name',
        'description',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    // ------------------------------------------------------------
    // Getter & Setter Methods (Explicit style)
    // ------------------------------------------------------------

    /** @return string */
    public function getKey(): string
    {
        return $this->key;
    }

    /** @param string $key */
    public function setKey(string $key): void
    {
        $this->key = strtolower($key);
    }

    /** @return string */
    public function getName(): string
    {
        return $this->name;
    }

    /** @param string $name */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @return string|null */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @param string|null $description */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
