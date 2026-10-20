<?php

namespace App\Enums;

enum StatusBkkdProposal: string
{
    case Draft = 'draft';
    case DiajukanOpd = 'diajukan_opd';
    case Dikembalikan = 'dikembalikan';
    case Layak = 'layak';
    case LayakBersyarat = 'layak_bersyarat';
    case TidakLayak = 'tidak_layak';
    case DitetapkanTapd = 'ditetapkan_tapd';
    case TidakTerpilih = 'tidak_terpilih';
    case DigantikanPapbd = 'digantikan_papbd';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::DiajukanOpd => 'Menunggu Telaah OPD',
            self::Dikembalikan => 'Dikembalikan untuk Perbaikan',
            self::Layak => 'Layak',
            self::LayakBersyarat => 'Layak dengan Catatan',
            self::TidakLayak => 'Tidak Layak',
            self::DitetapkanTapd => 'Masuk Rancangan APBD (Pilihan TAPD)',
            self::TidakTerpilih => 'Tidak Terpilih (Cadangan)',
            self::DigantikanPapbd => 'Digantikan di P-APBD',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
