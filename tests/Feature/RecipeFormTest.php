<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeFormTest extends TestCase
{
    use DatabaseTruncation;

    // A 1×1 PNG; UploadedFile::fake()->image() needs the GD extension, which is not always installed
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_form_pages_render(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Gazpacho']);

        $this->get('/nueva-receta')->assertOk()->assertSee('Crear receta');
        $this->get('/recetas/gazpacho/editar')->assertOk()->assertSee('Guardar cambios');
        $this->get('/recetas/no-existe/editar')->assertNotFound();
    }

    public function test_a_recipe_can_be_created_with_ingredients_steps_tags_and_photo(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $egg = Ingredient::factory()->create(['name' => 'Huevo']);

        Livewire::test('pages::recipes.form')
            ->set('title', 'Tortilla francesa')
            ->set('description', 'Rápida y sencilla.')
            ->set('categoryId', $category->id)
            ->set('difficulty', 'easy')
            ->set('prepMinutes', 2)
            ->set('cookMinutes', 5)
            ->set('servings', 1)
            ->set('tags', [$tag->id])
            ->set('photo', UploadedFile::fake()->createWithContent('tortilla.png', base64_decode(self::PNG)))
            ->set('ingredients', [
                ['name' => 'huevo', 'quantity' => '2', 'unit' => 'ud', 'note' => ''],
                ['name' => '  aceite   de oliva ', 'quantity' => '0,5', 'unit' => 'cucharada', 'note' => 'virgen extra'],
                ['name' => 'Sal', 'quantity' => '', 'unit' => 'al gusto', 'note' => ''],
            ])
            ->set('steps', [['body' => 'Batir los huevos.'], ['body' => 'Cuajar en la sartén.']])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect('/recetas/tortilla-francesa');

        $recipe = Recipe::sole();

        $this->assertSame('Tortilla francesa', $recipe->title);
        $this->assertSame(Difficulty::Easy, $recipe->difficulty);
        $this->assertSame([$tag->id], $recipe->tags->pluck('id')->all());
        $this->assertSame(['Huevo', 'Aceite de oliva', 'Sal'], $recipe->ingredients->pluck('name')->all());
        $this->assertTrue($recipe->ingredients[0]->is($egg));
        $this->assertEquals(0.5, $recipe->ingredients[1]->pivot->quantity);
        $this->assertSame('virgen extra', $recipe->ingredients[1]->pivot->note);
        $this->assertNull($recipe->ingredients[2]->pivot->quantity);
        $this->assertSame(['Batir los huevos.', 'Cuajar en la sartén.'], $recipe->steps->pluck('body')->all());
        $this->assertNotNull($recipe->image_path);
        Storage::disk('public')->assertExists($recipe->image_path);

        $this->get('/recetas/tortilla-francesa')->assertOk()->assertSee('Receta creada.');
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        Livewire::test('pages::recipes.form')
            ->set('servings', 0)
            ->set('ingredients', [['name' => '', 'quantity' => 'mucho', 'unit' => '', 'note' => '']])
            ->call('save')
            ->assertHasErrors(['title', 'categoryId', 'difficulty', 'servings', 'ingredients.0.name', 'ingredients.0.quantity', 'steps.0.body'])
            ->assertSee('El campo título es obligatorio.')
            ->assertSee('El campo cantidad debe ser un número.');

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_errors_disappear_when_the_field_is_corrected_or_rows_change(): void
    {
        Livewire::test('pages::recipes.form')
            ->call('save')
            ->assertHasErrors(['title', 'categoryId', 'ingredients.0.name', 'steps.0.body'])
            ->set('title', 'Gazpacho')
            ->assertHasNoErrors('title')
            ->assertHasErrors(['categoryId', 'ingredients.0.name'])
            ->set('ingredients.0.name', 'Tomate')
            ->assertHasNoErrors('ingredients.0.name')
            ->call('addStep')
            ->assertHasNoErrors('steps.0.body')
            ->assertHasErrors('categoryId');
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake('public');

        Livewire::test('pages::recipes.form')
            ->set('photo', UploadedFile::fake()->create('receta.pdf', 100, 'application/pdf'))
            ->assertHasErrors('photo')
            ->assertSet('photo', null)
            ->assertSee('El campo foto debe ser una imagen.');
    }

    public function test_rows_can_be_added_moved_and_removed(): void
    {
        Livewire::test('pages::recipes.form')
            ->set('steps', [['body' => 'Uno'], ['body' => 'Dos'], ['body' => 'Tres']])
            ->call('moveStep', 2, -1)
            ->assertSet('steps', [['body' => 'Uno'], ['body' => 'Tres'], ['body' => 'Dos']])
            ->call('moveStep', 0, -1)
            ->assertSet('steps', [['body' => 'Uno'], ['body' => 'Tres'], ['body' => 'Dos']])
            ->call('removeStep', 0)
            ->assertSet('steps', [['body' => 'Tres'], ['body' => 'Dos']])
            ->call('addStep')
            ->assertCount('steps', 3)
            ->call('removeIngredient', 0)
            ->assertCount('ingredients', 1)
            ->call('addIngredient')
            ->assertCount('ingredients', 2);
    }

    public function test_a_recipe_can_be_edited_and_its_photo_replaced(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('recipes/old.png', base64_decode(self::PNG));

        $recipe = Recipe::factory()->create(['title' => 'Gazpacho', 'image_path' => 'recipes/old.png']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(['name' => 'Tomate']), ['position' => 1, 'quantity' => 1.5, 'unit' => 'kg']);
        $recipe->steps()->create(['position' => 1, 'body' => 'Triturar.']);
        $recipe->tags()->attach(Tag::factory()->create());

        Livewire::test('pages::recipes.form', ['recipe' => $recipe])
            ->assertSet('title', 'Gazpacho')
            ->assertSet('ingredients', [['name' => 'Tomate', 'quantity' => '1,5', 'unit' => 'kg', 'note' => '']])
            ->assertSet('steps', [['body' => 'Triturar.']])
            ->set('title', 'Gazpacho andaluz')
            ->set('tags', [])
            ->set('photo', UploadedFile::fake()->createWithContent('new.png', base64_decode(self::PNG)))
            ->set('ingredients', [['name' => 'Pepino', 'quantity' => '1', 'unit' => 'ud', 'note' => '']])
            ->set('steps', [['body' => 'Trocear.'], ['body' => 'Triturar.']])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect('/recetas/gazpacho');

        $recipe->refresh();

        $this->assertSame('Gazpacho andaluz', $recipe->title);
        $this->assertSame(['Pepino'], $recipe->ingredients->pluck('name')->all());
        $this->assertSame(['Trocear.', 'Triturar.'], $recipe->steps->pluck('body')->all());
        $this->assertCount(0, $recipe->tags);
        $this->assertDatabaseCount('recipes', 1);
        $this->assertNotSame('recipes/old.png', $recipe->image_path);
        Storage::disk('public')->assertMissing('recipes/old.png');
        Storage::disk('public')->assertExists($recipe->image_path);
    }

    public function test_the_current_photo_can_be_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('recipes/old.png', base64_decode(self::PNG));

        $recipe = Recipe::factory()->create(['image_path' => 'recipes/old.png']);
        $recipe->ingredients()->attach(Ingredient::factory()->create(), ['position' => 1]);
        $recipe->steps()->create(['position' => 1, 'body' => 'Mezclar.']);

        Livewire::test('pages::recipes.form', ['recipe' => $recipe])
            ->set('removePhoto', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($recipe->fresh()->image_path);
        Storage::disk('public')->assertMissing('recipes/old.png');
    }

    public function test_a_recipe_can_be_deleted_from_its_page_along_with_its_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('recipes/old.png', base64_decode(self::PNG));

        $recipe = Recipe::factory()->create(['image_path' => 'recipes/old.png']);
        $recipe->steps()->create(['position' => 1, 'body' => 'Mezclar.']);

        Livewire::test('pages::recipes.show', ['recipe' => $recipe])
            ->call('delete')
            ->assertRedirect('/');

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('steps', 0);
        Storage::disk('public')->assertMissing('recipes/old.png');

        $this->get('/')->assertSee('Receta borrada.');
    }
}
