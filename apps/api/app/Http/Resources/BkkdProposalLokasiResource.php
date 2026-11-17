<?php

namespace App\Http\Resources;

use App\Models\BkkdProposalLokasi;
use App\Models\JalanPorosDesa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BkkdProposalLokasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $geojson = $this->geojson;
        if (is_string($geojson)) {
            $decoded = json_decode($geojson, true);
            if (is_array($decoded)) {
                $geojson = $decoded;
            }
        }

        $centroid = $this->centroid;
        if (is_string($centroid)) {
            $decodedCentroid = json_decode($centroid, true);
            if (is_array($decodedCentroid)) {
                $centroid = $decodedCentroid;
            }
        }

        return [
            'id' => $this->id,
            'id_proposal' => $this->id_proposal,
            'id_jalan_poros' => $this->id_jalan_poros,
            'id_jaringan_ruas' => $this->id_jaringan_ruas,
            'urutan' => (int) $this->urutan,
            'nama_lokasi' => $this->nama_lokasi,
            'nama_ruas_usulan' => $this->nama_ruas_usulan,
            'hierarki_jalan_usulan' => $this->hierarki_jalan_usulan,
            'panjang_rencana_m' => $this->panjang_rencana_m !== null ? (float) $this->panjang_rencana_m : null,
            'panjang_gis_m' => (float) $this->panjang_gis_m,
            'lebar_m' => $this->lebar_m !== null ? (float) $this->lebar_m : null,
            'status_analisa_spasial' => $this->status_analisa_spasial ?? 'belum_dianalisa',
            'status_promosi_master' => $this->status_promosi_master ?? 'belum_dievaluasi',
            'ringkasan_analisa' => $this->ringkasan_analisa,
            'validasi_promosi_spasial' => $this->validasi_promosi_spasial,
            'catatan_promosi_master' => $this->catatan_promosi_master,
            'diajukan_promosi_pada' => $this->diajukan_promosi_pada?->toIso8601String(),
            'divalidasi_promosi_pada' => $this->divalidasi_promosi_pada?->toIso8601String(),
            'geojson' => $geojson,
            'centroid' => $centroid,
            'jalan_poros' => $this->whenLoaded('jalanPoros', fn () => $this->jalanPoros ? [
                'id' => $this->jalanPoros->id,
                'kode_ruas' => $this->jalanPoros->kode_ruas,
                'nama_ruas' => $this->jalanPoros->nama_ruas,
            ] : null),
            'jaringan_ruas' => $this->when(
                $this->relationLoaded('jaringanRuas')
                    || ($this->status_promosi_master === BkkdProposalLokasi::PROMOSI_DISETUJUI && $this->relationLoaded('jalanPoros')),
                function () {
                    $masterRuas = $this->relationLoaded('jaringanRuas') ? $this->jaringanRuas : null;
                    if (!$masterRuas
                        && $this->status_promosi_master === BkkdProposalLokasi::PROMOSI_DISETUJUI
                        && $this->relationLoaded('jalanPoros')) {
                        $masterRuas = $this->jalanPoros;
                    }
                    if (!$masterRuas) {
                        return null;
                    }

                    return [
                        'id' => $masterRuas->id,
                        'kode_ruas' => $masterRuas->kode_ruas !== null ? (string) $masterRuas->kode_ruas : null,
                        'nama_ruas' => $masterRuas->nama_ruas,
                        'hierarki_jalan' => $masterRuas->hierarki_jalan ?? JalanPorosDesa::HIERARKI_POROS_DESA,
                        'status_validasi' => 'terverifikasi',
                        'sumber_data' => $masterRuas->sumber_data,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

