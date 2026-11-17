<?php

namespace Database\Seeders;

use App\Models\BackupSchedule;
use Illuminate\Database\Seeder;

class BackupScheduleSeeder extends Seeder
{
    public function run(): void
    {
        BackupSchedule::ensureDefaultsExist();
    }
}

