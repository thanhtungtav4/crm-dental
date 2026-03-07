<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
        ]);
    }
}
