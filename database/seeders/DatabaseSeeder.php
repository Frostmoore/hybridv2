<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed dello sviluppo locale.
     */
    public function run(): void
    {
        $this->call(DevSeeder::class);
    }
}
