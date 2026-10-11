<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    private const GENERATED_RECIPES = 22;

    /**
     * Dish names used to build the generated recipe titles, by category.
     */
    private const DISHES = [
        'Entrantes' => ['Tostas', 'Croquetas', 'Empanadillas'],
        'Principales' => ['Guiso', 'Salteado', 'Asado', 'Estofado'],
        'Postres' => ['Tarta', 'Bizcocho', 'Flan'],
        'Desayunos' => ['Tortitas', 'Tostadas', 'Revuelto'],
        'Sopas y cremas' => ['Crema', 'Sopa', 'Potaje'],
        'Ensaladas' => ['Ensalada', 'Ensalada templada'],
    ];

    public function run(): void
    {
        foreach ($this->recipes() as $data) {
            $recipe = Recipe::create([
                'category_id' => Category::where('name', $data['category'])->value('id'),
                'title' => $data['title'],
                'description' => $data['description'],
                'prep_minutes' => $data['prep'],
                'cook_minutes' => $data['cook'],
                'servings' => $data['servings'],
                'difficulty' => $data['difficulty'],
            ]);

            $recipe->tags()->attach(Tag::whereIn('name', $data['tags'])->pluck('id'));

            foreach ($data['ingredients'] as $position => [$name, $quantity, $unit]) {
                $recipe->ingredients()->attach(Ingredient::firstOrCreate(['name' => $name]), [
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'position' => $position + 1,
                ]);
            }

            foreach ($data['steps'] as $position => $body) {
                $recipe->steps()->create(['position' => $position + 1, 'body' => $body]);
            }
        }

        $this->generateRecipes();
    }

    /**
     * Fill the catalogue with made-up recipes so listing, pagination and filters have enough data.
     */
    private function generateRecipes(): void
    {
        $categories = Category::all()->keyBy('name');
        $ingredients = Ingredient::all();
        $tags = Tag::all();

        for ($i = 0; $i < self::GENERATED_RECIPES; $i++) {
            $categoryName = fake()->randomElement(array_keys(self::DISHES));
            $picked = $ingredients->random(fake()->numberBetween(4, 7))->values();

            $recipe = Recipe::factory()->create([
                'category_id' => $categories[$categoryName]->id,
                'title' => sprintf(
                    '%s de %s con %s',
                    fake()->randomElement(self::DISHES[$categoryName]),
                    mb_strtolower($picked[0]->name),
                    mb_strtolower($picked[1]->name),
                ),
            ]);

            $recipe->tags()->attach($tags->random(fake()->numberBetween(0, 3))->pluck('id'));

            foreach ($picked as $position => $ingredient) {
                $recipe->ingredients()->attach($ingredient, [
                    'quantity' => fake()->randomElement([1, 2, 3, 50, 100, 200, 250, 500]),
                    'unit' => fake()->randomElement(['g', 'ml', 'ud', 'cucharadas']),
                    'position' => $position + 1,
                ]);
            }

            foreach (range(1, fake()->numberBetween(3, 6)) as $position) {
                $recipe->steps()->create(['position' => $position, 'body' => fake()->sentence(14)]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipes(): array
    {
        return [
            [
                'title' => 'Tortilla de patatas',
                'category' => 'Principales',
                'difficulty' => Difficulty::Medium,
                'prep' => 15, 'cook' => 30, 'servings' => 4,
                'description' => 'La tortilla española de toda la vida, jugosa por dentro y con cebolla.',
                'tags' => ['Vegetariano', 'Sin gluten', 'Tradicional', 'Económico'],
                'ingredients' => [
                    ['Patata', 700, 'g'], ['Huevo', 6, 'ud'], ['Cebolla', 1, 'ud'],
                    ['Aceite de oliva', 300, 'ml'], ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Pela las patatas y la cebolla y córtalas en láminas finas.',
                    'Fríelas a fuego medio en abundante aceite hasta que estén tiernas, unos 20 minutos, y escúrrelas.',
                    'Bate los huevos con sal, mezcla con la patata y la cebolla y deja reposar 5 minutos.',
                    'Cuaja la mezcla en una sartén con un poco de aceite, dale la vuelta con un plato y termina por el otro lado.',
                ],
            ],
            [
                'title' => 'Gazpacho andaluz',
                'category' => 'Sopas y cremas',
                'difficulty' => Difficulty::Easy,
                'prep' => 15, 'cook' => 0, 'servings' => 4,
                'description' => 'Sopa fría de tomate, perfecta para los días de calor.',
                'tags' => ['Vegano', 'Sin lactosa', 'Rápido', 'Tradicional'],
                'ingredients' => [
                    ['Tomate', 1, 'kg'], ['Pepino', 1, 'ud'], ['Pimiento verde', 1, 'ud'], ['Ajo', 1, 'diente'],
                    ['Pan', 50, 'g'], ['Aceite de oliva', 50, 'ml'], ['Vinagre', 2, 'cucharadas'], ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Lava y trocea el tomate, el pepino pelado y el pimiento.',
                    'Tritura las verduras con el ajo y el pan remojado hasta obtener una crema fina.',
                    'Añade el aceite, el vinagre y la sal, y vuelve a triturar para emulsionar.',
                    'Cuela si quieres una textura más fina y enfría al menos dos horas antes de servir.',
                ],
            ],
            [
                'title' => 'Lentejas estofadas',
                'category' => 'Principales',
                'difficulty' => Difficulty::Easy,
                'prep' => 10, 'cook' => 45, 'servings' => 4,
                'description' => 'Un plato de cuchara sencillo y reconfortante, solo con verduras.',
                'tags' => ['Vegano', 'Sin gluten', 'Sin lactosa', 'Económico', 'Tradicional'],
                'ingredients' => [
                    ['Lentejas', 320, 'g'], ['Zanahoria', 2, 'ud'], ['Cebolla', 1, 'ud'], ['Ajo', 2, 'dientes'],
                    ['Patata', 1, 'ud'], ['Pimentón', 1, 'cucharadita'], ['Aceite de oliva', 3, 'cucharadas'],
                    ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Sofríe la cebolla y el ajo picados en el aceite hasta que estén transparentes.',
                    'Añade la zanahoria en rodajas, la patata en trozos y el pimentón, y remueve unos segundos.',
                    'Incorpora las lentejas, cubre con agua fría y lleva a ebullición.',
                    'Cuece a fuego suave unos 40 minutos, hasta que estén tiernas, y rectifica de sal.',
                ],
            ],
            [
                'title' => 'Crema de calabaza',
                'category' => 'Sopas y cremas',
                'difficulty' => Difficulty::Easy,
                'prep' => 10, 'cook' => 25, 'servings' => 4,
                'description' => 'Crema suave y ligeramente dulce, lista en poco más de media hora.',
                'tags' => ['Vegetariano', 'Sin gluten'],
                'ingredients' => [
                    ['Calabaza', 800, 'g'], ['Patata', 1, 'ud'], ['Cebolla', 1, 'ud'], ['Nata', 100, 'ml'],
                    ['Aceite de oliva', 2, 'cucharadas'], ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Pocha la cebolla picada en el aceite durante 5 minutos.',
                    'Añade la calabaza y la patata troceadas, cubre con agua y cuece 20 minutos.',
                    'Tritura con la nata hasta que quede una crema fina y ajusta de sal.',
                ],
            ],
            [
                'title' => 'Ensalada de garbanzos',
                'category' => 'Ensaladas',
                'difficulty' => Difficulty::Easy,
                'prep' => 10, 'cook' => 0, 'servings' => 2,
                'description' => 'Ensalada completa y fresca que se prepara en diez minutos.',
                'tags' => ['Vegano', 'Sin gluten', 'Sin lactosa', 'Rápido', 'Económico'],
                'ingredients' => [
                    ['Garbanzos', 400, 'g'], ['Tomate', 2, 'ud'], ['Pepino', 1, 'ud'], ['Pimiento rojo', 1, 'ud'],
                    ['Cebolla', 0.5, 'ud'], ['Aceite de oliva', 3, 'cucharadas'], ['Limón', 0.5, 'ud'],
                    ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Enjuaga y escurre los garbanzos cocidos.',
                    'Corta el tomate, el pepino, el pimiento y la cebolla en dados pequeños.',
                    'Mezcla todo y aliña con el aceite, el zumo de limón y la sal.',
                ],
            ],
            [
                'title' => 'Pollo al ajillo',
                'category' => 'Principales',
                'difficulty' => Difficulty::Medium,
                'prep' => 10, 'cook' => 35, 'servings' => 4,
                'description' => 'Pollo dorado con mucho ajo, un clásico que pide pan para mojar.',
                'tags' => ['Sin gluten', 'Sin lactosa', 'Tradicional'],
                'ingredients' => [
                    ['Pollo', 1, 'kg'], ['Ajo', 8, 'dientes'], ['Aceite de oliva', 100, 'ml'],
                    ['Vinagre', 1, 'cucharada'], ['Sal', null, 'al gusto'],
                ],
                'steps' => [
                    'Sala el pollo troceado y dóralo en el aceite caliente por todos los lados.',
                    'Añade los ajos sin pelar, ligeramente aplastados, y baja el fuego.',
                    'Cocina tapado 25 minutos, dando la vuelta a las piezas de vez en cuando.',
                    'Riega con el vinagre, deja reducir un par de minutos y sirve.',
                ],
            ],
            [
                'title' => 'Tortitas caseras',
                'category' => 'Desayunos',
                'difficulty' => Difficulty::Easy,
                'prep' => 5, 'cook' => 15, 'servings' => 4,
                'description' => 'Tortitas esponjosas para el desayuno del fin de semana.',
                'tags' => ['Vegetariano', 'Rápido'],
                'ingredients' => [
                    ['Harina', 200, 'g'], ['Leche', 250, 'ml'], ['Huevo', 2, 'ud'], ['Azúcar', 2, 'cucharadas'],
                    ['Mantequilla', 30, 'g'], ['Sal', 1, 'pizca'],
                ],
                'steps' => [
                    'Mezcla la harina, el azúcar y la sal en un bol.',
                    'Añade los huevos, la leche y la mantequilla derretida, y bate hasta que no queden grumos.',
                    'Vierte un cucharón de masa en una sartén caliente y cocina hasta que salgan burbujas.',
                    'Dale la vuelta, dora el otro lado y repite con el resto de la masa.',
                ],
            ],
            [
                'title' => 'Bizcocho de chocolate',
                'category' => 'Postres',
                'difficulty' => Difficulty::Hard,
                'prep' => 20, 'cook' => 40, 'servings' => 8,
                'description' => 'Bizcocho denso e intenso, con chocolate negro fundido en la masa.',
                'tags' => ['Vegetariano'],
                'ingredients' => [
                    ['Chocolate negro', 200, 'g'], ['Mantequilla', 150, 'g'], ['Azúcar', 150, 'g'],
                    ['Huevo', 4, 'ud'], ['Harina', 100, 'g'], ['Sal', 1, 'pizca'],
                ],
                'steps' => [
                    'Precalienta el horno a 180 °C y engrasa un molde.',
                    'Funde el chocolate con la mantequilla al baño maría.',
                    'Bate los huevos con el azúcar hasta que doblen su volumen.',
                    'Incorpora el chocolate fundido y después la harina tamizada con movimientos envolventes.',
                    'Hornea 35-40 minutos y deja enfriar antes de desmoldar.',
                ],
            ],
        ];
    }
}
