<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'skill_id' => Skill::factory(),
            'author' => fake()->name(),
            'model' => 'Claude Sonnet',
            'verdict' => fake()->randomElement(Review::VERDICTS),
            'body' => fake()->paragraph(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['is_approved' => true]);
    }
}
