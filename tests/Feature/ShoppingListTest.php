<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\ShoppingList;
use App\Support\ShoppingSelection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use DatabaseTruncation;

    private function ingredient(string $name): Ingredient
    {
        return Ingredient::firstOrCreate(['name' => $name]);
    }

    private function tortilla(): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => 'Tortilla de patatas', 'servings' => 4]);

        $recipe->ingredients()->attach($this->ingredient('Patata'), ['position' => 1, 'quantity' => 700, 'unit' => 'g']);
        $recipe->ingredients()->attach($this->ingredient('Huevo'), ['position' => 2, 'quantity' => 6, 'unit' => 'ud']);
        $recipe->ingredients()->attach($this->ingredient('Sal'), ['position' => 3, 'unit' => 'al gusto']);

        return $recipe;
    }

    private function tortitas(): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => 'Tortitas caseras', 'servings' => 2]);

        $recipe->ingredients()->attach($this->ingredient('Huevo'), ['position' => 1, 'quantity' => 2, 'unit' => 'ud']);
        $recipe->ingredients()->attach($this->ingredient('Azúcar'), ['position' => 2, 'quantity' => 30, 'unit' => 'g']);
        $recipe->ingredients()->attach($this->ingredient('Sal'), ['position' => 3, 'quantity' => 1, 'unit' => 'pizca']);

        return $recipe;
    }

    /**
     * @param  array<int, array{0: Recipe, 1: int}>  $entries
     * @return list<string>
     */
    private function lines(array $entries): array
    {
        return ShoppingList::combine(array_map(fn (array $entry) => ['recipe' => $entry[0]->fresh(), 'diners' => $entry[1]], $entries))
            ->map(fn (array $item) => trim($item['name'].' '.$item['amount']))
            ->all();
    }

    public function test_quantities_are_scaled_to_the_number_of_diners(): void
    {
        $recipe = $this->tortilla();

        $this->assertSame(['Huevo 6 ud', 'Patata 700 g', 'Sal al gusto'], $this->lines([[$recipe, 4]]));
        $this->assertSame(['Huevo 12 ud', 'Patata 1400 g', 'Sal al gusto'], $this->lines([[$recipe, 8]]));
        $this->assertSame(['Huevo 1,5 ud', 'Patata 175 g', 'Sal al gusto'], $this->lines([[$recipe, 1]]));
    }

    public function test_large_amounts_are_rounded_and_small_ones_keep_decimals(): void
    {
        $recipe = Recipe::factory()->create(['servings' => 3]);
        $recipe->ingredients()->attach($this->ingredient('Harina'), ['position' => 1, 'quantity' => 700, 'unit' => 'g']);
        $recipe->ingredients()->attach($this->ingredient('Limón'), ['position' => 2, 'quantity' => 1, 'unit' => 'ud']);

        $this->assertSame(['Harina 233 g', 'Limón 0,33 ud'], $this->lines([[$recipe, 1]]));
    }

    public function test_recipes_are_combined_adding_shared_ingredients_with_the_same_unit(): void
    {
        // Tortilla for 4 needs 6 eggs; tortitas for 4 (twice the recipe) need 4 more
        $this->assertSame(
            ['Azúcar 60 g', 'Huevo 10 ud', 'Patata 700 g', 'Sal al gusto', 'Sal 2 pizca'],
            $this->lines([[$this->tortilla(), 4], [$this->tortitas(), 4]]),
        );
    }

    public function test_a_repeated_ingredient_inside_one_recipe_is_added_together(): void
    {
        $recipe = Recipe::factory()->create(['servings' => 2]);
        $salt = $this->ingredient('Sal');
        $recipe->ingredients()->attach($salt, ['position' => 1, 'quantity' => 5, 'unit' => 'g']);
        $recipe->ingredients()->attach($salt, ['position' => 2, 'quantity' => 3, 'unit' => 'G']);

        $this->assertSame(['Sal 16 g'], $this->lines([[$recipe, 4]]));
    }

    public function test_empty_list_page_invites_to_browse_recipes(): void
    {
        $this->get('/lista-de-la-compra')
            ->assertOk()
            ->assertSee('Todavía no has añadido ninguna receta a la lista.');
    }

    public function test_recipes_can_be_added_from_the_recipe_page_and_from_the_listing(): void
    {
        $tortilla = $this->tortilla();
        $tortitas = $this->tortitas();

        Livewire::test('pages::recipes.show', ['recipe' => $tortilla])
            ->assertSee('Añadir a la lista de la compra')
            ->call('toggleShoppingList')
            ->assertDispatched('shopping-list-updated')
            ->assertSee('En la lista de la compra');

        Livewire::test('pages::home')
            ->assertSee('Quitar Tortilla de patatas de la lista de la compra')
            ->assertSee('Añadir Tortitas caseras a la lista de la compra')
            ->call('toggleShoppingList', $tortitas->id)
            ->assertDispatched('shopping-list-updated')
            ->assertSee('Quitar Tortitas caseras de la lista de la compra')
            ->call('toggleShoppingList', 999999);

        // Each recipe starts with its own number of servings
        $this->assertSame([$tortilla->id => 4, $tortitas->id => 2], ShoppingSelection::all());

        Livewire::test('shopping-list-link')->assertSee('2');
    }

    public function test_list_page_combines_the_selected_recipes(): void
    {
        ShoppingSelection::add($this->tortilla());
        ShoppingSelection::add($this->tortitas());

        $this->get('/lista-de-la-compra')
            ->assertOk()
            ->assertSeeInOrder(['Tortilla de patatas', 'Tortitas caseras'])
            ->assertSeeInOrder(['Azúcar', '30 g', 'Huevo', '8 ud', 'Patata', '700 g', 'Sal', 'al gusto', 'Sal', '1 pizca']);
    }

    public function test_diners_can_be_changed_per_recipe_within_limits(): void
    {
        $tortilla = $this->tortilla();
        $tortitas = $this->tortitas();
        ShoppingSelection::add($tortilla);
        ShoppingSelection::add($tortitas);

        $page = Livewire::test('pages::shopping-list')
            ->call('increment', $tortitas->id)
            ->call('increment', $tortitas->id);

        // Tortitas for 4 double their 2 eggs; the tortilla keeps its 6
        $page->assertSeeInOrder(['Huevo', '10 ud']);
        $this->assertSame([$tortilla->id => 4, $tortitas->id => 4], ShoppingSelection::all());

        $page->call('setDiners', $tortilla->id, '8')->assertSeeInOrder(['Huevo', '16 ud', 'Patata', '1400 g']);
        $page->call('setDiners', $tortilla->id, '5000');
        $this->assertSame(100, ShoppingSelection::all()[$tortilla->id]);

        $page->call('setDiners', $tortilla->id, '1')->call('decrement', $tortilla->id);
        $this->assertSame(1, ShoppingSelection::all()[$tortilla->id]);

        // Garbage and unknown recipes are ignored
        $page->call('setDiners', $tortilla->id, '')->call('setDiners', $tortilla->id, 'abc')->call('increment', 999999);
        $this->assertSame([$tortilla->id => 1, $tortitas->id => 4], ShoppingSelection::all());
    }

    public function test_recipes_can_be_removed_and_the_list_cleared(): void
    {
        $tortilla = $this->tortilla();
        $tortitas = $this->tortitas();
        ShoppingSelection::add($tortilla);
        ShoppingSelection::add($tortitas);

        $page = Livewire::test('pages::shopping-list')
            ->call('remove', $tortilla->id)
            ->assertDispatched('shopping-list-updated')
            ->assertDontSee('Patata')
            ->assertSee('Azúcar');

        $this->assertSame([$tortitas->id => 2], ShoppingSelection::all());

        $page->call('clear')->assertSee('Todavía no has añadido ninguna receta a la lista.');

        $this->assertSame([], ShoppingSelection::all());
    }

    public function test_a_deleted_recipe_disappears_from_the_list(): void
    {
        $tortilla = $this->tortilla();
        $tortitas = $this->tortitas();
        ShoppingSelection::add($tortilla);
        ShoppingSelection::add($tortitas);

        $tortilla->delete();

        $this->get('/lista-de-la-compra')->assertOk()->assertDontSee('Patata')->assertSee('Azúcar');
        $this->assertSame([$tortitas->id => 2], ShoppingSelection::all());
    }

    public function test_nothing_is_written_to_the_database(): void
    {
        $tortilla = $this->tortilla();
        ShoppingSelection::add($tortilla);

        Livewire::test('pages::shopping-list')->call('setDiners', $tortilla->id, '9');

        $this->assertSame(4, $tortilla->fresh()->servings);
        $this->assertEquals(700, $tortilla->fresh()->ingredients->firstWhere('name', 'Patata')->pivot->quantity);
    }
}
