<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateDistancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:driving,transit,walking,all'],
            // Optional explicit set of vacancies to compute (the full loaded /
            // filtered list). When omitted, the controller falls back to the
            // list's selected vacancies for backward compatibility.
            'vacancy_ids' => ['sometimes', 'array', 'max:2000'],
            'vacancy_ids.*' => ['integer'],
        ];
    }

    /**
     * @return list<string>
     */
    public function modes(): array
    {
        return $this->input('mode') === 'all'
            ? ['driving', 'transit', 'walking']
            : [$this->input('mode')];
    }
}
