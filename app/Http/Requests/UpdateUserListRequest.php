<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'home_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'home_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'home_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
