<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductionMasterDataSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                LocalDemoDataSeeder::class,
            ]);
        }
    }
}
