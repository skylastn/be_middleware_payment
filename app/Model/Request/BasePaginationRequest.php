<?php

namespace App\Model\Request;

use Illuminate\Foundation\Http\FormRequest;

class BasePaginationRequest extends FormRequest
{
    public ?int $perPage = null;
    public ?int $page = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|nullable|integer|min:1',
            'perPage' => 'sometimes|nullable|integer|min:1|max:100',
        ];
    }
}
