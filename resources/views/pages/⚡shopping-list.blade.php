<?php

use App\Support\ShoppingList;
use App\Support\ShoppingSelection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lista de la compra')] class extends Component
{
    public function increment(int $recipeId): void
    {
        $this->setDiners($recipeId, (ShoppingSelection::all()[$recipeId] ?? 0) + 1);
    }

    public function decrement(int $recipeId): void
    {
        $this->setDiners($recipeId, (ShoppingSelection::all()[$recipeId] ?? 2) - 1);
    }

    public function setDiners(int $recipeId, mixed $diners): void
    {
        // An empty or non-numeric box leaves the previous value in place
        if (is_numeric($diners)) {
            ShoppingSelection::setDiners($recipeId, (int) $diners);
        }
    }

    public function remove(int $recipeId): void
    {
        ShoppingSelection::remove($recipeId);

        $this->dispatch('shopping-list-updated');
    }

    public function clear(): void
    {
        ShoppingSelection::clear();

        $this->dispatch('shopping-list-updated');
    }

    #[Computed]
    public function entries()
    {
        return ShoppingSelection::entries();
    }

    #[Computed]
    public function items()
    {
        return ShoppingList::combine($this->entries);
    }

    /**
     * The list as plain text, for the copy button.
     */
    #[Computed]
    public function asText(): string
    {
        $recipes = $this->entries->map(fn (array $entry) => sprintf('%s (%d)', $entry['recipe']->title, $entry['diners']));

        return $this->items
            ->map(fn (array $item) => '- '.$item['name'].($item['amount'] !== '' ? ': '.$item['amount'] : ''))
            ->prepend('Lista de la compra: '.$recipes->implode(', '))
            ->implode("\n");
    }
};
?>

<div>
    <h1 class="h3 mb-4">Lista de la compra</h1>

    @if ($this->entries->isEmpty())
        <p class="text-body-secondary">Todavía no has añadido ninguna receta a la lista.</p>
        <a href="{{ route('home') }}" class="btn btn-primary" wire:navigate>Ver recetas</a>
    @else
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Recetas</h2>
                    <button type="button" class="btn btn-sm btn-link d-print-none" wire:click="clear"
                        wire:confirm="¿Vaciar la lista de la compra?">Vaciar lista</button>
                </div>

                <ul class="list-group">
                    @foreach ($this->entries as $entry)
                        @php($recipe = $entry['recipe'])

                        <li class="list-group-item" wire:key="recipe-{{ $recipe->id }}">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <a href="{{ route('recipes.show', $recipe) }}" class="fw-semibold link-body-emphasis text-decoration-none" wire:navigate>{{ $recipe->title }}</a>
                                <button type="button" class="btn-close d-print-none" aria-label="Quitar {{ $recipe->title }} de la lista"
                                    wire:click="remove({{ $recipe->id }})"></button>
                            </div>

                            <div class="d-flex align-items-center gap-2 d-print-none">
                                <div class="input-group input-group-sm shopping-list-diners">
                                    <button type="button" class="btn btn-outline-secondary" aria-label="Un comensal menos para {{ $recipe->title }}"
                                        wire:click="decrement({{ $recipe->id }})" @disabled($entry['diners'] <= 1)>−</button>
                                    <input type="number" min="1" max="{{ App\Support\ShoppingSelection::MAX_DINERS }}" class="form-control text-center"
                                        aria-label="Comensales para {{ $recipe->title }}" value="{{ $entry['diners'] }}"
                                        wire:key="diners-{{ $recipe->id }}-{{ $entry['diners'] }}"
                                        wire:change="setDiners({{ $recipe->id }}, $event.target.value)">
                                    <button type="button" class="btn btn-outline-secondary" aria-label="Un comensal más para {{ $recipe->title }}"
                                        wire:click="increment({{ $recipe->id }})" @disabled($entry['diners'] >= App\Support\ShoppingSelection::MAX_DINERS)>+</button>
                                </div>
                                <span class="small text-body-secondary">comensales</span>
                            </div>

                            <div class="small text-body-secondary d-none d-print-block">
                                {{ $entry['diners'] }} {{ $entry['diners'] === 1 ? 'comensal' : 'comensales' }}
                            </div>
                        </li>
                    @endforeach
                </ul>

                <p class="form-text d-print-none">
                    La lista es temporal: se borra tras {{ config('session.lifetime') }} minutos sin usar la web.
                </p>
            </div>

            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Qué comprar</h2>

                    <div class="d-flex gap-2 d-print-none"
                        x-data="{ copied: false, copy() { navigator.clipboard.writeText($refs.text.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                        <textarea class="d-none" x-ref="text" readonly>{{ $this->asText }}</textarea>
                        <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="copy()">
                            <span x-show="! copied">Copiar</span>
                            <span x-show="copied" x-cloak>Copiada</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Imprimir</button>
                    </div>
                </div>

                @if ($this->items->isEmpty())
                    <p class="text-body-secondary mb-0">Estas recetas no tienen ingredientes.</p>
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
                @endif
            </div>
        </div>
    @endif
</div>
