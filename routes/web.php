<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/nueva-receta', 'pages::recipes.form')->name('recipes.create');
Route::livewire('/lista-de-la-compra', 'pages::shopping-list')->name('shopping-list');
Route::livewire('/recetas/{recipe}', 'pages::recipes.show')->name('recipes.show');
Route::livewire('/recetas/{recipe}/editar', 'pages::recipes.form')->name('recipes.edit');
