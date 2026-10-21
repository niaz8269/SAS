<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Country> */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'is_destination' => false,
        ];
    }

    public function destination(): static
    {
        return $this->state(['is_destination' => true, 'guidance_published' => true]);
    }
}