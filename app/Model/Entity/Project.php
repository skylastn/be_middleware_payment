<?php

namespace App\Model\Entity;

use App\Enums\ProjectSlug;
use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory, BaseModelTrait;

    protected $fillable = ['id', "name", 'type', 'key', "secure", "callback", "value", 'slug'];
    protected $casts = [
        'slug' => ProjectSlug::class
    ];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getSecure(): string
    {
        return $this->secure;
    }

    public function setSecure(string $secure): void
    {
        $this->secure = $secure;
    }

    public function getCallback(): ?string
    {
        return $this->callback;
    }

    public function setCallback(?string $callback): void
    {
        $this->callback = $callback;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getSlug(): ProjectSlug
    {
        return $this->slug;
    }

    public function setSlug(ProjectSlug $slug): void
    {
        $this->slug = $slug;
    }
}
