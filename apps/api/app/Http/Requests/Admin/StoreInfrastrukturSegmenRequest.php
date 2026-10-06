<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfrastrukturSegmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe_kode'          => ['required', 'string', 'exists:infrastruktur_tipe,kode'],
            'geometry'           => ['required'],
            'namobj'             => ['nullable', 'string', 'max:255'],
            'panjang'            => ['nullable', 'numeric', 'min:0'],
            'lebar'              => ['nullable', 'numeric', 'min:0'],
            'jenis_perkerasan'   => ['nullable', 'string', 'max:100'],
            'status_jalan'       => ['nullable', 'string', 'max:100'],
            'kondisi'            => ['nullable', 'string', 'in:Baik,Sedang,Rusak Ringan,Rusak Berat'],
            'status_kondisi'     => ['nullable', 'string', 'in:Eksisting,Riwayat'],
            'tahun_pembangunan'  => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sumber_dana'        => ['nullable', 'string', 'max:255'],
            'keterangan'         => ['nullable', 'string'],
            'foto_url'           => ['nullable', 'string', 'max:255'],
            'atribut'            => ['nullable', 'array'],
            'id_desa'            => ['required', 'integer', 'exists:bataswilayah_desa,id'],
            'id_kecamatan'       => ['required', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'plotting_id'        => ['nullable', 'uuid', 'exists:plotting_anggaran,id'],
            'parent_id'          => ['nullable', 'uuid', 'exists:infrastruktur_segmen,id'],
            'status_aset'        => ['nullable', 'string', 'max:100'],
            'sumber_data'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
