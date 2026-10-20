<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdRiwayatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_proposal' => $this->id_proposal ?? $this->verifikasi?->id_proposal,
            'id_verifikasi' => $this->id_verifikasi ?? null,
            'versi' => $this->versi,
            'aksi' => $this->aksi,
            'field_berubah' => $this->field_berubah,
            'data_sebelum' => $this->data_sebelum,
            'data_sesudah' => $this->data_sesudah,
            'alasan_perubahan' => $this->alasan_perubahan,
            'changed_by' => $this->changed_by,
            'changed_by_name' => $this->actor?->name,
            'proposal_nomor' => $this->proposal?->nomor_registrasi ?? $this->verifikasi?->proposal?->nomor_registrasi,
            'proposal_judul' => $this->proposal?->judul_kegiatan ?? $this->verifikasi?->proposal?->judul_kegiatan,
            'nama_desa' => $this->proposal?->desa?->nama_desa ?? $this->verifikasi?->proposal?->desa?->nama_desa,
            'nama_kecamatan' => $this->proposal?->kecamatan?->nama_kecamatan ?? $this->verifikasi?->proposal?->kecamatan?->nama_kecamatan,
            'opd_nama' => $this->verifikasi?->opd?->nama ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
