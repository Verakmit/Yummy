<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class RecipePagesTest extends TestCase
{
    use DatabaseTruncation;

    public function test_home_shows_an_empty_state_without_recipes(): void
    {
        $this->get('/')->assertOk()->assertSee('Todavía no hay recetas.');
    }

    public function test_home_lists_recipes_newest_first_with_their_summary(): void
    {
        $category = Category::factory()->create(['name' => 'Postres']);
        Recipe::factory()->create(['title' => 'Receta antigua', 'created_at' => now()->subDay()]);
        Recipe::factory()->create([
            'category_id' => $category->id,
            'title' => 'Receta nueva',
            'prep_minutes' => 15,
            'cook_minutes' => 60,
            'servings' => 4,
            'difficulty' => Difficulty::Hard,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Receta nueva', 'Receta antigua'])
            ->assertSee('Postres')
            ->assertSee('1 h 15 min · Difícil · 4 raciones')
            ->assertSee(route('recipes.show', 'receta-nueva'));
    }

    public function test_home_paginates_twelve_recipes_per_page(): void
    {
        Recipe::factory()->count(12)->create();
        Recipe::factory()->create(['title' => 'La más antigua', 'created_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertDontSee('La más antigua');
        $this->get('/?page=2')->assertOk()->assertSee('La más antigua');
    }

    public function test_recipe_page_shows_ingredients_steps_and_tags(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Tortilla de patatas', 'description' => 'La de toda la vida.']);
        $recipe->tags()->attach(Tag::factory()->create(['name' => 'Tradicional']));
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Sal']), ['position' => 2, 'unit' => 'al gusto']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Cebolla']), ['position' => 1, 'quantity' => 0.5, 'unit' => 'ud']);
        $recipe->steps()->create(['position' => 2, 'body' => 'Cuajar la tortilla.']);
        $recipe->steps()->create(['position' => 1, 'body' => 'Batir los huevos.']);

        $this->get('/recetas/tortilla-de-patatas')
            ->assertOk()
            ->assertSee('<title>Tortilla de patatas · Yummy</title>', false)
            ->assertSee('La de toda la vida.')
            ->assertSee('Tradicional')
            ->assertSeeInOrder(['Cebolla', '0,5 ud', 'Sal', 'al gusto'])
            ->assertSeeInOrder(['Batir los huevos.', 'Cuajar la tortilla.']);
    }

    public function test_unknown_recipe_returns_not_found(): void
    {
        $this->get('/recetas/no-existe')->assertNotFound();
    }
}
