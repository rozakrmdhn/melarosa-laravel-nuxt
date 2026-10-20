<?php

namespace App\Http\Resources;

use App\Enums\HasilVerifikasiBkkd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdVerifikasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_proposal' => $this->id_proposal,
            'id_opd' => $this->id_opd,
            'kode_opd' => $this->opd?->kode,
            'nama_opd' => $this->opd?->nama,
            'opd' => $this->opd ? [
                'id' => $this->opd->id,
                'kode' => $this->opd->kode,
                'nama' => $this->opd->nama,
            ] : null,
            'nomor_surat_hasil' => $this->nomor_surat_hasil,
            'tanggal_surat_hasil' => $this->tanggal_surat_hasil?->format('Y-m-d'),
            'tanggal_verifikasi' => $this->tanggal_surat_hasil?->format('Y-m-d') ?? $this->tanggal_diterima?->format('Y-m-d'),
            'tanggal_diterima' => $this->tanggal_diterima?->format('Y-m-d'),
            'hasil' => $this->hasil,
            'hasil_label' => HasilVerifikasiBkkd::tryFrom($this->hasil)?->label() ?? $this->hasil,
            'nilai_rekomendasi' => $this->nilai_rekomendasi !== null ? (float) $this->nilai_rekomendasi : null,
            'rekomendasi_anggaran' => $this->nilai_rekomendasi !== null ? (float) $this->nilai_rekomendasi : null,
            'volume_rekomendasi' => $this->volume_rekomendasi !== null ? (float) $this->volume_rekomendasi : null,
            'panjang_rekomendasi' => $this->panjang_rekomendasi !== null ? (float) $this->panjang_rekomendasi : null,
            'lebar_rekomendasi' => $this->lebar_rekomendasi !== null ? (float) $this->lebar_rekomendasi : null,
            'tinggi_rekomendasi' => $this->tinggi_rekomendasi !== null ? (float) $this->tinggi_rekomendasi : null,
            'catatan_opd' => $this->catatan_opd,
            'nama_dokumen_asli' => $this->nama_dokumen_asli,
            'has_dokumen' => !empty($this->path_dokumen),
            'is_aktif' => (bool) $this->is_aktif,
            'versi' => $this->versi,
            'is_proposal_deleted' => (bool) $this->proposal?->trashed(),
            'proposal' => $this->proposal ? [
                'id' => $this->proposal->id,
                'nomor_registrasi' => $this->proposal->nomor_registrasi,
                'judul_kegiatan' => $this->proposal->judul_kegiatan,
                'status' => $this->proposal->status,
                'id_kecamatan' => $this->proposal->id_kecamatan,
                'id_desa' => $this->proposal->id_desa,
                'nama_kecamatan' => $this->proposal->kecamatan?->nama_kecamatan,
                'nama_desa' => $this->proposal->desa?->nama_desa,
                'id_bidang' => $this->proposal->id_bidang,
                'nama_bidang' => $this->proposal->bidang?->nama,
                'bidang' => $this->proposal->bidang ? [
                    'id' => $this->proposal->bidang->id,
                    'nama' => $this->proposal->bidang->nama,
                ] : null,
                'nilai_usulan' => $this->proposal->nilai_usulan !== null ? (float) $this->proposal->nilai_usulan : null,
                'panjang' => $this->proposal->panjang !== null ? (float) $this->proposal->panjang : null,
                'lebar' => $this->proposal->lebar !== null ? (float) $this->proposal->lebar : null,
                'tinggi' => $this->proposal->tinggi !== null ? (float) $this->proposal->tinggi : null,
                'volume' => $this->proposal->volume !== null ? (float) $this->proposal->volume : null,
                'volume_target' => $this->proposal->volume !== null ? (float) $this->proposal->volume : null,
                'satuan' => $this->proposal->satuan,
                'satuan_target' => $this->proposal->satuan,
                'lokasi_kegiatan' => $this->proposal->lokasi_kegiatan,
                'deleted_at' => $this->proposal->deleted_at?->toIso8601String(),
                'is_deleted' => $this->proposal->trashed(),
            ] : null,
            'input_by_name' => $this->verifikator?->name,
            'updated_by_name' => $this->updater?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
