<?php

namespace App\Http\Resources;

use App\Enums\StatusBkkdProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_registrasi' => $this->nomor_registrasi,
            'tahun_anggaran' => $this->tahun_anggaran,
            'id_kecamatan' => $this->id_kecamatan,
            'id_desa' => $this->id_desa,
            'nama_kecamatan' => $this->kecamatan?->nama_kecamatan,
            'nama_desa' => $this->desa?->nama_desa,
            'id_bidang' => $this->id_bidang,
            'nama_bidang' => $this->bidang?->nama,
            'id_opd_default' => $this->bidang?->id_opd_default,
            'bidang' => $this->bidang ? [
                'id' => $this->bidang->id,
                'nama' => $this->bidang->nama,
                'id_opd_default' => $this->bidang->id_opd_default,
            ] : null,
            'nomor_surat' => $this->nomor_surat_desa,
            'nomor_surat_desa' => $this->nomor_surat_desa,
            'tanggal_surat_desa' => $this->tanggal_surat_desa?->format('Y-m-d'),
            'tanggal_diterima' => $this->tanggal_diterima?->format('Y-m-d'),
            'judul_kegiatan' => $this->judul_kegiatan,
            'uraian_kegiatan' => $this->uraian_kegiatan,
            'lokasi_kegiatan' => $this->lokasi_kegiatan,
            'panjang' => $this->panjang !== null ? (float) $this->panjang : null,
            'lebar' => $this->lebar !== null ? (float) $this->lebar : null,
            'tinggi' => $this->tinggi !== null ? (float) $this->tinggi : null,
            'volume' => $this->volume !== null ? (float) $this->volume : null,
            'volume_target' => $this->volume !== null ? (float) $this->volume : null,
            'satuan' => $this->satuan,
            'satuan_target' => $this->satuan,
            'nilai_usulan' => $this->nilai_usulan !== null ? (float) $this->nilai_usulan : null,
            'status' => $this->status,
            'status_label' => StatusBkkdProposal::tryFrom($this->status)?->label() ?? $this->status,
            'catatan' => $this->catatan,
            'tahap_anggaran' => $this->tahap_anggaran ?? 'induk',
            'id_proposal_induk' => $this->id_proposal_induk,
            'alasan_pergantian_papbd' => $this->alasan_pergantian_papbd,
            'nilai_disetujui_tapd' => $this->nilai_disetujui_tapd !== null ? (float) $this->nilai_disetujui_tapd : null,
            'catatan_tapd' => $this->catatan_tapd,
            'tanggal_penetapan_tapd' => $this->tanggal_penetapan_tapd?->format('Y-m-d'),
            'versi' => $this->versi,
            'verifikasi_aktif' => $this->relationLoaded('verifikasiAktif') && $this->verifikasiAktif
                ? new BkkdVerifikasiResource($this->verifikasiAktif)
                : null,
            'creator_name' => $this->creator?->name,
            'updater_name' => $this->updater?->name,
            'penetap_name' => $this->penetap?->name,
            'total_lampiran' => (int) ($this->total_lampiran ?? ($this->relationLoaded('lampiran') ? $this->lampiran->count() : 0)),
            'total_lampiran_file' => (int) ($this->total_lampiran_file ?? ($this->relationLoaded('lampiran') ? $this->lampiran->where('tipe_sumber', 'file')->whereNull('url_link')->count() : 0)),
            'total_lampiran_link' => (int) ($this->total_lampiran_link ?? ($this->relationLoaded('lampiran') ? $this->lampiran->filter(fn ($l) => $l->tipe_sumber === 'link' || !empty($l->url_link))->count() : 0)),
            'has_lampiran' => $this->relationLoaded('lampiran')
                ? $this->lampiran->isNotEmpty()
                : (isset($this->total_lampiran) ? (int) $this->total_lampiran > 0 : false),
            'cloud_links' => $this->relationLoaded('lampiran')
                ? $this->lampiran->filter(fn ($l) => $l->tipe_sumber === 'link' || !empty($l->url_link))->map(fn ($l) => [
                    'id' => $l->id,
                    'nama' => $l->nama_asli,
                    'url' => $l->url_link,
                ])->values()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'is_deleted' => $this->trashed(),
        ];
    }
}
