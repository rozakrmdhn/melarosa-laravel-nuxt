<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlottingAnggaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_anggaran'       => ['required', 'integer', 'min:2000', 'max:2100'],
            'id_kecamatan'         => ['required', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa'              => ['required', 'integer', 'exists:bataswilayah_desa,id'],
            'jenis_bantuan'        => ['required', 'string', 'max:255'],
            'nama_kegiatan'        => ['required', 'string', 'max:255'],
            'lokasi_kegiatan'      => ['nullable', 'string'],
            'sumber_dana'          => ['required', 'string', 'max:255'],
            'target_pagu_anggaran' => ['required', 'numeric', 'min:0'],
            'target_panjang_m'     => ['required', 'numeric', 'min:0'],
        ];
    }
}
