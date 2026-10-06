<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyKecamatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'  => ['required', 'string', 'in:approve,reject'],
            'catatan' => ['nullable', 'string', 'required_if:action,reject'],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan.required_if' => 'Catatan revisi wajib diisi jika permohonan ditolak.',
        ];
    }
}
