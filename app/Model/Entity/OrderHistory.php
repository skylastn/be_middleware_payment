<?php

namespace App\Model\Entity;

use App\Enums\OrderStatus;
use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHistory extends Model
{
    use HasFactory, BaseModelTrait, HasUuids;

    protected $table = 'order_histories';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'reference',
        'from_status',
        'to_status',
        'source',
        'description',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'from_status' => OrderStatus::class,
        'to_status' => OrderStatus::class,
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // ------------------------------------------------------------
    // Getters & Setters
    // ------------------------------------------------------------

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->order_id;
    }

    public function setOrderId(string $orderId): void
    {
        $this->order_id = $orderId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getFromStatus(): ?OrderStatus
    {
        return $this->from_status instanceof OrderStatus
            ? $this->from_status
            : OrderStatus::fromName($this->from_status);
    }

    public function setFromStatus(string|OrderStatus|null $status): void
    {
        $this->from_status = OrderStatus::fromName($status)?->value;
    }

    public function getToStatus(): ?OrderStatus
    {
        return $this->to_status instanceof OrderStatus
            ? $this->to_status
            : OrderStatus::fromName($this->to_status);
    }

    public function setToStatus(string|OrderStatus|null $status): void
    {
        $this->to_status = OrderStatus::fromName($status)?->value ?? OrderStatus::PENDING->value;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPayload(): ?array
    {
        return is_array($this->payload) ? $this->payload : json_decode((string) $this->payload, true);
    }

    public function setPayload(mixed $payload): void
    {
        $this->payload = is_string($payload) ? (json_decode($payload, true) ?: ['raw' => $payload]) : $payload;
    }
}
