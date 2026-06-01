<?php

namespace App\Model\Entity;

use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, BaseModelTrait;

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
        'payment_repository_id' => 'string',
    ];

    protected $fillable = [
        'id',
        'mode',
        'type',
        'reference',
        'payment_method',
        'request',
        'response',
        'callback',
        'url',
        'notes',
        'address',
        'phone',
        'email',
    ];

    // Keep the original eager-loaded relationships.
    protected $with = ['payment_methods', 'project'];

    // ------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------

    public function payment_methods()
    {
        return $this->hasOne(PaymentMethod::class, 'value', 'payment_method');
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'type', 'type');
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

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
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

    public function getPaymentMethod(): ?string
    {
        return $this->payment_method;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->payment_method = $paymentMethod;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
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
