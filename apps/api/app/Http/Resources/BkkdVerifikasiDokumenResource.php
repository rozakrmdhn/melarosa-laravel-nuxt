<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdVerifikasiDokumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_opd' => $this->id_opd,
            'opd' => $this->opd ? [
                'id' => $this->opd->id,
                'kode' => $this->opd->kode,
                'nama' => $this->opd->nama,
            ] : null,
            'nomor_surat_hasil' => $this->nomor_surat_hasil,
            'tanggal_surat_hasil' => $this->tanggal_surat_hasil?->format('Y-m-d'),
            'tanggal_diterima' => $this->tanggal_diterima?->format('Y-m-d'),
            'tahun_anggaran' => $this->tahun_anggaran,
            'tipe_sumber' => $this->tipe_sumber ?? 'file',
            'url_link' => $this->url_link,
            'nama_dokumen_asli' => $this->nama_dokumen_asli,
            'has_dokumen' => !empty($this->path_dokumen) || !empty($this->url_link),
            'catatan_umum' => $this->catatan_umum,
            'total_desa' => $this->verifikasi_count ?? $this->verifikasi()->where('is_aktif', true)->count(),
            'input_by_name' => $this->verifikator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
