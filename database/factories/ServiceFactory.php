<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'), 'category' => 'advisory', 'name' => ucfirst($name),
            'summary' => fake()->sentence(), 'published' => true];
    }
}