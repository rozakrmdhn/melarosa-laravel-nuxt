<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdProposalDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_registrasi' => $this->nomor_registrasi,
            'tahun_anggaran' => $this->tahun_anggaran,
            'tagging_usulan' => $this->tagging_usulan ?? 'PRKPD',
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
            'judul_kegiatan' => $this->judul_kegiatan,
            'uraian_kegiatan' => $this->uraian_kegiatan,
            'lokasi_kegiatan' => $this->lokasi_kegiatan,
            'nilai_usulan' => $this->nilai_usulan !== null ? (float) $this->nilai_usulan : null,
            'jumlah_lokasi' => (int) ($this->jumlah_lokasi ?? 0),
            'total_panjang_gis' => (float) ($this->total_panjang_gis ?? 0),
            'status_analisa_spasial' => $this->status_analisa_spasial ?? 'belum_dianalisa',
            'catatan_konfirmasi_desa' => $this->catatan_konfirmasi_desa,
            'dikonfirmasi_oleh' => $this->dikonfirmasi_oleh,
            'dikonfirmasi_pada' => $this->dikonfirmasi_pada?->toIso8601String(),
            'lokasi' => $this->relationLoaded('lokasi') ? BkkdProposalLokasiResource::collection($this->lokasi) : [],
            'versi' => $this->versi,
            'creator_name' => $this->creator?->name,
            'updater_name' => $this->updater?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'is_deleted' => $this->trashed(),
        ];
    }
}
