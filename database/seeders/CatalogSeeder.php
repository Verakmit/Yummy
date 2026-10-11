<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    private const CATEGORIES = ['Entrantes', 'Principales', 'Postres', 'Desayunos', 'Sopas y cremas', 'Ensaladas'];

    private const TAGS = ['Vegetariano', 'Vegano', 'Sin gluten', 'Sin lactosa', 'Rápido', 'Económico', 'Tradicional'];

    private const INGREDIENTS = [
        'Aceite de oliva', 'Ajo', 'Arroz', 'Azúcar', 'Calabacín', 'Calabaza', 'Cebolla', 'Champiñones',
        'Chocolate negro', 'Garbanzos', 'Harina', 'Huevo', 'Leche', 'Lentejas', 'Limón', 'Mantequilla',
        'Nata', 'Pan', 'Pasta', 'Patata', 'Pepino', 'Pimentón', 'Pimiento rojo', 'Pimiento verde', 'Pollo',
        'Queso', 'Sal', 'Tomate', 'Vinagre', 'Zanahoria',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        foreach (self::TAGS as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }

        foreach (self::INGREDIENTS as $name) {
            Ingredient::firstOrCreate(['name' => $name]);
        }
    }
}
