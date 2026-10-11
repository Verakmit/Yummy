<?php

namespace App\Support;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShoppingList
{
    /**
     * The ingredients needed to cook the given recipes, each for its own number of diners.
     *
     * Quantities are scaled by diners / servings. Lines that share ingredient and unit are
     * added together, across recipes too; ingredients without a quantity ("al gusto") are
     * listed once, unscaled. The result is sorted by ingredient name.
     *
     * @param  iterable<array{recipe: Recipe, diners: int}>  $entries
     * @return Collection<int, array{key: string, name: string, amount: string}>
     */
    public static function combine(iterable $entries): Collection
    {
        $lines = collect();

        foreach ($entries as $entry) {
            $factor = $entry['diners'] / max(1, $entry['recipe']->servings);

            foreach ($entry['recipe']->ingredients as $ingredient) {
                $unit = trim((string) $ingredient->pivot->unit);
                $quantity = $ingredient->pivot->quantity;

                $lines->push([
                    'key' => $ingredient->id.'-'.mb_strtolower($unit),
                    'name' => $ingredient->name,
                    'unit' => $unit,
                    'quantity' => $quantity === null ? null : $quantity * $factor,
                ]);
            }
        }

        return $lines
            ->groupBy('key')
            ->map(function (Collection $group, string|int $key) {
                $quantities = $group->pluck('quantity')->filter(fn ($quantity) => $quantity !== null);
                $total = $quantities->isEmpty() ? null : self::round($quantities->sum());

                return [
                    'key' => (string) $key,
                    'name' => $group->first()['name'],
                    'amount' => trim(Quantity::format($total).' '.$group->first()['unit']),
                ];
            })
            // Str::ascii keeps accented names in place without needing the intl extension
            ->sortBy(fn (array $item) => Str::lower(Str::ascii($item['name'])))
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
