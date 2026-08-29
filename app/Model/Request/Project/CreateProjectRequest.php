<?php

namespace App\Model\Request\Project;

use App\Enums\ProjectSlug;
use App\Model\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateProjectRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', Rule::in(array_map(fn (ProjectSlug $s) => $s->value, ProjectSlug::cases()))],
            'callback' => ['required', 'string'],
        ];
    }
}
