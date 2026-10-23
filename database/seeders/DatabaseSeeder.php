<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-safe reference data and draft (unpublished) service catalogue.
        $this->call([TaxonomySeeder::class, ServiceCatalogueSeeder::class]);
    }
}
