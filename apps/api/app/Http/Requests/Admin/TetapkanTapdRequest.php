<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TetapkanTapdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nilai_disetujui_tapd' => ['required', 'numeric', 'min:0'],
            'catatan_tapd' => ['nullable', 'string', 'max:1000'],
            'tanggal_penetapan_tapd' => ['nullable', 'date'],
        ];
    }
}
