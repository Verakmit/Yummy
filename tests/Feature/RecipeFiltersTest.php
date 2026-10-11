<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeFiltersTest extends TestCase
{
    // The full-text index only sees committed rows, so these tests cannot run inside a transaction
    use DatabaseTruncation;

    public function test_search_matches_title_and_description_by_word_prefix(): void
    {
        Recipe::factory()->create(['title' => 'Tortilla de patatas', 'description' => 'Con cebolla.']);
        Recipe::factory()->create(['title' => 'Gazpacho andaluz', 'description' => 'Sopa fría de tomate.']);

        Livewire::test('pages::home')
            ->set('search', 'torti')
            ->assertSee('Tortilla de patatas')
            ->assertDontSee('Gazpacho andaluz')
            ->set('search', 'tomate')
            ->assertSee('Gazpacho andaluz')
            ->assertDontSee('Tortilla de patatas')
            ->set('search', 'tortilla de patatas')
            ->assertSee('Tortilla de patatas')
            ->set('search', 'tortilla tomate')
            ->assertSee('Ninguna receta coincide con estos filtros.');
    }

    public function test_search_ignores_accents_and_boolean_operators(): void
    {
        Recipe::factory()->create(['title' => 'Crema de champiñones', 'description' => null]);

        Livewire::test('pages::home')
            ->set('search', 'champinones')
            ->assertSee('Crema de champiñones')
            ->set('search', '+-crema* ("')
            ->assertSee('Crema de champiñones');
    }

    public function test_short_search_terms_fall_back_to_a_title_match(): void
    {
        Recipe::factory()->create(['title' => 'Té con limón', 'description' => null]);
        Recipe::factory()->create(['title' => 'Gazpacho', 'description' => null]);

        Livewire::test('pages::home')
            ->set('search', 'té')
            ->assertSee('Té con limón')
            ->assertDontSee('Gazpacho');
    }

    public function test_filters_by_category_difficulty_and_total_time(): void
    {
        $desserts = Category::factory()->create(['name' => 'Postres']);
        Recipe::factory()->create([
            'category_id' => $desserts->id, 'title' => 'Flan rápido',
            'difficulty' => Difficulty::Easy, 'prep_minutes' => 10, 'cook_minutes' => 20,
        ]);
        Recipe::factory()->create([
            'title' => 'Cocido lento',
            'difficulty' => Difficulty::Hard, 'prep_minutes' => 30, 'cook_minutes' => 120,
        ]);

        Livewire::test('pages::home')
            ->set('category', 'postres')
            ->assertSee('Flan rápido')->assertDontSee('Cocido lento')
            ->set('category', '')
            ->set('difficulty', 'hard')
            ->assertSee('Cocido lento')->assertDontSee('Flan rápido')
            ->set('difficulty', '')
            ->set('maxTime', '30')
            ->assertSee('Flan rápido')->assertDontSee('Cocido lento');
    }

    public function test_filters_by_ingredient_and_requires_every_selected_tag(): void
    {
        $vegan = Tag::factory()->create(['name' => 'Vegano']);
        $quick = Tag::factory()->create(['name' => 'Rápido']);
        $tomato = Ingredient::factory()->create(['name' => 'Tomate']);

        $gazpacho = Recipe::factory()->create(['title' => 'Gazpacho']);
        $gazpacho->tags()->attach([$vegan->id, $quick->id]);
        $gazpacho->ingredients()->attach($tomato);

        $lentils = Recipe::factory()->create(['title' => 'Lentejas']);
        $lentils->tags()->attach($vegan);

        Livewire::test('pages::home')
            ->set('tags', ['vegano'])
            ->assertSee('Gazpacho')->assertSee('Lentejas')
            ->set('tags', ['vegano', 'rapido'])
            ->assertSee('Gazpacho')->assertDontSee('Lentejas')
            ->set('tags', [])
            ->set('ingredient', 'tomate')
            ->assertSee('Gazpacho')->assertDontSee('Lentejas');
    }

    public function test_filters_are_read_from_the_query_string_and_can_be_cleared(): void
    {
        Recipe::factory()->create(['title' => 'Receta fácil', 'difficulty' => Difficulty::Easy]);
        Recipe::factory()->create(['title' => 'Receta difícil', 'difficulty' => Difficulty::Hard]);

        $this->get('/?dificultad=easy')
            ->assertOk()
            ->assertSee('Receta fácil')
            ->assertDontSee('Receta difícil')
            ->assertSee('Limpiar filtros');

        Livewire::withQueryParams(['dificultad' => 'easy'])
            ->test('pages::home')
            ->assertSet('difficulty', 'easy')
            ->call('clearFilters')
            ->assertSet('difficulty', '')
            ->assertSee('Receta difícil');
    }

    public function test_invalid_query_string_values_do_not_break_the_page(): void
    {
        Recipe::factory()->create(['title' => 'Receta visible']);

        $this->get('/?dificultad=imposible&tiempo=abc&etiquetas[0][x]=1&categoria[]=x')
            ->assertOk();
    }

    public function test_changing_a_filter_returns_to_the_first_page(): void
    {
        Recipe::factory()->count(13)->create(['difficulty' => Difficulty::Easy]);

        Livewire::withQueryParams(['page' => 2])
            ->test('pages::home')
            ->assertSet('paginators.page', 2)
            ->set('difficulty', 'easy')
            ->assertSet('paginators.page', 1);
    }
}
