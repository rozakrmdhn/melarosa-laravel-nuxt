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
            'id_proposal' => $this->id_proposal,
            'versi' => $this->versi,
            'aksi' => $this->aksi,
            'field_berubah' => $this->field_berubah,
            'data_sebelum' => $this->data_sebelum,
            'data_sesudah' => $this->data_sesudah,
            'alasan_perubahan' => $this->alasan_perubahan,
            'changed_by' => $this->changed_by,
            'changed_by_name' => $this->actor?->name,
            'proposal_nomor' => $this->proposal?->nomor_registrasi,
            'proposal_judul' => $this->proposal?->judul_kegiatan,
            'nama_desa' => $this->proposal?->desa?->nama_desa,
            'nama_kecamatan' => $this->proposal?->kecamatan?->nama_kecamatan,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
