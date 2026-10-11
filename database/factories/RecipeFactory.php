<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'prep_minutes' => fake()->randomElement([5, 10, 15, 20, 30]),
            'cook_minutes' => fake()->randomElement([0, 10, 20, 30, 45, 60, 90]),
            'servings' => fake()->numberBetween(1, 8),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'image_path' => null,
        ];
    }
}
