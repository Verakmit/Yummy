@props(['recipe', 'inShoppingList' => false])

<div class="card h-100 shadow-sm">
    @if ($recipe->imageUrl())
        <img src="{{ $recipe->imageUrl() }}" class="card-img-top recipe-card-image" alt="">
    @else
        <div class="recipe-card-image bg-secondary-subtle d-flex align-items-center justify-content-center text-secondary">
            Sin foto
        </div>
    @endif

    <div class="card-body d-flex flex-column">
        <span class="badge text-bg-light border align-self-start mb-2">{{ $recipe->category->name }}</span>

        <h2 class="card-title h5">
            <a href="{{ route('recipes.show', $recipe) }}" class="stretched-link link-body-emphasis text-decoration-none" wire:navigate>
                {{ $recipe->title }}
            </a>
        </h2>

        <p class="card-text text-body-secondary small mt-auto mb-3">
            {{ $recipe->totalTimeLabel() }} · {{ $recipe->difficulty->label() }} · {{ $recipe->servings }} {{ $recipe->servings === 1 ? 'ración' : 'raciones' }}
        </p>

        {{-- Sits above the stretched link so it can be clicked on its own --}}
        <button type="button" wire:click="toggleShoppingList({{ $recipe->id }})"
            aria-label="{{ $inShoppingList ? 'Quitar' : 'Añadir' }} {{ $recipe->title }} {{ $inShoppingList ? 'de' : 'a' }} la lista de la compra"
            @class(['btn btn-sm align-self-start position-relative z-2', 'btn-success' => $inShoppingList, 'btn-outline-primary' => ! $inShoppingList])>
            {{ $inShoppingList ? 'En la lista ✓' : 'Añadir a la lista' }}
        </button>
    </div>
</div>
