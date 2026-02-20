<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CustomerAndPromotionGroupsSeeder::class,
            ServiceCategoriesAndServicesSeeder::class,
            ToothConditionSeeder::class,
            DiseaseSeeder::class,
            ClinicSettingsSeeder::class,
        ]);
    }
}
