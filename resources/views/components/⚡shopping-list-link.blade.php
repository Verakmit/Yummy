<?php

use App\Support\ShoppingSelection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('shopping-list-updated')]
    public function refresh(): void
    {
        //
    }

    #[Computed]
    public function count(): int
    {
        return ShoppingSelection::count();
    }
};
?>

<li class="nav-item">
    <a class="nav-link" href="{{ route('shopping-list') }}" wire:navigate>
        Lista de la compra
        @if ($this->count > 0)
            <span class="badge rounded-pill text-bg-primary">{{ $this->count }}<span class="visually-hidden"> {{ $this->count === 1 ? 'receta' : 'recetas' }}</span></span>
        @endif
    </a>
</li>
