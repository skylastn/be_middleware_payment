<?php

namespace App\Model\Request\Project;

use App\Enums\ProjectSlug;
use App\Model\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'slug' => ['sometimes', 'required', 'string', Rule::in(array_map(fn (ProjectSlug $s) => $s->value, ProjectSlug::cases()))],
            'callback' => ['sometimes', 'required', 'string'],
        ];
    }
}
