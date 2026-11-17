<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBkkdProposalLokasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_lokasi' => ['required', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'panjang_rencana_m' => ['nullable', 'numeric', 'min:0'],
            'lebar_m' => ['nullable', 'numeric', 'min:0'],
            'id_jalan_poros' => ['nullable', 'string', 'max:36'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude_akhir' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude_akhir' => ['nullable', 'numeric', 'between:-180,180'],
            'sumber_rute' => ['nullable', 'string', 'max:50'],
            'waypoints' => ['nullable', 'array'],
            'titik_sebaran' => ['nullable', 'array'],
            'geojson' => ['required_without_all:latitude,longitude,waypoints'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lokasi.required' => 'Nama lokasi kegiatan wajib diisi.',
            'geojson.required' => 'Geometri LineString lokasi kegiatan wajib disediakan.',
        ];
    }
}

