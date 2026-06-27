<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'min:8', 'max:64'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
        ];
    }
}
