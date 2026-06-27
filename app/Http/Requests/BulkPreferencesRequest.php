<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['present', 'array'],
            'preferences.*.vacancy_id' => ['required', 'integer', 'exists:vacancies,id'],
            'preferences.*.position' => ['sometimes', 'integer', 'min:0'],
            'preferences.*.status' => ['required', 'in:selected,discarded,neutral'],
            'preferences.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
