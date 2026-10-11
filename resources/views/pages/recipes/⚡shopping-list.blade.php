<?php

use App\Models\Recipe;
use App\Support\ShoppingList;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public const MAX_DINERS = 100;

    public Recipe $recipe;

    #[Url(as: 'comensales')]
    public $diners = null;

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe->load('ingredients');
        $this->diners = $this->dinerCount();
    }

    public function increment(): void
    {
        $this->diners = min(self::MAX_DINERS, $this->dinerCount() + 1);
    }

    public function decrement(): void
    {
        $this->diners = max(1, $this->dinerCount() - 1);
    }

    /**
     * The number of diners as a usable integer, whatever was typed or came in the query string.
     */
    public function dinerCount(): int
    {
        if (! is_numeric($this->diners)) {
            return $this->recipe->servings;
        }

        return max(1, min(self::MAX_DINERS, (int) $this->diners));
    }

    #[Computed]
    public function items()
    {
        return ShoppingList::for($this->recipe, $this->dinerCount());
    }

    /**
     * The list as plain text, for the copy button.
     */
    #[Computed]
    public function asText(): string
    {
        $diners = $this->dinerCount();

        return $this->items
            ->map(fn (array $item) => '- '.$item['name'].($item['amount'] !== '' ? ': '.$item['amount'] : ''))
            ->prepend(sprintf('Lista de la compra: %s (%d %s)', $this->recipe->title, $diners, $diners === 1 ? 'comensal' : 'comensales'))
            ->implode("\n");
    }

    public function rendering($view): void
    {
        $view->title('Lista de la compra: '.$this->recipe->title);
    }
};
?>

<div>
    <nav aria-label="Ruta de navegación" class="d-print-none">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" wire:navigate>Recetas</a></li>
            <li class="breadcrumb-item"><a href="{{ route('recipes.show', $recipe) }}" wire:navigate>{{ $recipe->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Lista de la compra</li>
        </ol>
    </nav>

    <h1 class="h3 mb-1">Lista de la compra</h1>
    <p class="text-body-secondary mb-4">
        {{ $recipe->title }} · {{ $this->dinerCount() }} {{ $this->dinerCount() === 1 ? 'comensal' : 'comensales' }}
    </p>

    <div class="card card-body mb-4 d-print-none">
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label for="diners" class="form-label">Comensales</label>

                <div class="input-group shopping-list-diners">
                    <button type="button" class="btn btn-outline-secondary" aria-label="Un comensal menos"
                        wire:click="decrement" @disabled($this->dinerCount() <= 1)>−</button>
                    <input type="number" id="diners" min="1" max="{{ self::MAX_DINERS }}" class="form-control text-center"
                        wire:model.live.debounce.300ms="diners">
                    <button type="button" class="btn btn-outline-secondary" aria-label="Un comensal más"
                        wire:click="increment" @disabled($this->dinerCount() >= self::MAX_DINERS)>+</button>
                </div>
            </div>

            <div class="col-auto text-body-secondary small pb-2">
                La receta original es para {{ $recipe->servings }} {{ $recipe->servings === 1 ? 'ración' : 'raciones' }}.
            </div>

            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2"
                x-data="{ copied: false, copy() { navigator.clipboard.writeText($refs.text.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                <textarea class="d-none" x-ref="text" readonly>{{ $this->asText }}</textarea>
                <button type="button" class="btn btn-outline-secondary" x-on:click="copy()">
                    <span x-show="! copied">Copiar</span>
                    <span x-show="copied" x-cloak>Copiada</span>
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
            </div>
        </div>
    </div>

    @if ($this->items->isEmpty())
        <p class="text-body-secondary mb-0">Esta receta no tiene ingredientes.</p>
    @else
        <ul class="list-group shopping-list">
            @foreach ($this->items as $item)
                <li class="list-group-item" wire:key="item-{{ $item['key'] }}" x-data="{ done: false }">
                    <label class="d-flex align-items-center gap-3 mb-0">
                        <input type="checkbox" class="form-check-input mt-0 flex-shrink-0" x-model="done">
                        <span class="flex-grow-1" x-bind:class="done && 'text-decoration-line-through text-body-secondary'">{{ $item['name'] }}</span>
                        <span class="text-body-secondary text-nowrap">{{ $item['amount'] }}</span>
                    </label>
                </li>
            @endforeach
        </ul>

        <p class="form-text d-print-none">La lista no se guarda: las marcas se pierden al salir de la página.</p>
    @endif
</div>
