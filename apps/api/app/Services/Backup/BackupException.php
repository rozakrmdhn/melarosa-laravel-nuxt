<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Exception operasional untuk modul Backup & Restore tanpa membocorkan kredensial sensitif.
 */
class BackupException extends RuntimeException
{
}

