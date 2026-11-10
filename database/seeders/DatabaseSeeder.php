<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Data demo hanya diisi di lingkungan local, tidak pernah di produksi.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
