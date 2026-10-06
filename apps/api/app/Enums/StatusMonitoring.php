<?php

namespace App\Enums;

enum StatusMonitoring: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reverted = 'reverted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Reverted => 'Direvisi (Reverted)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
