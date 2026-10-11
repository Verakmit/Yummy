<?php

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    private const EMPTY_INGREDIENT = ['name' => '', 'quantity' => '', 'unit' => '', 'note' => ''];

    private const EMPTY_STEP = ['body' => ''];

    public ?Recipe $editing = null;

    public string $title = '';

    public string $description = '';

    public $categoryId = '';

    public string $difficulty = '';

    public $prepMinutes = 0;

    public $cookMinutes = 0;

    public $servings = 4;

    public array $tags = [];

    public array $ingredients = [self::EMPTY_INGREDIENT];

    public array $steps = [self::EMPTY_STEP];

    public $photo = null;

    public bool $removePhoto = false;

    public function mount(?Recipe $recipe = null): void
    {
        // On the create route the container hands us an empty model instead of null
        if (! $recipe?->exists) {
            return;
        }

        $this->editing = $recipe;
        $this->title = $recipe->title;
        $this->description = (string) $recipe->description;
        $this->categoryId = $recipe->category_id;
        $this->difficulty = $recipe->difficulty->value;
        $this->prepMinutes = $recipe->prep_minutes;
        $this->cookMinutes = $recipe->cook_minutes;
        $this->servings = $recipe->servings;
        $this->tags = $recipe->tags->pluck('id')->all();

        $this->ingredients = $recipe->ingredients->map(fn (Ingredient $ingredient) => [
            'name' => $ingredient->name,
            'quantity' => Quantity::format($ingredient->pivot->quantity),
            'unit' => (string) $ingredient->pivot->unit,
            'note' => (string) $ingredient->pivot->note,
        ])->all() ?: [self::EMPTY_INGREDIENT];

        $this->steps = $recipe->steps->map(fn ($step) => ['body' => $step->body])->all() ?: [self::EMPTY_STEP];
    }

    /**
     * Drop a field's error once the user has changed it, rather than keeping it until the next save.
     */
    public function updated(string $property): void
    {
        if ($property !== 'photo') {
            $this->resetValidation($property);
        }
    }

    /**
     * Check the photo as soon as it is uploaded instead of waiting for the form to be saved.
     */
    public function updatedPhoto(): void
    {
        try {
            $this->validateOnly('photo');
        } catch (ValidationException $e) {
            $this->reset('photo');

            throw $e;
        }
    }

    public function addIngredient(): void
    {
        $this->ingredients[] = self::EMPTY_INGREDIENT;
        $this->clearRowErrors('ingredients');
    }

    public function removeIngredient(int $index): void
    {
        $this->ingredients = $this->withoutRow($this->ingredients, $index) ?: [self::EMPTY_INGREDIENT];
        $this->clearRowErrors('ingredients');
    }

    public function moveIngredient(int $index, int $offset): void
    {
        $this->ingredients = $this->withRowMoved($this->ingredients, $index, $offset);
        $this->clearRowErrors('ingredients');
    }

    public function addStep(): void
    {
        $this->steps[] = self::EMPTY_STEP;
        $this->clearRowErrors('steps');
    }

    public function removeStep(int $index): void
    {
        $this->steps = $this->withoutRow($this->steps, $index) ?: [self::EMPTY_STEP];
        $this->clearRowErrors('steps');
    }

    public function moveStep(int $index, int $offset): void
    {
        $this->steps = $this->withRowMoved($this->steps, $index, $offset);
        $this->clearRowErrors('steps');
    }

    public function save(): void
    {
        foreach ($this->ingredients as $index => $row) {
            if (is_array($row)) {
                $this->ingredients[$index]['quantity'] = Quantity::normalize($row['quantity'] ?? null);
            }
        }

        $data = $this->validate();
        $previousImage = $this->editing?->image_path;

        $imagePath = match (true) {
            $this->photo !== null => $this->photo->store('recipes', 'public'),
            $this->removePhoto => null,
            default => $previousImage,
        };

        $recipe = DB::transaction(function () use ($data, $imagePath) {
            $recipe = $this->editing ?? new Recipe;

            $recipe->fill([
                'category_id' => $data['categoryId'],
                'title' => $data['title'],
                'description' => filled($data['description']) ? $data['description'] : null,
                'prep_minutes' => $data['prepMinutes'],
                'cook_minutes' => $data['cookMinutes'],
                'servings' => $data['servings'],
                'difficulty' => $data['difficulty'],
                'image_path' => $imagePath,
            ])->save();

            $recipe->tags()->sync($data['tags']);

            $recipe->ingredients()->detach();

            foreach (array_values($data['ingredients']) as $position => $row) {
                $ingredient = Ingredient::firstOrCreate(['name' => Str::ucfirst(Str::squish($row['name']))]);

                $recipe->ingredients()->attach($ingredient, [
                    'quantity' => filled($row['quantity'] ?? null) ? $row['quantity'] : null,
                    'unit' => filled($row['unit'] ?? null) ? trim($row['unit']) : null,
                    'note' => filled($row['note'] ?? null) ? trim($row['note']) : null,
                    'position' => $position + 1,
                ]);
            }

            $recipe->steps()->delete();

            foreach (array_values($data['steps']) as $position => $row) {
                $recipe->steps()->create(['position' => $position + 1, 'body' => trim($row['body'])]);
            }

            return $recipe;
        });

        if ($previousImage && $previousImage !== $imagePath) {
            Storage::disk('public')->delete($previousImage);
        }

        session()->flash('status', $this->editing ? 'Receta actualizada.' : 'Receta creada.');

        $this->redirectRoute('recipes.show', $recipe, navigate: true);
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function allTags()
    {
        return Tag::orderBy('name')->get();
    }

    #[Computed]
    public function ingredientNames()
    {
        return Ingredient::orderBy('name')->pluck('name');
    }

    public function rendering($view): void
    {
        $view->title($this->editing ? 'Editar '.$this->editing->title : 'Nueva receta');
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'categoryId' => ['required', Rule::exists('categories', 'id')],
            'difficulty' => ['required', Rule::enum(Difficulty::class)],
            'prepMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'cookMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'servings' => ['required', 'integer', 'min:1', 'max:100'],
            'tags' => ['array'],
            'tags.*' => [Rule::exists('tags', 'id')],
            'photo' => ['nullable', 'image', 'max:4096'],
            'removePhoto' => ['boolean'],
            'ingredients' => ['required', 'array', 'min:1', 'max:50'],
            'ingredients.*' => ['array'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
            'ingredients.*.note' => ['nullable', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*' => ['array'],
            'steps.*.body' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'categoryId' => 'categoría',
            'difficulty' => 'dificultad',
            'prepMinutes' => 'preparación',
            'cookMinutes' => 'cocción',
            'servings' => 'raciones',
            'tags' => 'etiquetas',
            'tags.*' => 'etiqueta',
            'photo' => 'foto',
            'ingredients' => 'ingredientes',
            'ingredients.*.name' => 'ingrediente',
            'ingredients.*.quantity' => 'cantidad',
            'ingredients.*.unit' => 'unidad',
            'ingredients.*.note' => 'nota',
            'steps' => 'pasos',
            'steps.*.body' => 'paso',
        ];
    }

    /**
     * Row errors are keyed by position, so they stop matching once rows are added, removed or moved.
     */
    private function clearRowErrors(string $list): void
    {
        $this->resetValidation(
            array_filter(array_keys($this->getErrorBag()->toArray()), fn (string $key) => $key === $list || str_starts_with($key, $list.'.'))
        );
    }

    private function withoutRow(array $rows, int $index): array
    {
        unset($rows[$index]);

        return array_values($rows);
    }

    private function withRowMoved(array $rows, int $index, int $offset): array
    {
        $target = $index + $offset;

        if (isset($rows[$index], $rows[$target])) {
            [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];
        }

        return $rows;
    }
};
?>

<div>
    <nav aria-label="Ruta de navegación">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" wire:navigate>Recetas</a></li>
            @if ($editing)
                <li class="breadcrumb-item"><a href="{{ route('recipes.show', $editing) }}" wire:navigate>{{ $editing->title }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Editar</li>
            @else
                <li class="breadcrumb-item active" aria-current="page">Nueva receta</li>
            @endif
        </ol>
    </nav>

    <h1 class="h3 mb-4">{{ $editing ? 'Editar receta' : 'Nueva receta' }}</h1>

    <form wire:submit="save" novalidate>
        <div class="card card-body mb-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="title" class="form-label">Título</label>
                    <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" wire:model="title">
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción <span class="text-body-secondary">(opcional)</span></label>
                    <textarea id="description" rows="3" class="form-control @error('description') is-invalid @enderror" wire:model="description"></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-sm-6">
                    <label for="category" class="form-label">Categoría</label>
                    <select id="category" class="form-select @error('categoryId') is-invalid @enderror" wire:model="categoryId">
                        <option value="">Elige una categoría</option>
                        @foreach ($this->categories as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-sm-6">
                    <label for="difficulty" class="form-label">Dificultad</label>
                    <select id="difficulty" class="form-select @error('difficulty') is-invalid @enderror" wire:model="difficulty">
                        <option value="">Elige una dificultad</option>
                        @foreach (App\Enums\Difficulty::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('difficulty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-sm-4">
                    <label for="prep-minutes" class="form-label">Preparación (min)</label>
                    <input type="number" id="prep-minutes" min="0" class="form-control @error('prepMinutes') is-invalid @enderror" wire:model="prepMinutes">
                    @error('prepMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-sm-4">
                    <label for="cook-minutes" class="form-label">Cocción (min)</label>
                    <input type="number" id="cook-minutes" min="0" class="form-control @error('cookMinutes') is-invalid @enderror" wire:model="cookMinutes">
                    @error('cookMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-sm-4">
                    <label for="servings" class="form-label">Raciones</label>
                    <input type="number" id="servings" min="1" class="form-control @error('servings') is-invalid @enderror" wire:model="servings">
                    @error('servings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if ($this->allTags->isNotEmpty())
                    <fieldset class="col-12">
                        <legend class="form-label fs-6">Etiquetas</legend>

                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($this->allTags as $option)
                                <div wire:key="tag-{{ $option->id }}">
                                    <input type="checkbox" class="btn-check" id="tag-{{ $option->id }}" value="{{ $option->id }}"
                                        autocomplete="off" wire:model="tags">
                                    <label class="btn btn-sm btn-outline-secondary rounded-pill" for="tag-{{ $option->id }}">{{ $option->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </div>
        </div>

        <div class="card card-body mb-4">
            <h2 class="h5 mb-3">Foto</h2>

            @if ($photo?->isPreviewable())
                <img src="{{ $photo->temporaryUrl() }}" class="img-thumbnail recipe-form-photo mb-3" alt="Vista previa de la foto nueva">
            @elseif ($editing?->imageUrl() && ! $removePhoto)
                <img src="{{ $editing->imageUrl() }}" class="img-thumbnail recipe-form-photo mb-3" alt="Foto actual">
            @endif

            <input type="file" id="photo" accept="image/*" aria-label="Foto de la receta"
                class="form-control @error('photo') is-invalid @enderror" wire:model="photo">
            @error('photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text" wire:loading wire:target="photo">Subiendo la foto…</div>
            <div class="form-text">Opcional. Una imagen de 4 MB como máximo.</div>

            @if ($editing?->image_path)
                <div class="form-check mt-3">
                    <input type="checkbox" id="remove-photo" class="form-check-input" wire:model.live="removePhoto">
                    <label for="remove-photo" class="form-check-label">Quitar la foto actual</label>
                </div>
            @endif
        </div>

        <div class="card card-body mb-4">
            <h2 class="h5 mb-3">Ingredientes</h2>

            @error('ingredients') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

            <datalist id="ingredient-names">
                @foreach ($this->ingredientNames as $name)
                    <option value="{{ $name }}"></option>
                @endforeach
            </datalist>

            @foreach ($ingredients as $index => $row)
                <div class="row g-2 align-items-start mb-3" wire:key="ingredient-{{ $index }}">
                    <div class="col-12 col-md-4">
                        <input type="text" list="ingredient-names" placeholder="Ingrediente" aria-label="Ingrediente {{ $index + 1 }}"
                            class="form-control @error("ingredients.$index.name") is-invalid @enderror" wire:model="ingredients.{{ $index }}.name">
                        @error("ingredients.$index.name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-4 col-md-2">
                        <input type="text" inputmode="decimal" placeholder="Cantidad" aria-label="Cantidad del ingrediente {{ $index + 1 }}"
                            class="form-control @error("ingredients.$index.quantity") is-invalid @enderror" wire:model="ingredients.{{ $index }}.quantity">
                        @error("ingredients.$index.quantity") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-8 col-md-2">
                        <input type="text" placeholder="Unidad" aria-label="Unidad del ingrediente {{ $index + 1 }}"
                            class="form-control @error("ingredients.$index.unit") is-invalid @enderror" wire:model="ingredients.{{ $index }}.unit">
                        @error("ingredients.$index.unit") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-2">
                        <input type="text" placeholder="Nota" aria-label="Nota del ingrediente {{ $index + 1 }}"
                            class="form-control @error("ingredients.$index.note") is-invalid @enderror" wire:model="ingredients.{{ $index }}.note">
                        @error("ingredients.$index.note") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-2">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-secondary" aria-label="Subir ingrediente {{ $index + 1 }}"
                                wire:click="moveIngredient({{ $index }}, -1)" @disabled($loop->first)>↑</button>
                            <button type="button" class="btn btn-outline-secondary" aria-label="Bajar ingrediente {{ $index + 1 }}"
                                wire:click="moveIngredient({{ $index }}, 1)" @disabled($loop->last)>↓</button>
                            <button type="button" class="btn btn-outline-danger" aria-label="Quitar ingrediente {{ $index + 1 }}"
                                wire:click="removeIngredient({{ $index }})">✕</button>
                        </div>
                    </div>
                </div>
            @endforeach

            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addIngredient">Añadir ingrediente</button>
            </div>
        </div>

        <div class="card card-body mb-4">
            <h2 class="h5 mb-3">Preparación</h2>

            @error('steps') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

            @foreach ($steps as $index => $row)
                <div class="row g-2 align-items-start mb-3" wire:key="step-{{ $index }}">
                    <div class="col-auto pt-2 fw-semibold">{{ $index + 1 }}.</div>

                    <div class="col">
                        <textarea rows="2" placeholder="Describe este paso" aria-label="Paso {{ $index + 1 }}"
                            class="form-control @error("steps.$index.body") is-invalid @enderror" wire:model="steps.{{ $index }}.body"></textarea>
                        @error("steps.$index.body") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-2">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-secondary" aria-label="Subir paso {{ $index + 1 }}"
                                wire:click="moveStep({{ $index }}, -1)" @disabled($loop->first)>↑</button>
                            <button type="button" class="btn btn-outline-secondary" aria-label="Bajar paso {{ $index + 1 }}"
                                wire:click="moveStep({{ $index }}, 1)" @disabled($loop->last)>↓</button>
                            <button type="button" class="btn btn-outline-danger" aria-label="Quitar paso {{ $index + 1 }}"
                                wire:click="removeStep({{ $index }})">✕</button>
                        </div>
                    </div>
                </div>
            @endforeach

            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addStep">Añadir paso</button>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save, photo">
                {{ $editing ? 'Guardar cambios' : 'Crear receta' }}
            </button>
            <a href="{{ $editing ? route('recipes.show', $editing) : route('home') }}" class="btn btn-outline-secondary" wire:navigate>Cancelar</a>
        </div>
    </form>
</div>
