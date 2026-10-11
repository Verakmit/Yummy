<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class RecipeModelTest extends TestCase
{
    use DatabaseTruncation;

    public function test_slug_is_generated_from_the_title_and_kept_unique(): void
    {
        $first = Recipe::factory()->create(['title' => 'Tortilla de patatas']);
        $second = Recipe::factory()->create(['title' => 'Tortilla de patatas']);

        $this->assertSame('tortilla-de-patatas', $first->slug);
        $this->assertSame('tortilla-de-patatas-2', $second->slug);
    }

    public function test_slug_does_not_change_when_the_recipe_is_updated(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Gazpacho']);

        $recipe->update(['title' => 'Gazpacho andaluz']);

        $this->assertSame('gazpacho', $recipe->slug);
    }

    public function test_ingredients_and_steps_come_back_in_position_order(): void
    {
        $recipe = Recipe::factory()->create();
        $salt = Ingredient::factory()->create(['name' => 'Sal']);
        $egg = Ingredient::factory()->create(['name' => 'Huevo']);

        $recipe->ingredients()->attach($salt, ['position' => 2, 'unit' => 'al gusto']);
        $recipe->ingredients()->attach($egg, ['position' => 1, 'quantity' => 6, 'unit' => 'ud']);
        $recipe->steps()->create(['position' => 2, 'body' => 'Cuajar']);
        $recipe->steps()->create(['position' => 1, 'body' => 'Batir']);

        $this->assertSame(['Huevo', 'Sal'], $recipe->ingredients->pluck('name')->all());
        $this->assertEquals(6, $recipe->ingredients->first()->pivot->quantity);
        $this->assertSame(['Batir', 'Cuajar'], $recipe->steps->pluck('body')->all());
    }

    public function test_difficulty_is_cast_to_the_enum(): void
    {
        $recipe = Recipe::factory()->create(['difficulty' => Difficulty::Hard]);

        $this->assertSame(Difficulty::Hard, $recipe->fresh()->difficulty);
        $this->assertSame('Difícil', $recipe->difficulty->label());
    }

    public function test_deleting_a_recipe_removes_its_steps_and_pivot_rows(): void
    {
        $recipe = Recipe::factory()->create();
        $recipe->steps()->create(['position' => 1, 'body' => 'Batir']);
        $recipe->ingredients()->attach(Ingredient::factory()->create());
        $recipe->tags()->attach(Tag::factory()->create());

        $recipe->delete();

        $this->assertDatabaseCount('steps', 0);
        $this->assertDatabaseCount('ingredient_recipe', 0);
        $this->assertDatabaseCount('recipe_tag', 0);
        $this->assertDatabaseCount('ingredients', 1);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_a_category_with_recipes_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Recipe::factory()->create(['category_id' => $category->id]);

        $this->expectException(QueryException::class);

        $category->delete();
    }

    public function test_database_seeder_builds_a_complete_catalogue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('recipes', 30);
        $this->assertSame(0, Recipe::doesntHave('steps')->count());
        $this->assertSame(0, Recipe::doesntHave('ingredients')->count());
    }
}
