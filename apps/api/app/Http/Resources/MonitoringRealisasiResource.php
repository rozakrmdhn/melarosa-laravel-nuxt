<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringRealisasiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nomor_ba'          => $this->nomor_ba,
            'id_plotting'       => $this->id_plotting,
            'id_kecamatan'      => $this->id_kecamatan,
            'id_desa'           => $this->id_desa,
            'tahun_anggaran'    => $this->tahun_anggaran,
            'sumber_dana'       => $this->sumber_dana,
            'rencana_panjang'   => $this->rencana_panjang,
            'realisasi_panjang' => $this->realisasi_panjang,
            'persentase'        => $this->rencana_panjang > 0
                ? round(($this->realisasi_panjang / $this->rencana_panjang) * 100, 2)
                : 0,
            'status'            => $this->status?->value ?? $this->status,
            'keterangan'        => $this->keterangan,
            'user_id'           => $this->user_id,
            'plotting'          => $this->whenLoaded('plotting'),
            'segmen'            => $this->whenLoaded('segmen'),
            'kecamatan'         => $this->whenLoaded('kecamatan'),
            'desa'              => $this->whenLoaded('desa'),
            'author'            => $this->whenLoaded('author'),
            'items'             => $this->whenLoaded('items'),
            'revisions'         => $this->whenLoaded('revisions'),
            'latest_revision'   => $this->whenLoaded('latestRevision'),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
