<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInfrastrukturSegmenGeometryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'geometry' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'geometry.required' => 'Data koordinat geometri wajib disertakan.',
        ];
    }
}
