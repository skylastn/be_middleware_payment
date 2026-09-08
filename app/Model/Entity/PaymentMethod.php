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
        'payment_gateway_id',
        'bankCode',
        'image',
        'is_active',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'payment_gateway_id' => 'string',
        'is_active' => 'boolean',
    ];

    protected $appends = ['type'];

    // Keep eager-loaded relationships
    protected $with = ['category', 'payment_gateway'];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(PaymentCategory::class, 'category_id', 'id');
    }

    public function payment_gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id', 'id');
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

    public function getPaymentGatewayId(): ?string
    {
        return $this->payment_gateway_id;
    }

    public function setPaymentGatewayId(?string $paymentGatewayId): void
    {
        $this->payment_gateway_id = $paymentGatewayId;
    }

    public function getType(): ?string
    {
        return $this->category?->key;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->getType();
    }

    public function getBankCode(): ?string
    {
        return $this->bankCode;
    }

    public function setBankCode(?string $bankCode): void
    {
        $this->bankCode = $bankCode;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getIsActive(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function setIsActive(bool $isActive): void
    {
        $this->is_active = $isActive;
    }
}
