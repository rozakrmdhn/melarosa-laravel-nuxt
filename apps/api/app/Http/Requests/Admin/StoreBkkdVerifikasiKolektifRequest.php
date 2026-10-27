<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBkkdVerifikasiKolektifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_opd' => ['required', 'uuid', 'exists:ref_opd,id'],
            'nomor_surat_hasil' => ['required', 'string', 'max:150'],
            'tanggal_surat_hasil' => ['required', 'date'],
            'tanggal_diterima' => ['nullable', 'date'],
            'tahun_anggaran' => ['nullable', 'integer'],
            'catatan_umum' => ['nullable', 'string'],
            'tipe_sumber' => ['nullable', 'string', 'in:file,link'],
            'url_link' => ['nullable', 'required_if:tipe_sumber,link', 'url', 'max:1000'],
            'path_dokumen' => ['nullable', 'required_if:tipe_sumber,file', 'string'],
            'nama_dokumen_asli' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_proposal' => ['required', 'uuid', 'exists:bkkd_proposal,id'],
            'items.*.hasil' => ['required', 'string', 'in:layak,layak_bersyarat,tidak_layak,dikembalikan'],
            'items.*.nilai_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'items.*.panjang_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'items.*.lebar_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'items.*.tinggi_rekomendasi' => ['nullable', 'numeric', 'min:0'],
            'items.*.catatan_opd' => ['nullable', 'string'],
        ];
    }
}
