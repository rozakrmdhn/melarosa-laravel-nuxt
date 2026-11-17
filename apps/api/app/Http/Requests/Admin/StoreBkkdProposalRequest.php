<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBkkdProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_anggaran' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tagging_usulan' => ['nullable', 'string', 'in:RKPD,PRKPD'],
            'id_kecamatan' => ['required', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa' => ['required', 'numeric', 'exists:bataswilayah_desa,id'],
            'id_bidang' => ['required', 'uuid', 'exists:ref_bkkd_bidang,id'],
            'judul_kegiatan' => ['required', 'string', 'max:255'],
            'uraian_kegiatan' => ['nullable', 'string'],
            'lokasi_kegiatan' => ['nullable', 'string'],
            'nilai_usulan' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude_akhir' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude_akhir' => ['nullable', 'numeric', 'between:-180,180'],
            'id_jalan_poros' => ['nullable', 'string', 'max:36'],
            'geojson' => ['nullable'],
            'lokasi' => ['nullable', 'array'],
            'lokasi.*.nama_lokasi' => ['required_with:lokasi', 'string', 'max:255'],
            'lokasi.*.urutan' => ['nullable', 'integer', 'min:1'],
            'lokasi.*.panjang_rencana_m' => ['nullable', 'numeric', 'min:0'],
            'lokasi.*.lebar_m' => ['nullable', 'numeric', 'min:0'],
            'lokasi.*.id_jalan_poros' => ['nullable', 'string', 'max:36'],
            'lokasi.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'lokasi.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'lokasi.*.latitude_akhir' => ['nullable', 'numeric', 'between:-90,90'],
            'lokasi.*.longitude_akhir' => ['nullable', 'numeric', 'between:-180,180'],
            'lokasi.*.sumber_rute' => ['nullable', 'string', 'max:50'],
            'lokasi.*.waypoints' => ['nullable', 'array'],
            'lokasi.*.titik_sebaran' => ['nullable', 'array'],
            'lokasi.*.geojson' => ['nullable'],
        ];
    }
}
