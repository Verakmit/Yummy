<?php

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Recetas')] class extends Component
{
    use WithPagination;

    public const MAX_TIMES = [15 => '15 min', 30 => '30 min', 60 => '1 hora', 120 => '2 horas'];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public string $category = '';

    #[Url(as: 'ingrediente')]
    public string $ingredient = '';

    #[Url(as: 'dificultad')]
    public string $difficulty = '';

    #[Url(as: 'tiempo')]
    public string $maxTime = '';

    /** @var list<string> */
    #[Url(as: 'etiquetas')]
    public array $tags = [];

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'ingredient', 'difficulty', 'maxTime', 'tags');
        $this->resetPage();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return trim($this->search) !== ''
            || $this->category !== ''
            || $this->ingredient !== ''
            || $this->difficulty !== ''
            || $this->maxTime !== ''
            || $this->selectedTags() !== [];
    }

    #[Computed]
    public function recipes()
    {
        $difficulty = Difficulty::tryFrom($this->difficulty);

        return Recipe::with('category')
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->search(trim($this->search)))
            ->when($this->category !== '', fn (Builder $query) => $query->whereRelation('category', 'slug', $this->category))
            ->when($this->ingredient !== '', fn (Builder $query) => $query->whereRelation('ingredients', 'slug', $this->ingredient))
            ->when($difficulty, fn (Builder $query) => $query->where('difficulty', $difficulty))
            ->when((int) $this->maxTime > 0, fn (Builder $query) => $query->whereRaw('prep_minutes + cook_minutes <= ?', [(int) $this->maxTime]))
            ->when($this->selectedTags(), function (Builder $query, array $tags) {
                // A recipe must carry every selected tag, not just one of them
                foreach ($tags as $tag) {
                    $query->whereRelation('tags', 'slug', $tag);
                }
            })
            ->latest()
            ->latest('id')
            ->paginate(12);
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function ingredients()
    {
        return Ingredient::has('recipes')->orderBy('name')->get();
    }

    #[Computed]
    public function allTags()
    {
        return Tag::orderBy('name')->get();
    }

    /**
     * The tag slugs from the query string, discarding anything that is not a plain string.
     *
     * @return list<string>
     */
    private function selectedTags(): array
    {
        return array_values(array_filter($this->tags, fn ($tag) => is_string($tag) && $tag !== ''));
    }
};
?>

<div>
    <h1 class="h3 mb-4">Recetas</h1>

    <form class="card card-body mb-4" wire:submit.prevent>
        <div class="row g-3">
            <div class="col-12">
                <label for="search" class="form-label">Buscar</label>
                <input type="search" id="search" class="form-control" placeholder="Tortilla, crema, chocolate…"
                    wire:model.live.debounce.300ms="search">
            </div>

            <div class="col-sm-6 col-lg-3">
                <label for="category" class="form-label">Categoría</label>
                <select id="category" class="form-select" wire:model.live="category">
                    <option value="">Todas</option>
                    @foreach ($this->categories as $option)
                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-sm-6 col-lg-3">
                <label for="ingredient" class="form-label">Ingrediente</label>
                <select id="ingredient" class="form-select" wire:model.live="ingredient">
                    <option value="">Cualquiera</option>
                    @foreach ($this->ingredients as $option)
                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-sm-6 col-lg-3">
                <label for="difficulty" class="form-label">Dificultad</label>
                <select id="difficulty" class="form-select" wire:model.live="difficulty">
                    <option value="">Cualquiera</option>
                    @foreach (App\Enums\Difficulty::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-sm-6 col-lg-3">
                <label for="max-time" class="form-label">Tiempo máximo</label>
                <select id="max-time" class="form-select" wire:model.live="maxTime">
                    <option value="">Sin límite</option>
                    @foreach (self::MAX_TIMES as $minutes => $label)
                        <option value="{{ $minutes }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($this->allTags->isNotEmpty())
                <fieldset class="col-12">
                    <legend class="form-label fs-6">Etiquetas</legend>

                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($this->allTags as $option)
                            <div wire:key="tag-{{ $option->id }}">
                                <input type="checkbox" class="btn-check" id="tag-{{ $option->id }}" value="{{ $option->slug }}"
                                    autocomplete="off" wire:model.live="tags">
                                <label class="btn btn-sm btn-outline-secondary rounded-pill" for="tag-{{ $option->id }}">{{ $option->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-body-secondary mb-0" aria-live="polite">
            {{ $this->recipes->total() }} {{ $this->recipes->total() === 1 ? 'receta' : 'recetas' }}
        </p>

        @if ($this->hasFilters)
            <button type="button" class="btn btn-sm btn-link" wire:click="clearFilters">Limpiar filtros</button>
        @endif
    </div>

    @if ($this->recipes->isEmpty())
        <p class="text-body-secondary mb-0">
            {{ $this->hasFilters ? 'Ninguna receta coincide con estos filtros.' : 'Todavía no hay recetas.' }}
        </p>
    @else
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mb-4">
            @foreach ($this->recipes as $recipe)
                <div class="col" wire:key="recipe-{{ $recipe->id }}">
                    <x-recipe-card :recipe="$recipe" />
                </div>
            @endforeach
        </div>

        {{ $this->recipes->links() }}
    @endif
</div>
