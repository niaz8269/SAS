<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-safe reference data and draft (unpublished) service catalogue.
        $this->call([TaxonomySeeder::class, ServiceCatalogueSeeder::class]);

        // Demo records only when explicitly requested and never in production: `php artisan db:seed --class=DemoSeeder`.
        if (! app()->isProduction() && config('app.env') === 'local' && env('SAS_SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
