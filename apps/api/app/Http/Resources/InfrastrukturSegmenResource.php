<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfrastrukturSegmenResource extends JsonResource
{
    /**
     * Transform the resource into an array (GeoJSON Feature).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type'       => 'Feature',
            'id'         => $this->id,
            'geometry'   => !empty($this->geojson) ? json_decode($this->geojson) : null,
            'properties' => [
                'id'                 => $this->id,
                'tipe_kode'          => $this->tipe_kode,
                'namobj'             => $this->namobj,
                'panjang'            => $this->panjang,
                'panjang_meter_gis'  => $this->panjang_meter_gis,
                'lebar'              => $this->lebar,
                'jenis_perkerasan'   => $this->jenis_perkerasan,
                'status_jalan'       => $this->status_jalan,
                'kondisi'            => $this->kondisi?->value ?? $this->kondisi,
                'status_kondisi'     => $this->status_kondisi,
                'tahun_pembangunan'  => $this->tahun_pembangunan,
                'sumber_dana'        => $this->sumber_dana,
                'status_verifikasi'  => $this->status_verifikasi?->value ?? $this->status_verifikasi,
                'status_aset'        => $this->status_aset,
                'desa'               => $this->desa,
                'kecamatan'          => $this->kecamatan,
                'id_desa'            => $this->id_desa,
                'id_kecamatan'       => $this->id_kecamatan,
                'sumber_data'        => $this->sumber_data,
                'keterangan'         => $this->keterangan,
                'foto_url'           => $this->foto_url,
                'atribut'            => $this->atribut,
                'centroid'           => !empty($this->centroid) ? json_decode($this->centroid) : null,
                'catatan_kecamatan'  => $this->catatan_kecamatan,
                'catatan_bappeda'    => $this->catatan_bappeda,
                'submitted_desa_at'  => $this->submitted_desa_at,
                'verified_kecamatan_at' => $this->verified_kecamatan_at,
                'verified_bappeda_at'   => $this->verified_bappeda_at,
                'tipe'               => $this->whenLoaded('tipe'),
                'plotting'           => $this->whenLoaded('plotting'),
                'creator'            => $this->whenLoaded('creator'),
                'verifier_kecamatan' => $this->whenLoaded('verifierKecamatan'),
                'verifier_bappeda'   => $this->whenLoaded('verifierBappeda'),
                'created_at'         => $this->created_at,
                'updated_at'         => $this->updated_at,
            ],
        ];
    }
}
