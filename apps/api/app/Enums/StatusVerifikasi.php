<?php

namespace App\Enums;

enum StatusVerifikasi: string
{
    case Draft = 'draft';
    case SubmittedDesa = 'submitted_desa';
    case VerifiedKecamatan = 'verified_kecamatan';
    case RejectedKecamatan = 'rejected_kecamatan';
    case VerifiedBappeda = 'verified_bappeda';
    case RejectedBappeda = 'rejected_bappeda';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::SubmittedDesa => 'Menunggu Verifikasi Kecamatan',
            self::VerifiedKecamatan => 'Menunggu Verifikasi Bappeda',
            self::RejectedKecamatan => 'Ditolak Kecamatan',
            self::VerifiedBappeda => 'Disetujui Bappeda (Final)',
            self::RejectedBappeda => 'Ditolak Bappeda',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
