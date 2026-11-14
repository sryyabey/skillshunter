<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SkillSuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author' => fake()->name(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
