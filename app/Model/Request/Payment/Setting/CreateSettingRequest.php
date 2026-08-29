<?php

namespace App\Model\Request\Payment\Setting;

use App\Model\Request\BaseRequest;

class CreateSettingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string'],
        ];
    }
}
