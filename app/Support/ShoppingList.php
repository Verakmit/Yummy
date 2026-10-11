<?php

namespace App\Support;

use App\Models\Recipe;
use Illuminate\Support\Collection;

class ShoppingList
{
    /**
     * The recipe's ingredients with their quantities scaled to the given number of diners.
     *
     * Lines for the same ingredient and unit are added together; ingredients without a
     * quantity ("al gusto") are listed once, unscaled.
     *
     * @return Collection<int, array{key: string, name: string, amount: string}>
     */
    public static function for(Recipe $recipe, int $diners): Collection
    {
        $factor = $diners / max(1, $recipe->servings);

        return $recipe->ingredients
            ->groupBy(fn ($ingredient) => $ingredient->id.'-'.mb_strtolower(trim((string) $ingredient->pivot->unit)))
            ->map(function (Collection $lines, string $key) use ($factor) {
                $first = $lines->first();
                $quantities = $lines->pluck('pivot.quantity')->filter(fn ($quantity) => $quantity !== null);
                $total = $quantities->isEmpty() ? null : self::round($quantities->sum() * $factor);

                return [
                    'key' => $key,
                    'name' => $first->name,
                    'amount' => trim(Quantity::format($total).' '.$first->pivot->unit),
                ];
            })
            ->values();
    }

    /**
     * Large amounts are rounded to whole numbers (233 g, not 233,33 g); small ones keep two decimals.
     */
    private static function round(float $quantity): float
    {
        return $quantity >= 10 ? round($quantity) : round($quantity, 2);
    }
}
