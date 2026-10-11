<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (Model $model) {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlug((string) $model->{$model->slugSource()});
            }
        });
    }

    /**
     * The attribute the slug is generated from.
     */
    protected function slugSource(): string
    {
        return 'name';
    }

    protected function uniqueSlug(string $value): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
