<?php

namespace App\Services;

use App\Models\BkkdProposal;
use App\Models\BkkdProposalRiwayat;
use App\Models\BkkdVerifikasi;
use App\Models\BkkdVerifikasiRiwayat;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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

        // Wajibkan alasan jika bukan draft
        if ($proposal->status !== 'draft' && $aksi === 'updated' && empty(trim((string) $alasan))) {
            throw ValidationException::withMessages([
                'alasan_perubahan' => ['Alasan perubahan wajib diisi untuk usulan yang telah diajukan atau diverifikasi.'],
            ]);
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

    public function catatKoreksiVerifikasi(
        BkkdVerifikasi $verifikasi,
        array $dataBaru,
        string $alasan,
        User $user
    ): BkkdVerifikasi {
        if (empty(trim($alasan))) {
            throw ValidationException::withMessages([
                'alasan_perubahan' => ['Alasan koreksi hasil verifikasi wajib diisi.'],
            ]);
        }

        $dirtyKeys = [];
        $before = [];
        $after = [];

        foreach ($dataBaru as $key => $value) {
            if ($verifikasi->isDirty($key) || (array_key_exists($key, $dataBaru) && $verifikasi->getAttribute($key) != $value)) {
                $dirtyKeys[] = $key;
                $before[$key] = $verifikasi->getOriginal($key);
                $after[$key] = $value;
            }
        }

        if (empty($dirtyKeys)) {
            return $verifikasi;
        }

        $versiBaru = $verifikasi->versi + 1;
        $verifikasi->fill($dataBaru);
        $verifikasi->versi = $versiBaru;
        $verifikasi->updated_by = $user->uuid;
        $verifikasi->save();

        BkkdVerifikasiRiwayat::create([
            'id_verifikasi' => $verifikasi->id,
            'versi' => $versiBaru,
            'aksi' => 'updated',
            'field_berubah' => $dirtyKeys,
            'data_sebelum' => $before,
            'data_sesudah' => $after,
            'alasan_perubahan' => $alasan,
            'changed_by' => $user->uuid,
            'created_at' => now(),
        ]);

        return $verifikasi;
    }

    public function catatAksiVerifikasi(
        BkkdVerifikasi $verifikasi,
        string $aksi,
        ?string $alasan,
        User $user,
        ?array $extraSebelum = null,
        ?array $extraSesudah = null
    ): void {
        BkkdVerifikasiRiwayat::create([
            'id_verifikasi' => $verifikasi->id,
            'versi' => $verifikasi->versi,
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
