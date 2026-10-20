<?php

namespace App\Enums;

enum HasilVerifikasiBkkd: string
{
    case Layak = 'layak';
    case LayakBersyarat = 'layak_bersyarat';
    case TidakLayak = 'tidak_layak';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::Layak => 'Layak',
            self::LayakBersyarat => 'Layak dengan Catatan',
            self::TidakLayak => 'Tidak Layak',
            self::Dikembalikan => 'Dikembalikan untuk Perbaikan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
