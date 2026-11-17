<?php

namespace App\Http\Requests\Backup;

use App\Models\BackupHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackupRequest extends FormRequest
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
            'backup_type' => ['required', 'string', Rule::in(BackupHistory::TYPES)],
            'backup_name' => ['nullable', 'string', 'max:150'],
            'encrypt' => ['sometimes', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'replicate_to_remote' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

