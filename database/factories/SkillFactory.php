<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SkillFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(4, true);

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
