<?php

namespace App\Support;

use App\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * The recipes picked for the shopping list and how many diners each is for.
 *
 * It lives only in the visitor's session: nothing is written to the application's tables.
 */
class ShoppingSelection
{
    public const MAX_DINERS = 100;

    public const MAX_RECIPES = 30;

    private const SESSION_KEY = 'shopping_list';

    /**
     * @return array<int, int> Diners keyed by recipe id, in the order the recipes were added.
     */
    public static function all(): array
    {
        $selection = session(self::SESSION_KEY, []);

        return is_array($selection) ? $selection : [];
    }

    public static function count(): int
    {
        return count(self::all());
    }

    public static function has(int $recipeId): bool
    {
        return array_key_exists($recipeId, self::all());
    }

    public static function add(Recipe $recipe): void
    {
        $selection = self::all();

        if (isset($selection[$recipe->id]) || count($selection) >= self::MAX_RECIPES) {
            return;
        }

        $selection[$recipe->id] = self::clamp($recipe->servings);

        session([self::SESSION_KEY => $selection]);
    }

    public static function toggle(Recipe $recipe): void
    {
        self::has($recipe->id) ? self::remove($recipe->id) : self::add($recipe);
    }

    public static function setDiners(int $recipeId, int $diners): void
    {
        $selection = self::all();

        if (isset($selection[$recipeId])) {
            $selection[$recipeId] = self::clamp($diners);

            session([self::SESSION_KEY => $selection]);
        }
    }

    public static function remove(int $recipeId): void
    {
        $selection = self::all();

        unset($selection[$recipeId]);

        session([self::SESSION_KEY => $selection]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * The selected recipes with their ingredients loaded, forgetting any that no longer exist.
     *
     * @return Collection<int, array{recipe: Recipe, diners: int}>
     */
    public static function entries(): Collection
    {
        $selection = self::all();
        $recipes = Recipe::with('ingredients')->findMany(array_keys($selection))->keyBy('id');

        if ($recipes->count() !== count($selection)) {
            $selection = array_intersect_key($selection, $recipes->all());

            session([self::SESSION_KEY => $selection]);
        }

        return collect($selection)
            ->map(fn (int $diners, int $id) => ['recipe' => $recipes[$id], 'diners' => $diners])
            ->values();
    }

    private static function clamp(int $diners): int
    {
        return max(1, min(self::MAX_DINERS, $diners));
    }
}
