<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\ShoppingList;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use DatabaseTruncation;

    private function tortilla(): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => 'Tortilla de patatas', 'servings' => 4]);

        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Patata']), ['position' => 1, 'quantity' => 700, 'unit' => 'g']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Huevo']), ['position' => 2, 'quantity' => 6, 'unit' => 'ud']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Sal']), ['position' => 3, 'unit' => 'al gusto']);

        return $recipe;
    }

    /**
     * @return array<string, string>
     */
    private function amounts(Recipe $recipe, int $diners): array
    {
        return ShoppingList::for($recipe->fresh(), $diners)->pluck('amount', 'name')->all();
    }

    public function test_quantities_are_scaled_to_the_number_of_diners(): void
    {
        $recipe = $this->tortilla();

        $this->assertSame(['Patata' => '700 g', 'Huevo' => '6 ud', 'Sal' => 'al gusto'], $this->amounts($recipe, 4));
        $this->assertSame(['Patata' => '1400 g', 'Huevo' => '12 ud', 'Sal' => 'al gusto'], $this->amounts($recipe, 8));
        $this->assertSame(['Patata' => '175 g', 'Huevo' => '1,5 ud', 'Sal' => 'al gusto'], $this->amounts($recipe, 1));
    }

    public function test_large_amounts_are_rounded_and_small_ones_keep_decimals(): void
    {
        $recipe = Recipe::factory()->create(['servings' => 3]);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Harina']), ['position' => 1, 'quantity' => 700, 'unit' => 'g']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Limón']), ['position' => 2, 'quantity' => 1, 'unit' => 'ud']);

        $this->assertSame(['Harina' => '233 g', 'Limón' => '0,33 ud'], $this->amounts($recipe, 1));
    }

    public function test_repeated_ingredients_are_added_together_when_the_unit_matches(): void
    {
        $recipe = Recipe::factory()->create(['servings' => 2]);
        $salt = Ingredient::factory()->create(['name' => 'Sal']);
        $recipe->ingredients()->attach($salt, ['position' => 1, 'quantity' => 5, 'unit' => 'g', 'note' => 'para la masa']);
        $recipe->ingredients()->attach($salt, ['position' => 2, 'quantity' => 3, 'unit' => 'G', 'note' => 'para el relleno']);
        $recipe->ingredients()->attach($salt, ['position' => 3, 'quantity' => 1, 'unit' => 'pizca']);

        $list = ShoppingList::for($recipe->fresh(), 4);

        $this->assertSame(['16 g', '2 pizca'], $list->pluck('amount')->all());
        $this->assertSame(['Sal', 'Sal'], $list->pluck('name')->all());
    }

    public function test_page_defaults_to_the_recipe_servings_and_reads_diners_from_the_url(): void
    {
        $this->tortilla();

        $this->get('/recetas/tortilla-de-patatas/lista-de-la-compra')
            ->assertOk()
            ->assertSee('Tortilla de patatas · 4 comensales')
            ->assertSeeInOrder(['Patata', '700 g', 'Huevo', '6 ud', 'Sal', 'al gusto']);

        $this->get('/recetas/tortilla-de-patatas/lista-de-la-compra?comensales=2')
            ->assertOk()
            ->assertSee('Tortilla de patatas · 2 comensales')
            ->assertSeeInOrder(['Patata', '350 g', 'Huevo', '3 ud']);

        $this->get('/recetas/no-existe/lista-de-la-compra')->assertNotFound();
    }

    public function test_diners_can_be_changed_and_stay_within_limits(): void
    {
        $recipe = $this->tortilla();

        Livewire::test('pages::recipes.shopping-list', ['recipe' => $recipe])
            ->assertSet('diners', 4)
            ->call('increment')
            ->assertSet('diners', 5)
            ->assertSee('875 g')
            ->set('diners', '1')
            ->assertSee('1 comensal')
            ->assertSee('175 g')
            ->call('decrement')
            ->assertSet('diners', 1)
            ->set('diners', '5000')
            ->assertSee('100 comensales')
            ->set('diners', '')
            ->assertSee('4 comensales')
            ->set('diners', '-3')
            ->assertSee('1 comensal');
    }

    public function test_invalid_diners_in_the_url_do_not_break_the_page(): void
    {
        $this->tortilla();

        $this->get('/recetas/tortilla-de-patatas/lista-de-la-compra?comensales=abc')->assertOk()->assertSee('4 comensales');
        $this->get('/recetas/tortilla-de-patatas/lista-de-la-compra?comensales[]=2')->assertOk()->assertSee('4 comensales');
    }

    public function test_recipe_page_links_to_its_shopping_list(): void
    {
        $this->tortilla();

        $this->get('/recetas/tortilla-de-patatas')
            ->assertOk()
            ->assertSee(route('recipes.shopping-list', 'tortilla-de-patatas'));
    }

    public function test_nothing_is_stored_when_a_list_is_generated(): void
    {
        $recipe = $this->tortilla();

        Livewire::test('pages::recipes.shopping-list', ['recipe' => $recipe])->set('diners', '9');

        $this->assertSame(4, $recipe->fresh()->servings);
        $this->assertEquals(700, $recipe->fresh()->ingredients->first()->pivot->quantity);
    }
}
