<?php

namespace App\Http\Requests\Backup;

use App\Models\BackupHistory;
use App\Models\BackupRestoreHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreBackupRequest extends FormRequest
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
        $expectedPhrase = (string) config('backup.restore.confirmation_phrase', 'KONFIRMASI RESTORE');

        return [
            'backup_id' => ['required', 'uuid', 'exists:backup_histories,id'],
            'restore_scope' => ['required', 'string', Rule::in(BackupHistory::TYPES)],
            'target_mode' => ['required', 'string', Rule::in([
                BackupRestoreHistory::TARGET_MODE_ISOLATED,
                BackupRestoreHistory::TARGET_MODE_ACTIVE,
            ])],
            'target_database' => [
                'nullable',
                'string',
                'max:63',
                'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                Rule::requiredIf(fn () => $this->input('target_mode') === BackupRestoreHistory::TARGET_MODE_ISOLATED
                    && in_array($this->input('restore_scope'), [BackupHistory::TYPE_DATABASE, BackupHistory::TYPE_FULL], true)),
            ],
            'create_safety_backup' => ['sometimes', 'boolean'],
            'confirm_understood' => ['required', 'accepted'],
            'confirmation_phrase' => ['required', 'string', Rule::in([$expectedPhrase])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $phrase = (string) config('backup.restore.confirmation_phrase', 'KONFIRMASI RESTORE');

        return [
            'confirm_understood.accepted' => 'Anda harus mencentang pernyataan risiko sebelum menjalankan restore.',
            'confirmation_phrase.in' => "Ketik '{$phrase}' secara tepat untuk mengonfirmasi restore.",
            'target_database.regex' => 'Nama database target hanya boleh berisi huruf, angka, dan underscore.',
        ];
    }
}

