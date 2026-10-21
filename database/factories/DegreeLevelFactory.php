<?php

namespace Database\Factories;

use App\Models\DegreeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DegreeLevel> */
class DegreeLevelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['code' => Str::slug($name), 'name' => ucfirst($name), 'slug' => Str::slug($name), 'sort' => 1];
    }
}
