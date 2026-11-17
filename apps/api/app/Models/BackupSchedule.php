<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Konfigurasi jadwal backup otomatis (database, file, dan full backup).
 */
class BackupSchedule extends Model
{
    use HasUuids;

    public const FREQUENCY_HOURLY = 'hourly';

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_CRON = 'cron';

    /** @var list<string> */
    public const FREQUENCIES = [
        self::FREQUENCY_HOURLY,
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_CRON,
    ];

    protected $fillable = [
        'name',
        'backup_type',
        'is_active',
        'frequency',
        'cron_expression',
        'run_time',
        'day_of_week',
        'day_of_month',
        'timezone',
        'retention_days',
        'encryption_enabled',
        'replicate_to_remote',
        'last_run_at',
        'last_status',
        'next_run_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'retention_days' => 'integer',
            'encryption_enabled' => 'boolean',
            'replicate_to_remote' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Menghasilkan ekspresi cron 5-field yang ekuivalen dengan konfigurasi jadwal.
     */
    public function toCronExpression(): string
    {
        if ($this->frequency === self::FREQUENCY_CRON && filled($this->cron_expression)) {
            return trim((string) $this->cron_expression);
        }

        [$hour, $minute] = $this->parseRunTime();

        return match ($this->frequency) {
            self::FREQUENCY_HOURLY => sprintf('%d * * * *', $minute),
            self::FREQUENCY_WEEKLY => sprintf('%d %d * * %d', $minute, $hour, $this->day_of_week ?? 0),
            self::FREQUENCY_MONTHLY => sprintf('%d %d %d * *', $minute, $hour, max(1, min(28, (int) ($this->day_of_month ?? 1)))),
            default => sprintf('%d %d * * *', $minute, $hour),
        };
    }

    /**
     * Menghitung waktu eksekusi berikutnya dalam timezone jadwal (default Asia/Jakarta).
     */
    public function computeNextRunAt(?Carbon $from = null): Carbon
    {
        $tz = $this->timezone ?: (string) config('backup.timezone', 'Asia/Jakarta');
        $reference = ($from ? $from->copy() : now($tz))->setTimezone($tz);
        $expression = new CronExpression($this->toCronExpression());
        $next = $expression->getNextRunDate($reference->toDateTimeImmutable(), 0, false, $tz);

        return Carbon::instance($next)->setTimezone($tz);
    }

    /**
     * Memastikan 3 konfigurasi jadwal bawaan tersedia tanpa duplikasi.
     */
    public static function ensureDefaultsExist(): void
    {
        $tz = (string) config('backup.timezone', 'Asia/Jakarta');
        $defaults = [
            [
                'backup_type' => BackupHistory::TYPE_DATABASE,
                'name' => 'Backup Database Harian',
                'is_active' => true,
                'frequency' => self::FREQUENCY_DAILY,
                'run_time' => '01:00',
                'day_of_week' => null,
                'day_of_month' => null,
                'timezone' => $tz,
                'retention_days' => (int) config('backup.retention.database', 14),
                'encryption_enabled' => true,
                'replicate_to_remote' => false,
            ],
            [
                'backup_type' => BackupHistory::TYPE_FILE,
                'name' => 'Backup File Storage Harian',
                'is_active' => true,
                'frequency' => self::FREQUENCY_DAILY,
                'run_time' => '02:00',
                'day_of_week' => null,
                'day_of_month' => null,
                'timezone' => $tz,
                'retention_days' => (int) config('backup.retention.file', 7),
                'encryption_enabled' => true,
                'replicate_to_remote' => false,
            ],
            [
                'backup_type' => BackupHistory::TYPE_FULL,
                'name' => 'Backup Full Mingguan',
                'is_active' => true,
                'frequency' => self::FREQUENCY_WEEKLY,
                'run_time' => '03:00',
                'day_of_week' => 0,
                'day_of_month' => null,
                'timezone' => $tz,
                'retention_days' => (int) config('backup.retention.full', 56),
                'encryption_enabled' => true,
                'replicate_to_remote' => false,
            ],
        ];

        foreach ($defaults as $definition) {
            $schedule = static::firstOrNew(['backup_type' => $definition['backup_type']]);
            if (! $schedule->exists) {
                $schedule->fill($definition);
                $schedule->next_run_at = $schedule->computeNextRunAt();
                $schedule->save();
            }
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseRunTime(): array
    {
        $parts = explode(':', (string) ($this->run_time ?: '01:00'));
        $hour = isset($parts[0]) ? max(0, min(23, (int) $parts[0])) : 1;
        $minute = isset($parts[1]) ? max(0, min(59, (int) $parts[1])) : 0;

        return [$hour, $minute];
    }
}

