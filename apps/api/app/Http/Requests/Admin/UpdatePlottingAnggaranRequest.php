<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlottingAnggaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_anggaran'       => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'id_kecamatan'         => ['sometimes', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa'              => ['sometimes', 'integer', 'exists:bataswilayah_desa,id'],
            'jenis_bantuan'        => ['sometimes', 'string', 'max:255'],
            'nama_kegiatan'        => ['sometimes', 'string', 'max:255'],
            'lokasi_kegiatan'      => ['nullable', 'string'],
            'sumber_dana'          => ['sometimes', 'string', 'max:255'],
            'target_pagu_anggaran' => ['sometimes', 'numeric', 'min:0'],
            'target_panjang_m'     => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
