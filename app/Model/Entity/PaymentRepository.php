<?php

namespace App\Model\Entity;

use App\Enums\PaymentModeType;
use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRepository extends Model
{
    use BaseModelTrait, HasUuids, SoftDeletes;

    protected $table = 'payment_repositories';

    protected $fillable = [
        'payment_gateway_id',
        'key',
        'mode',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'payment_gateway_id' => 'string',
            'mode' => PaymentModeType::class,
            'value' => 'array',
        ];
    }

    protected $with = ['payment_gateway'];

    // ------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------
    public function payment_gateway(): HasOne
    {
        return $this->hasOne(PaymentGateway::class, 'id', 'payment_gateway_id');
    }

    // ------------------------------------------------------------
    // Getter & Setter Methods (Explicit style)
    // ------------------------------------------------------------

    public function getPaymentGatewayId(): string
    {
        return $this->payment_gateway_id;
    }

    public function setPaymentGatewayId(string $gatewayId): void
    {
        $this->payment_gateway_id = strtolower($gatewayId);
    }

    public function getMode(): ?PaymentModeType
    {
        return $this->mode instanceof PaymentModeType
            ? $this->mode
            : PaymentModeType::tryFrom($this->mode);
    }

    public function setMode(PaymentModeType|string $mode): void
    {
        $this->mode = $mode instanceof PaymentModeType
            ? $mode->value
            : $mode;
    }

    public function getValue(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        }

        $decoded = is_string($this->value)
            ? json_decode($this->value, true) ?? []
            : [];

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true) ?? [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function setValue(array|string $value): void
    {
        $this->value = is_string($value)
            ? (json_decode($value, true) ?: $value)
            : $value;
    }
}
