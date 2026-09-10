<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * The compendium, through its one writer.
 *
 * Seeding calls the command rather than reading the file itself, so the checksum guard
 * and the "never delete" rule hold on every path that fills the table.
 */
class StatBlockSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('demgem:import-srd', [], $this->command->getOutput());
    }
}
