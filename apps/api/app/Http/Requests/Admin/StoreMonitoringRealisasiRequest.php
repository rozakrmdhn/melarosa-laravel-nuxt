<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonitoringRealisasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_ba'          => ['required', 'string', 'max:255'],
            'id_plotting'       => ['required', 'uuid', 'exists:plotting_anggaran,id'],
            'id_kecamatan'      => ['required', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa'           => ['required', 'integer', 'exists:bataswilayah_desa,id'],
            'tahun_anggaran'    => ['required', 'integer', 'min:2000', 'max:2100'],
            'sumber_dana'       => ['required', 'string', 'max:255'],
            'rencana_panjang'   => ['required', 'numeric', 'min:0'],
            'realisasi_panjang' => ['required', 'numeric', 'min:0'],
            'status'            => ['nullable', 'string', 'in:draft,submitted,approved,rejected,reverted'],
            'keterangan'        => ['nullable', 'string'],
            'segmen_ids'        => ['nullable', 'array'],
            'segmen_ids.*'      => ['uuid', 'exists:infrastruktur_segmen,id'],
        ];
    }
}
