# Plan B — Tests 100% fieles a PostgreSQL real

> Estado: **EJECUTADO y VERDE** (172/172 tests en Postgres real, Pint green).

## Objetivo
Correr toda la suite contra PostgreSQL real (Docker) en una BD de test aislada,
eliminando la deriva de dialecto (antes `phpunit.xml` forzaba SQLite `:memory:`,
mientras prod/dev son Postgres).

## Entorno verificado
- Postgres 17 en Docker, `127.0.0.1:5444` (mapeo `5444:5432`), user `postgres`,
  auth `trust` (sin password). `pdo_pgsql` habilitado en el CLI de PHP.
- BD de test creada: `vyntra_testing`.

## Cambios aplicados (CÓDIGO REAL)
1. `phpunit.xml` — reemplazado `DB_CONNECTION=sqlite` + `:memory:` por
   `pgsql` apuntando a `vyntra_testing` (host 127.0.0.1, port 5444, user/postgres).
2. `.env.example` — `DB_CONNECTION=sqlite` → `pgsql` + defaults pgsql
   (`DB_PORT=5444`, nota de compose.yaml). Plantilla fiel a la instalación real.
3. `config/database.php` — default `env('DB_CONNECTION', 'sqlite')` → `pgsql`
   (red de seguridad anti-despliegue-SQLite-accidental).
4. `.env` (real) — agregado `FRONTEND_URL=http://localhost:3000` (no secreto).
5. `routes/api.php` — `PATCH /user/friend-requests/{request_uuid}` ahora lleva
   `->whereUuid('request_uuid')` (ver bug real abajo).

## Cambios en TESTS (fidelidad frontend + corrección)
6. `tests/Feature/Auth/PasswordResetTest.php` — nuevo
   `test_password_reset_route_redirects_to_frontend`: afirma que
   `GET /api/reset-password/{token}` redirige a `config('app.frontend_url')`.
7. `tests/Feature/Auth/SocialiteTest.php` — los dos tests de callback ahora
   afirman que la redirección contiene `config('app.frontend_url')`
   (simula existencia de frontend real).
8. `tests/Feature/FriendshipControllerTest.php` — `test_respond_requires_authentication`
   usa UUID de formato válido (`00000000-...`) para que la ruta matchee y el
   middleware auth devuelva 401 (antes usaba `some-uuid`, malformado).

## Bug real encontrado y corregido (dialect drift)
- `FriendshipController::respond` hacía `Friendship::where('uuid', $requestUuid)
  ->firstOrFail()`. Con un UUID malformado (`nonexistent-uuid`):
  - SQLite: texto cualquiera → no matchea → 404 (correcto).
  - Postgres: falla el cast a `uuid` → `PDOException 22P02` → **500**.
- Fix: `->whereUuid('request_uuid')` en la ruta → UUID malformado no matchea → 404.
  Esto expuso que el mismo patrón afecta a TODOS los endpoints con binding
  implícito por UUID (ver "Pendiente" abajo).

## Verificación
- `php artisan test` → **172 passed / 172, 470 assertions** (Postgres real).
- `./vendor/bin/pint --test` → passed.
- `php artisan route:list` → rutas resuelven (incl. `friend-requests/{request_uuid}`).

## Pendiente (decisión de arquitectura — ver mensaje al usuario)
El bug de UUID malformado → 500 en Postgres es **sistémico** en todos los
endpoints con route model binding implícito por UUID (params `user`, `club`,
`channel`, `dm_conversation`, `notification`, `member`, `role`, `category`):
el binding hace `where('uuid', $valor)` y Postgres rechaza el cast.
Opciones de fix global:
- (A) `Route::pattern()` para cada nombre de param en `AppServiceProvider::boot()`.
- (B) Override de `resolveRouteBindingQuery` en un base model / trait compartido
  por todos los modelos UUID (param-name-agnostic, más robusto).
