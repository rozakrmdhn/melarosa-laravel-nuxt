<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SplitInfrastrukturSegmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'point'             => ['nullable', 'array', 'size:2'],
            'point.0'           => ['required_with:point', 'numeric'],
            'point.1'           => ['required_with:point', 'numeric'],
            'part1'             => ['nullable', 'array'],
            'part1.namobj'      => ['nullable', 'string', 'max:255'],
            'part2'             => ['nullable', 'array'],
            'part2.namobj'      => ['nullable', 'string', 'max:255'],
            'parts'             => ['nullable', 'array', 'size:2'],
            'parts.*.namobj'    => ['required_with:parts', 'string', 'max:255'],
            'parts.*.geometry'  => ['required_with:parts'],
        ];
    }

    public function messages(): array
    {
        return [
            'point.size' => 'Titik koordinat pemotongan harus berupa pasangan [longitude, latitude].',
        ];
    }
}
