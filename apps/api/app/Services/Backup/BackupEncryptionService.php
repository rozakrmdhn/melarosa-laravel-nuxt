<?php

namespace App\Services\Backup;

/**
 * Streaming chunked AES-256-GCM encryption/decryption untuk arsip backup berukuran besar.
 *
 * Setiap chunk terikat dengan index chunk dan flag final-chunk via AAD sehingga
 * modifikasi bit, pemotongan trailing chunk, atau penukaran urutan chunk langsung gagal.
 */
class BackupEncryptionService
{
    private const MAGIC = 'MLRBK001';

    private const FORMAT_VERSION = 1;

    private const ALG_AES_256_GCM = 1;

    private const HEADER_LENGTH = 30; // 8 (magic) + 1 (ver) + 1 (alg) + 4 (chunkSize) + 16 (salt)

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    private const MAX_ALLOWED_CHUNK_SIZE = 16777216; // 16 MiB batas keamanan memori

    public function isConfigured(): bool
    {
        try {
            $this->resolveMasterKey();

            return true;
        } catch (BackupException) {
            return false;
        }
    }

    public function isEncryptedFile(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, strlen(self::MAGIC));
        fclose($handle);

        return $header === self::MAGIC;
    }

    public function encryptFile(string $sourcePath, string $destinationPath): void
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new BackupException('File sumber enkripsi backup tidak ditemukan.');
        }

        $masterKey = $this->resolveMasterKey();
        $chunkSize = max(65536, min(self::MAX_ALLOWED_CHUNK_SIZE, (int) config('backup.encryption.chunk_size', 1048576)));
        $salt = random_bytes(16);
        $derivedKey = $this->deriveFileKey($masterKey, $salt);

        $in = @fopen($sourcePath, 'rb');
        $out = @fopen($destinationPath, 'wb');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            throw new BackupException('Gagal membuka stream file untuk proses enkripsi backup.');
        }

        try {
            $header = self::MAGIC
                .pack('CC', self::FORMAT_VERSION, self::ALG_AES_256_GCM)
                .pack('N', $chunkSize)
                .$salt;
            $this->writeExact($out, $header);

            $fileSize = (int) filesize($sourcePath);
            $bytesReadTotal = 0;
            $chunkIndex = 0;

            if ($fileSize === 0) {
                $this->writeEncryptedChunk($out, '', $derivedKey, 0, true);

                return;
            }

            while (! feof($in)) {
                $plaintext = fread($in, $chunkSize);
                if ($plaintext === false) {
                    throw new BackupException('Gagal membaca data sumber saat enkripsi.');
                }
                if ($plaintext === '') {
                    break;
                }

                $bytesReadTotal += strlen($plaintext);
                $isFinal = $bytesReadTotal >= $fileSize || feof($in);

                $this->writeEncryptedChunk($out, $plaintext, $derivedKey, $chunkIndex, $isFinal);
                $chunkIndex++;
            }
        } catch (\Throwable $e) {
            @unlink($destinationPath);
            if ($e instanceof BackupException) {
                throw $e;
            }
            throw new BackupException('Terjadi kesalahan saat mengenkripsi file backup.');
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decryptFile(string $sourcePath, string $destinationPath): void
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new BackupException('File terenkripsi tidak ditemukan atau tidak dapat dibaca.');
        }

        $masterKey = $this->resolveMasterKey();
        $in = @fopen($sourcePath, 'rb');
        $out = @fopen($destinationPath, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            throw new BackupException('Gagal membuka stream untuk proses dekripsi backup.');
        }

        try {
            $header = $this->readExact($in, self::HEADER_LENGTH);
            if ($header === null || substr($header, 0, 8) !== self::MAGIC) {
                throw new BackupException('Format header arsip terenkripsi tidak valid.');
            }

            $meta = unpack('Cversion/Calgorithm/Nchunk_size', substr($header, 8, 6));
            if ($meta === false || $meta['version'] !== self::FORMAT_VERSION || $meta['algorithm'] !== self::ALG_AES_256_GCM) {
                throw new BackupException('Versi atau algoritma enkripsi arsip tidak didukung.');
            }

            $chunkSize = (int) $meta['chunk_size'];
            if ($chunkSize < 1 || $chunkSize > self::MAX_ALLOWED_CHUNK_SIZE) {
                throw new BackupException('Ukuran chunk arsip terenkripsi di luar batas yang diizinkan.');
            }

            $salt = substr($header, 14, 16);
            $derivedKey = $this->deriveFileKey($masterKey, $salt);

            $chunkIndex = 0;
            $sawFinal = false;

            while (true) {
                $chunkMetaRaw = fread($in, 5);
                if ($chunkMetaRaw === '' || $chunkMetaRaw === false) {
                    break;
                }
                if (strlen($chunkMetaRaw) !== 5) {
                    throw new BackupException('Dekripsi arsip backup gagal: struktur chunk terpotong.');
                }

                $chunkMeta = unpack('Nlen/Cis_final', $chunkMetaRaw);
                if ($chunkMeta === false) {
                    throw new BackupException('Dekripsi arsip backup gagal: metadata chunk tidak valid.');
                }

                $cipherLength = (int) $chunkMeta['len'];
                $isFinal = ((int) $chunkMeta['is_final']) === 1;

                if ($cipherLength < 0 || $cipherLength > $chunkSize) {
                    throw new BackupException('Dekripsi arsip backup gagal: panjang payload chunk tidak valid.');
                }

                $iv = $this->readExact($in, self::IV_LENGTH);
                $tag = $this->readExact($in, self::TAG_LENGTH);
                $ciphertext = $cipherLength > 0 ? $this->readExact($in, $cipherLength) : '';

                if ($iv === null || $tag === null || $ciphertext === null) {
                    throw new BackupException('Dekripsi arsip backup gagal: data chunk tidak lengkap.');
                }

                $aad = pack('NC', $chunkIndex, $isFinal ? 1 : 0);
                $plaintext = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $derivedKey,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    $aad
                );

                if ($plaintext === false) {
                    throw new BackupException('Dekripsi arsip backup gagal: kunci enkripsi tidak cocok atau file arsip rusak.');
                }

                if ($plaintext !== '') {
                    $this->writeExact($out, $plaintext);
                }

                $chunkIndex++;
                if ($isFinal) {
                    $sawFinal = true;
                    break;
                }
            }

            // Tolak trailing byte di luar chunk final untuk mencegah manipulasi file.
            $trailing = fread($in, 1);
            if (! $sawFinal || ($trailing !== '' && $trailing !== false)) {
                throw new BackupException('Dekripsi arsip backup gagal: arsip terpotong atau memiliki byte tambahan yang tidak sah.');
            }
        } catch (\Throwable $e) {
            @fclose($out);
            @unlink($destinationPath);
            if ($e instanceof BackupException) {
                throw $e;
            }
            throw new BackupException('Dekripsi arsip backup gagal.');
        } finally {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
        }
    }

    private function writeEncryptedChunk($out, string $plaintext, string $derivedKey, int $chunkIndex, bool $isFinal): void
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $aad = pack('NC', $chunkIndex, $isFinal ? 1 : 0);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new BackupException('Proses enkripsi AES-256-GCM gagal menghasilkan tag autentikasi.');
        }

        $payload = pack('NC', strlen($ciphertext), $isFinal ? 1 : 0).$iv.$tag.$ciphertext;
        $this->writeExact($out, $payload);
    }

    private function resolveMasterKey(): string
    {
        $rawKey = (string) config('backup.encryption.key', '');
        if ($rawKey === '') {
            throw new BackupException('BACKUP_ENCRYPTION_KEY belum dikonfigurasi pada environment server.');
        }

        if (str_starts_with($rawKey, 'base64:')) {
            $decoded = base64_decode(substr($rawKey, 7), true);
            if ($decoded === false || strlen($decoded) < 32) {
                throw new BackupException('Format BACKUP_ENCRYPTION_KEY base64 tidak valid atau kurang dari 32 byte.');
            }

            return substr($decoded, 0, 32);
        }

        if (strlen($rawKey) < 32) {
            throw new BackupException('BACKUP_ENCRYPTION_KEY minimal harus 32 karakter atau base64:32-byte.');
        }

        return hash('sha256', $rawKey, true);
    }

    private function deriveFileKey(string $masterKey, string $salt): string
    {
        return hash_hkdf('sha256', $masterKey, 32, 'melarosa-backup-v1', $salt);
    }

    private function writeExact($handle, string $data): void
    {
        $total = strlen($data);
        $written = 0;
        while ($written < $total) {
            $bytes = fwrite($handle, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new BackupException('Gagal menulis stream file backup.');
            }
            $written += $bytes;
        }
    }

    private function readExact($handle, int $length): ?string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($handle, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }
}

