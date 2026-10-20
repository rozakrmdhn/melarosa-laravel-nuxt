<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBkkdProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'versi' => ['required', 'integer'],
            'alasan_perubahan' => ['nullable', 'string', 'max:1000'],
            'tahun_anggaran' => ['required', 'integer', 'min:2020', 'max:2100'],
            'id_kecamatan' => ['required', 'integer', 'exists:bataswilayah_kecamatan,id'],
            'id_desa' => ['required', 'numeric', 'exists:bataswilayah_desa,id'],
            'id_bidang' => ['required', 'uuid', 'exists:ref_bkkd_bidang,id'],
            'nomor_surat_desa' => ['required', 'string', 'max:150'],
            'tanggal_surat_desa' => ['required', 'date'],
            'tanggal_diterima' => ['nullable', 'date'],
            'judul_kegiatan' => ['required', 'string', 'max:255'],
            'uraian_kegiatan' => ['nullable', 'string'],
            'lokasi_kegiatan' => ['nullable', 'string'],
            'panjang' => ['nullable', 'numeric', 'min:0'],
            'lebar' => ['nullable', 'numeric', 'min:0'],
            'tinggi' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'volume_target' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'satuan_target' => ['nullable', 'string', 'max:30'],
            'nilai_usulan' => ['nullable', 'numeric', 'min:0'],
            'tahap_anggaran' => ['nullable', 'string', 'in:induk,perubahan'],
            'id_proposal_induk' => ['nullable', 'uuid', 'exists:bkkd_proposal,id'],
            'alasan_pergantian_papbd' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
