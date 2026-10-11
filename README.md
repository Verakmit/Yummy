# Yummy

Recetario web para consultar, buscar y publicar recetas de cocina.

Hecho con Laravel 13, Livewire 4, Bootstrap 5.3 (Sass), Vite y MariaDB.

## Qué hace

- **Listado** de recetas en tarjetas, paginado de 12 en 12.
- **Ficha** de cada receta con ingredientes, cantidades, pasos y etiquetas.
- **Búsqueda** por texto en título y descripción, mientras se escribe.
- **Filtros** combinables por categoría, ingrediente, dificultad, tiempo máximo y etiquetas. Quedan reflejados en la URL, así que una búsqueda se puede compartir con un enlace.
- **Lista de la compra** que junta varias recetas, cada una con su número de comensales, y suma los ingredientes que comparten. Es temporal: se puede marcar, copiar o imprimir, pero no se guarda.
- **Crear, editar y borrar** recetas, con filas de ingredientes y pasos que se añaden, quitan y reordenan, y foto opcional.

> **Aviso:** la aplicación no tiene inicio de sesión. Cualquiera que pueda abrirla puede crear, editar y borrar recetas. Está pensada para uso local; antes de publicarla en internet hay que proteger esas pantallas.

## Requisitos

- PHP 8.3 o superior, con las extensiones `pdo_mysql`, `mbstring`, `openssl` y `fileinfo`
- Composer
- Node.js y npm
- MariaDB 10.4 o superior (sirve la que trae XAMPP)

## Instalación

1. Clona el repositorio y entra en la carpeta:

   ```sh
   git clone https://github.com/Verakmit/Yummy.git
   cd Yummy
   ```

2. Arranca MariaDB y crea las dos bases de datos, la de desarrollo y la de tests:

   ```sql
   CREATE DATABASE yummy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE yummy_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Instala las dependencias, crea el `.env`, aplica las migraciones y compila los assets:

   ```sh
   composer setup
   ```

   El `.env` que se genera se conecta a `127.0.0.1:3306` con el usuario `root` sin contraseña, que es la configuración por defecto de XAMPP. Si la tuya es distinta, edita las variables `DB_*` del `.env` y ejecuta `php artisan migrate`.

4. Carga las recetas de ejemplo y enlaza la carpeta de fotos:

   ```sh
   php artisan db:seed
   php artisan storage:link
   ```

## Uso

Arranca el servidor de la aplicación y el de Vite a la vez:

```sh
composer dev
```

y abre <http://localhost:8000>.

También se pueden lanzar por separado, con `php artisan serve` y `npm run dev`.

Para volver a dejar la base de datos con las 30 recetas de ejemplo:

```sh
php artisan migrate:fresh --seed
```

## Tests

```sh
php artisan test
```

Los tests necesitan MariaDB en marcha y usan la base de datos `yummy_testing`, no SQLite, porque la búsqueda de texto se apoya en un índice FULLTEXT.

Para lanzar un solo archivo o un solo test:

```sh
php artisan test tests/Feature/RecipeFormTest.php
php artisan test --filter=test_search_ignores_accents
```

## Estructura

No hay controladores: cada página es un componente Livewire de un solo archivo en `resources/views/pages/`, y las rutas de `routes/web.php` apuntan directamente a ellos.

| Ruta | Página |
|---|---|
| `/` | Listado con búsqueda y filtros |
| `/nueva-receta` | Formulario de alta |
| `/recetas/{slug}` | Ficha de la receta |
| `/recetas/{slug}/editar` | Formulario de edición |
| `/lista-de-la-compra` | Lista de la compra |

Los modelos están en `app/Models`: una receta pertenece a una categoría, tiene pasos ordenados y se relaciona con etiquetas e ingredientes. Los ingredientes forman un catálogo compartido entre recetas.

En `CLAUDE.md` hay una descripción más detallada de la arquitectura.
