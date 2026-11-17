<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
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
        $maxKb = max(1024, ((int) config('backup.upload.max_size_mb', 2048)) * 1024);

        return [
            'backup_file' => ['required', 'file', "max:{$maxKb}"],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

