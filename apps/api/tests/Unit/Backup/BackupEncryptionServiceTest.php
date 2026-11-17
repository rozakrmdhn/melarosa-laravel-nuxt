<?php

use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tempDir = storage_path('app/private/temporary/test_enc_'.Str::uuid());
    File::ensureDirectoryExists($this->tempDir);
    config([
        'backup.encryption.key' => 'base64:'.base64_encode(random_bytes(32)),
        'backup.encryption.chunk_size' => 65536, // 64 KiB untuk memaksa multi-chunk
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

it('mengenkripsi dan mendekripsi arsip multi-chunk dengan AES-256-GCM secara utuh', function () {
    $service = app(BackupEncryptionService::class);
    $src = $this->tempDir.'/plain.bin';
    $enc = $this->tempDir.'/plain.bin.enc';
    $dec = $this->tempDir.'/decrypted.bin';

    $payload = random_bytes(160000); // > 2 chunk (64 KiB * 2 + sisa)
    file_put_contents($src, $payload);

    $service->encryptFile($src, $enc);

    expect(file_exists($enc))->toBeTrue()
        ->and($service->isEncryptedFile($enc))->toBeTrue()
        ->and(file_get_contents($enc))->not->toBe($payload);

    $service->decryptFile($enc, $dec);

    expect(hash_file('sha256', $dec))->toBe(hash('sha256', $payload));
});

it('menolak dekripsi jika kunci enkripsi berbeda atau file rusak/dimodifikasi', function () {
    $service = app(BackupEncryptionService::class);
    $src = $this->tempDir.'/source.bin';
    $enc = $this->tempDir.'/source.bin.enc';
    $corrupt = $this->tempDir.'/corrupt.bin.enc';
    $out = $this->tempDir.'/out.bin';

    file_put_contents($src, random_bytes(90000));
    $service->encryptFile($src, $enc);

    // Uji korup 1 byte pada ciphertext
    $bytes = file_get_contents($enc);
    $mid = (int) floor(strlen($bytes) / 2);
    $bytes[$mid] = chr(ord($bytes[$mid]) ^ 0xFF);
    file_put_contents($corrupt, $bytes);

    expect(fn () => $service->decryptFile($corrupt, $out))->toThrow(BackupException::class);

    // Uji kunci berbeda
    config(['backup.encryption.key' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(fn () => $service->decryptFile($enc, $out))->toThrow(BackupException::class);

    // Pastikan output gagal dibersihkan
    expect(file_exists($out))->toBeFalse();
});

it('menolak enkripsi jika BACKUP_ENCRYPTION_KEY tidak valid atau terlalu pendek', function () {
    $service = app(BackupEncryptionService::class);
    $src = $this->tempDir.'/plain.txt';
    $enc = $this->tempDir.'/plain.txt.enc';
    file_put_contents($src, 'rahasia melarosa');

    config(['backup.encryption.key' => 'terlalu-pendek']);
    expect($service->isConfigured())->toBeFalse()
        ->and(fn () => $service->encryptFile($src, $enc))->toThrow(BackupException::class);
});
