<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-safe reference data, the service catalogue and destination pages that point to official sources.
        $this->call([TaxonomySeeder::class, ServiceCatalogueSeeder::class, DestinationGuidesSeeder::class]);
    }
}
