<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBkkdVerifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_dokumen' => ['nullable', 'uuid', 'exists:bkkd_verifikasi_dokumen,id'],
            'id_opd' => ['nullable', 'uuid', 'exists:ref_opd,id'],
            'nomor_surat_hasil' => ['required_without:id_dokumen', 'nullable', 'string', 'max:150'],
            'tanggal_surat_hasil' => ['nullable', 'date'],
            'tanggal_verifikasi' => ['nullable', 'date'],
            'tanggal_diterima' => ['nullable', 'date'],
            'hasil' => ['required', 'string', 'in:layak,layak_bersyarat,tidak_layak,dikembalikan'],
            'nilai_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'rekomendasi_anggaran' => ['nullable', 'numeric', 'min:0'],
            'volume_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'panjang_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'lebar_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'tinggi_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'tipe_sumber' => ['nullable', 'string', 'in:file,link'],
            'url_link' => ['nullable', 'required_if:tipe_sumber,link', 'url', 'max:1000'],
            'path_dokumen' => ['nullable', 'string'],
            'nama_dokumen_asli' => ['nullable', 'string'],
        ];
    }
}
