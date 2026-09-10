<?php

namespace App\Model\Entity;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, BaseModelTrait;

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
        'payment_repository_id' => 'string',
        'mode' => PaymentModeType::class,
        'status' => OrderStatus::class,
        'amount' => 'float',
    ];

    protected $fillable = [
        'id',
        'payment_repository_id',
        'mode',
        'type',
        'reference',
        'name',
        'payment_method',
        'amount',
        'value',
        'status',
        'request',
        'response',
        'callback',
        'url',
        'return_url',
        'notes',
        'address',
        'phone',
        'email',
    ];

    // Keep the original eager-loaded relationships.
    protected $with = ['payment_methods', 'project', 'payment_repository'];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function payment_methods(): HasOne
    {
        return $this->hasOne(PaymentMethod::class, 'key', 'payment_method');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'type', 'type');
    }

    public function payment_repository(): HasOne
    {
        return $this->hasOne(PaymentRepository::class, 'id', 'payment_repository_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id')->orderBy('created_at', 'asc');
    }

    // ------------------------------------------------------------
    // Getter & Setter Methods (Explicit style)
    // ------------------------------------------------------------

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getPaymentRepositoryId(): string
    {
        return $this->payment_repository_id;
    }

    public function setPaymentRepositoryId(?string $value): void
    {
        $this->payment_repository_id = $value;
    }

    public function getMode(): ?PaymentModeType
    {
        return $this->mode instanceof PaymentModeType
            ? $this->mode
            : PaymentModeType::fromName($this->mode);
    }

    public function setMode(string|PaymentModeType|null $mode): void
    {
        $this->mode = PaymentModeType::fromName($mode)?->value;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * Extract the original merchantOrderId from the reference.
     * Reference format is always "{projectType}-{merchantOrderId}" where merchantOrderId may contain dashes.
     * E.g. reference "AD-FM-0000071-USXMMR" => merchantOrderId "FM-0000071-USXMMR"
     * Always drops the first dash-segment (the project type prefix) from the stored reference.
     */
    public function getMerchantOrderId(): string
    {
        $ref = (string) $this->reference;
        if ($ref === '') {
            return '';
        }

        $parts = explode('-', $ref);
        if (count($parts) > 1) {
            array_shift($parts);
            return implode('-', $parts);
        }

        return $ref;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->payment_method;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->payment_method = $paymentMethod;
    }

    public function getAmount(): float
    {
        return (float) ($this->amount ?? 0);
    }

    public function setAmount(float|int|string|null $amount): void
    {
        $this->amount = (float) ($amount ?? 0);
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getStatus(): ?OrderStatus
    {
        return $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::fromName($this->status);
    }

    public function setStatus(string|OrderStatus|null $status): void
    {
        $this->status = OrderStatus::fromName($status)?->value;
    }

    public function getRequest(): mixed
    {
        return $this->request;
    }

    public function setRequest(mixed $request): void
    {
        $this->request = $request;
    }

    public function getResponse(): mixed
    {
        return $this->response;
    }

    public function setResponse(mixed $response): void
    {
        $this->response = $response;
    }

    public function getCallback(): ?string
    {
        return $this->callback;
    }

    public function setCallback(?string $callback): void
    {
        $this->callback = $callback;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getReturnUrl(): ?string
    {
        return $this->return_url;
    }

    public function setReturnUrl(?string $returnUrl): void
    {
        $this->return_url = $returnUrl;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
}
