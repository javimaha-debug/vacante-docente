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
