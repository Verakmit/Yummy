<?php

use App\Models\Recipe;
use App\Support\Quantity;
use App\Support\ShoppingSelection;
use Livewire\Component;

new class extends Component
{
    public Recipe $recipe;

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe->load(['category', 'tags', 'ingredients', 'steps']);
    }

    /**
     * Quantity and unit of an ingredient line, e.g. "0,5 ud" or "al gusto".
     */
    public function amount(?string $quantity, ?string $unit): string
    {
        return trim(Quantity::format($quantity).' '.$unit);
    }

    public function inShoppingList(): bool
    {
        return ShoppingSelection::has($this->recipe->id);
    }

    public function toggleShoppingList(): void
    {
        ShoppingSelection::toggle($this->recipe);

        $this->dispatch('shopping-list-updated');
    }

    public function delete(): void
    {
        ShoppingSelection::remove($this->recipe->id);
        $this->recipe->delete();

        session()->flash('status', 'Receta borrada.');

        $this->redirectRoute('home', navigate: true);
    }

    public function rendering($view): void
    {
        $view->title($this->recipe->title);
    }
};
?>

<div>
    <nav aria-label="Ruta de navegación">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" wire:navigate>Recetas</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $recipe->title }}</li>
        </ol>
    </nav>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <span class="badge text-bg-light border mb-2">{{ $recipe->category->name }}</span>

            <h1 class="mb-3">{{ $recipe->title }}</h1>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @if ($this->inShoppingList())
                    <button type="button" class="btn btn-sm btn-success" wire:click="toggleShoppingList">En la lista de la compra ✓</button>
                    <a href="{{ route('shopping-list') }}" class="btn btn-sm btn-outline-primary" wire:navigate>Ver la lista</a>
                @else
                    <button type="button" class="btn btn-sm btn-primary" wire:click="toggleShoppingList">Añadir a la lista de la compra</button>
                @endif
                <a href="{{ route('recipes.edit', $recipe) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>Editar</a>
                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete"
                    wire:confirm="¿Borrar «{{ $recipe->title }}»? Esta acción no se puede deshacer.">Borrar</button>
            </div>

            @if ($recipe->description)
                <p class="lead">{{ $recipe->description }}</p>
            @endif

            @if ($recipe->tags->isNotEmpty())
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($recipe->tags as $tag)
                        <span class="badge rounded-pill text-bg-secondary">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @if ($recipe->imageUrl())
                <img src="{{ $recipe->imageUrl() }}" class="img-fluid rounded" alt="">
            @endif
        </div>
    </div>

    <dl class="row row-cols-2 row-cols-md-4 g-3 text-center mb-4">
        <div class="col">
            <div class="bg-body border rounded p-3 h-100">
                <dt class="small text-body-secondary fw-normal">Preparación</dt>
                <dd class="mb-0 fw-semibold">{{ $recipe->prep_minutes }} min</dd>
            </div>
        </div>
        <div class="col">
            <div class="bg-body border rounded p-3 h-100">
                <dt class="small text-body-secondary fw-normal">Cocción</dt>
                <dd class="mb-0 fw-semibold">{{ $recipe->cook_minutes }} min</dd>
            </div>
        </div>
        <div class="col">
            <div class="bg-body border rounded p-3 h-100">
                <dt class="small text-body-secondary fw-normal">Raciones</dt>
                <dd class="mb-0 fw-semibold">{{ $recipe->servings }}</dd>
            </div>
        </div>
        <div class="col">
            <div class="bg-body border rounded p-3 h-100">
                <dt class="small text-body-secondary fw-normal">Dificultad</dt>
                <dd class="mb-0 fw-semibold">{{ $recipe->difficulty->label() }}</dd>
            </div>
        </div>
    </dl>

    <div class="row g-4">
        <div class="col-md-5 col-lg-4">
            <h2 class="h4 mb-3">Ingredientes</h2>

            <ul class="list-group">
                @foreach ($recipe->ingredients as $ingredient)
                    <li class="list-group-item d-flex justify-content-between gap-3" wire:key="ingredient-{{ $ingredient->pivot->id }}">
                        <span>
                            {{ $ingredient->name }}
                            @if ($ingredient->pivot->note)
                                <small class="text-body-secondary">({{ $ingredient->pivot->note }})</small>
                            @endif
                        </span>
                        <span class="text-body-secondary text-nowrap">{{ $this->amount($ingredient->pivot->quantity, $ingredient->pivot->unit) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="col-md-7 col-lg-8">
            <h2 class="h4 mb-3">Preparación</h2>

            <ol class="list-group list-group-numbered">
                @foreach ($recipe->steps as $step)
                    <li class="list-group-item" wire:key="step-{{ $step->id }}">{{ $step->body }}</li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
