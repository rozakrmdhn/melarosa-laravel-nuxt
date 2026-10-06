<?php

namespace App\Enums;

enum KondisiSegmen: string
{
    case Baik = 'Baik';
    case Sedang = 'Sedang';
    case RusakRingan = 'Rusak Ringan';
    case RusakBerat = 'Rusak Berat';

    public function label(): string
    {
        return match ($this) {
            self::Baik => 'Baik',
            self::Sedang => 'Sedang',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
