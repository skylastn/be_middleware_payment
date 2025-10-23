<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'type',
        'from',
        'bankCode',
        'value',
    ];

    // ⚠️ Tidak diubah — tetap seperti kode aslimu
    protected $with = ['category'];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function category()
    {
        return $this->hasOne(PaymentCategory::class, 'key', 'key');
    }

    // ------------------------------------------------------------
    // Getter & Setter Methods
    // ------------------------------------------------------------

    public function getKeyAttribute(): string
    {
        return $this->attributes['key'];
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
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
