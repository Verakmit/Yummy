# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Yummy is a recipe web app: browse, search and filter recipes, and create, edit and delete them. Laravel 13, Livewire 4, Bootstrap 5.3 (Sass) built with Vite, on MariaDB. There is no authentication by design: every page, including create, edit and delete, is public.

User-facing text is Spanish (`APP_LOCALE=es`); code, identifiers and comments are English.

## Environment

- Windows with XAMPP. The database server is **MariaDB 10.4**, not MySQL, so `.env` uses `DB_CONNECTION=mariadb` (user `root`, empty password). It must be started from the XAMPP control panel; `mysql.exe` lives in `C:\xampp\mysql\bin` and is not on `PATH`.
- Two databases: `yummy` (development) and `yummy_testing` (tests, set in `phpunit.xml`).
- PHP has no `intl` or `gd` extension. Avoid `Illuminate\Support\Number` formatting and `UploadedFile::fake()->image()`; see `App\Support\Quantity` and the inline PNG in `tests/Feature/RecipeFormTest.php` for the workarounds in use.

## Commands

```sh
composer dev                      # app server + Vite dev server together
php artisan serve                 # app server only (http://localhost:8000)
npm run dev                       # Vite dev server only
npm run build                     # production assets

php artisan migrate:fresh --seed  # rebuild the dev database with 30 sample recipes

php artisan test                              # whole suite (needs MariaDB running)
php artisan test tests/Feature/RecipeFormTest.php
php artisan test --filter=test_search_ignores_accents

vendor/bin/pint                   # format PHP
```

## Architecture

### Pages are Livewire single-file components

There are no controllers. Each route in `routes/web.php` is `Route::livewire(...)` pointing at a single-file component under `resources/views/pages/`, whose file name starts with `⚡` (PHP class and Blade template in the same file):

| Route | Component | File |
|---|---|---|
| `/` | `pages::home` | `pages/⚡home.blade.php` |
| `/nueva-receta` | `pages::recipes.form` | `pages/recipes/⚡form.blade.php` |
| `/recetas/{recipe}` | `pages::recipes.show` | `pages/recipes/⚡show.blade.php` |
| `/recetas/{recipe}/editar` | `pages::recipes.form` | same form component |
| `/lista-de-la-compra` | `pages::shopping-list` | `pages/⚡shopping-list.blade.php` |

All of them render inside `resources/views/layouts/app.blade.php`, which also shows the `status` session flash. `Recipe` binds by `slug`. The create route is `/nueva-receta` rather than `/recetas/nueva` so it can never collide with a recipe slug.

The form component serves both create and edit. On the create route the container injects an empty `Recipe` into `mount()` instead of `null`, which is why it checks `$recipe?->exists`.

`config/livewire.php` is published only to set `pagination_theme` to `bootstrap`.

### Data model

`Recipe` belongs to one `Category`, has many ordered `Step`s, and belongs to many `Tag`s and `Ingredient`s. `Ingredient` is a shared catalogue; the `ingredient_recipe` pivot carries `quantity`, `unit`, `note` and `position`, and the same ingredient may appear twice in one recipe. Deleting a recipe cascades to its steps and pivot rows; categories and ingredients that are in use cannot be deleted.

- **Slugs** come from the `App\Models\Concerns\HasSlug` trait: generated on save only when empty, made unique with a numeric suffix, and never regenerated on rename so URLs stay stable. Seeders must not use `WithoutModelEvents`, or slugs will not be generated.
- **Difficulty** is the `App\Enums\Difficulty` string enum, stored in a plain string column.
- **Saving a recipe** (form component `save()`) replaces all pivot rows and steps inside a transaction rather than diffing them, and creates missing ingredients by name with `firstOrCreate` (the collation makes this case- and accent-insensitive).
- **Images** go to the `public` disk under `recipes/`. The old file is removed when a photo is replaced or removed, and a `deleted` model hook removes it with the recipe.

### Search and filters

All filtering lives in the `recipes` computed property of `pages/⚡home.blade.php`; every filter is a `#[Url]` property with a Spanish query-string alias (`q`, `categoria`, `ingrediente`, `dificultad`, `tiempo`, `etiquetas`). Selected tags are combined with AND.

Text search is the `search` scope on `Recipe`. It runs a boolean-mode FULLTEXT query over `title` and `description`, turning each word into a required prefix term (`+word*`). The index ignores tokens shorter than three characters, so those are dropped, and a term made only of short words falls back to a `LIKE` on the title.

### Shopping list

The shopping list combines several recipes and is never written to the application's tables.

- `App\Support\ShoppingSelection` holds the picked recipes in the session as `[recipe id => diners]`. Recipes are added or removed from the cards on the home page and from the recipe page; each starts with its own number of servings.
- `App\Support\ShoppingList::combine()` scales each quantity by `diners / servings`, adds up lines that share ingredient and unit (across recipes too), rounds totals of 10 or more to whole numbers and sorts by name.
- Anything that changes the selection dispatches `shopping-list-updated`, which the `shopping-list-link` component in the navbar listens to in order to refresh its counter.
- The ticked-off state of each line is client-side Alpine state only.

### Tests

The FULLTEXT index is the reason for two unusual choices:

- Tests run against MariaDB (`yummy_testing`), because SQLite cannot create the index.
- Test classes use `DatabaseTruncation`, not `RefreshDatabase`: InnoDB full-text search only sees committed rows, so it returns nothing inside the wrapping transaction. Mixing both traits leaks rows between classes, so keep every class on `DatabaseTruncation`.

Livewire components are tested by name, e.g. `Livewire::test('pages::recipes.form', ['recipe' => $recipe])`.

### Front end

`resources/sass/app.scss` imports Bootstrap's Sass and holds the few custom rules; `resources/js/app.js` imports Bootstrap's JavaScript. `vite.config.js` silences the Sass deprecation warnings that Bootstrap 5.3 triggers.
