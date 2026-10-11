<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Models\Concerns\HasSlug;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id',
    'title',
    'slug',
    'description',
    'prep_minutes',
    'cook_minutes',
    'servings',
    'difficulty',
    'image_path',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Recipe $recipe) {
            if ($recipe->image_path) {
                Storage::disk('public')->delete($recipe->image_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    /**
     * Match every word of the term, as a prefix, against the title and description.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        // The full-text index ignores tokens shorter than three characters
        $words = collect(preg_split('/[^\pL\pN]+/u', $term, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word) => mb_strlen($word) >= 3);

        if ($words->isEmpty()) {
            $query->whereLike('title', '%'.addcslashes($term, '%_\\').'%');

            return;
        }

        $query->whereFullText(
            ['title', 'description'],
            $words->map(fn (string $word) => '+'.$word.'*')->implode(' '),
            ['mode' => 'boolean'],
        );
    }

    /**
     * Preparation plus cooking time, formatted for display (e.g. "1 h 15 min").
     */
    public function totalTimeLabel(): string
    {
        $minutes = $this->prep_minutes + $this->cook_minutes;
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => $rest.' min',
            $rest === 0 => $hours.' h',
            default => $hours.' h '.$rest.' min',
        };
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['quantity', 'unit', 'note', 'position'])
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<Step, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
