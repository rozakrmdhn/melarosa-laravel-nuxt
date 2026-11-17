<?php

namespace App\Http\Requests\Backup;

use App\Models\BackupHistory;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBackupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1', 'max:10'],
            'schedules.*.backup_type' => ['required', 'string', Rule::in(BackupHistory::TYPES)],
            'schedules.*.name' => ['sometimes', 'string', 'max:120'],
            'schedules.*.is_active' => ['required', 'boolean'],
            'schedules.*.frequency' => ['required', 'string', Rule::in(BackupSchedule::FREQUENCIES)],
            'schedules.*.cron_expression' => ['nullable', 'string', 'max:100'],
            'schedules.*.run_time' => ['required', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'schedules.*.day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'schedules.*.day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'schedules.*.timezone' => ['sometimes', 'string', 'timezone:all'],
            'schedules.*.retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'schedules.*.encryption_enabled' => ['sometimes', 'boolean'],
            'schedules.*.replicate_to_remote' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schedules = $this->input('schedules', []);
                if (! is_array($schedules)) {
                    return;
                }

                foreach ($schedules as $idx => $item) {
                    if (($item['frequency'] ?? null) === BackupSchedule::FREQUENCY_CRON) {
                        $expr = trim((string) ($item['cron_expression'] ?? ''));
                        if ($expr === '' || ! CronExpression::isValidExpression($expr)) {
                            $validator->errors()->add(
                                "schedules.{$idx}.cron_expression",
                                'Ekspresi cron 5-field tidak valid.'
                            );
                        }
                    }

                    if (($item['frequency'] ?? null) === BackupSchedule::FREQUENCY_WEEKLY && ! isset($item['day_of_week'])) {
                        $validator->errors()->add(
                            "schedules.{$idx}.day_of_week",
                            'Hari eksekusi wajib diisi untuk frekuensi mingguan.'
                        );
                    }
                }
            },
        ];
    }
}

