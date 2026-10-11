<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="bg-body-tertiary">
        <nav class="navbar navbar-expand-md bg-body border-bottom d-print-none">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="{{ url('/') }}" wire:navigate>{{ config('app.name') }}</a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                    aria-controls="main-nav" aria-expanded="false" aria-label="Abrir menú">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="main-nav">
                    <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('/') }}" wire:navigate>Recetas</a>
                        </li>
                        <livewire:shopping-list-link />
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm" href="{{ route('recipes.create') }}" wire:navigate>Nueva receta</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="container py-4">
            @session('status')
                <div class="alert alert-success" role="status">{{ $value }}</div>
            @endsession

            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
