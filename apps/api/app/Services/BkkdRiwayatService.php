<?php

namespace App\Services;

use App\Models\BkkdProposal;
use App\Models\BkkdProposalRiwayat;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BkkdRiwayatService
{
    public function catatPerubahanProposal(
        BkkdProposal $proposal,
        array $dataBaru,
        ?int $versiKlien,
        ?string $alasan,
        User $user,
        string $aksi = 'updated'
    ): BkkdProposal {
        // Validasi optimistic locking
        if ($versiKlien !== null && $versiKlien !== $proposal->versi) {
            throw new ConflictHttpException(
                'Data proposal ini telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk melihat versi terbaru.'
            );
        }

        $dirtyKeys = [];
        $before = [];
        $after = [];

        foreach ($dataBaru as $key => $value) {
            if ($proposal->isDirty($key) || (array_key_exists($key, $dataBaru) && $proposal->getAttribute($key) != $value)) {
                $dirtyKeys[] = $key;
                $before[$key] = $proposal->getOriginal($key);
                $after[$key] = $value;
            }
        }

        // Jika tidak ada data yang berubah
        if (empty($dirtyKeys)) {
            return $proposal;
        }

        $versiBaru = $proposal->versi + 1;
        $proposal->fill($dataBaru);
        $proposal->versi = $versiBaru;
        $proposal->updated_by = $user->uuid;
        $proposal->save();

        BkkdProposalRiwayat::create([
            'id_proposal' => $proposal->id,
            'versi' => $versiBaru,
            'aksi' => $aksi,
            'field_berubah' => $dirtyKeys,
            'data_sebelum' => $before,
            'data_sesudah' => $after,
            'alasan_perubahan' => $alasan,
            'changed_by' => $user->uuid,
            'created_at' => now(),
        ]);

        return $proposal;
    }

    public function catatAksiProposal(
        BkkdProposal $proposal,
        string $aksi,
        ?string $alasan,
        User $user,
        ?array $extraSebelum = null,
        ?array $extraSesudah = null
    ): void {
        BkkdProposalRiwayat::create([
            'id_proposal' => $proposal->id,
            'versi' => $proposal->versi,
            'aksi' => $aksi,
            'field_berubah' => $extraSesudah ? array_keys($extraSesudah) : [],
            'data_sebelum' => $extraSebelum,
            'data_sesudah' => $extraSesudah,
            'alasan_perubahan' => $alasan,
            'changed_by' => $user->uuid,
            'created_at' => now(),
        ]);
    }
}
