<?php

namespace App\Model\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'category_id',
        'from',
        'bankCode',
        'value',
    ];

    // Keep the original eager-loaded relationships.
    protected $with = ['category'];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(PaymentCategory::class, 'category_id', 'id');
    }

    // ------------------------------------------------------------
    // Getter & Setter Methods
    // ------------------------------------------------------------

    public function getKeyAttribute(): string
    {
        return $this->attributes['key'] ?? '';
    }

    public function setKeyAttribute(string $key): void
    {
        $this->attributes['key'] = $key;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCategoryId(): ?int
    {
        return $this->category_id !== null ? (int) $this->category_id : null;
    }

    public function setCategoryId(?int $categoryId): void
    {
        $this->category_id = $categoryId;
    }

    public function getType(): ?string
    {
        return $this->category?->key;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    public function getBankCode(): ?string
    {
        return $this->bankCode;
    }

    public function setBankCode(?string $bankCode): void
    {
        $this->bankCode = $bankCode;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
