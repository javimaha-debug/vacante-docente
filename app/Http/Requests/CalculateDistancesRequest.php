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
            'mode' => ['sometimes', 'in:driving,transit,walking,all'],
            // Subset of travel modes to compute (preferred over `mode`).
            'modes' => ['sometimes', 'array'],
            'modes.*' => ['in:driving,transit,walking'],
            // Optional explicit set of vacancies to compute (the full loaded /
            // filtered list). When omitted, the controller falls back to the
            // list's selected vacancies for backward compatibility.
            'vacancy_ids' => ['sometimes', 'array', 'max:2000'],
            'vacancy_ids.*' => ['integer'],
            // Departure (outbound) and return times, used as traffic-aware
            // departure times for the next-Monday estimate.
            'dep_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'ret_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            // Force recomputation (ignore cache) — used when times change.
            'force' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    public function modes(): array
    {
        $modes = $this->input('modes');
        if (is_array($modes) && count($modes)) {
            return array_values(array_unique($modes));
        }

        $mode = $this->input('mode');

        return match ($mode) {
            'all' => ['driving', 'transit', 'walking'],
            null, '' => ['driving'],
            default => [$mode],
        };
    }
}
