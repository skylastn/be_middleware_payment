<?php

namespace App\Model\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'title',
        'detail',
    ];

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'category_id', 'id');
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): void
    {
        $this->detail = $detail;
    }
}
