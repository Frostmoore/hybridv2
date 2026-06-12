# HybridV2 — hybridandgogsv2.it

Migrazione a Laravel 12 del sito white-label multi-agenzia `hybridandgogsv.it` (PHP puro).
Un unico progetto serve due domini:

| Dominio (prod) | Dominio (dev) | Contenuto |
|---|---|---|
| `hybridandgogsv2.it` | `hybridandgogsv2.test` | sito principale + API v2 (`/res/api/v2/*`) |
| `agencies.hybridandgogsv2.it` | `agencies.hybridandgogsv2.test` | pannello operatori |

Documentazione di lavoro (fuori repo, cartella `../memory/`):
`migration_plan.md` (piano + tracking fasi) e `codebase_reference.md` (mappa del legacy).

## Requisiti
- PHP 8.3 (estensioni: pdo_mysql, mbstring, openssl, curl, zip, fileinfo, intl)
- Composer 2
- MySQL/MariaDB (charset utf8mb4)

## Setup locale

```bash
composer install
copy .env.example .env          # poi compila APP_KEY, JWT_SECRET, DB_*
php artisan key:generate
# genera un JWT_SECRET casuale di almeno 64 caratteri e mettilo in .env
php artisan migrate
```

Aggiungi i due host al file hosts (`C:\Windows\System32\drivers\etc\hosts` su Windows):

```
127.0.0.1 hybridandgogsv2.test
127.0.0.1 agencies.hybridandgogsv2.test
```

Avvio rapido (entrambi i domini sulla stessa porta):

```bash
php artisan serve --host=0.0.0.0 --port=8080
# → http://hybridandgogsv2.test:8080 e http://agencies.hybridandgogsv2.test:8080
```

In alternativa, vhost Apache/XAMPP puntati a `public/` con `ServerName` corrispondenti
(in quel caso aggiorna `APP_DOMAIN_*` nel `.env` se usi domini diversi).

## Architettura

- **Routing per dominio** — `bootstrap/app.php` registra tre gruppi:
  `routes/api_v2.php` (nessuna sessione/CSRF, path legacy con suffisso `.php`),
  `routes/agencies.php` (dominio agencies, middleware web),
  `routes/main.php` (dominio principale, middleware web).
- **Contratto API v2** — identico al legacy: wrapper `{"success":…}`,
  `App\Support\ApiResponse` (`ok()/err()/s()`), errori 404/405/500 in JSON
  (exception handler in `bootstrap/app.php`).
- **Auth API** — `App\Services\JwtService` (HS256 compatibile col legacy, secret in
  `JWT_SECRET`) + middleware `auth.jwt` (`App\Http\Middleware\AuthenticateJwt`);
  i claims sono in `$request->attributes->get('jwt')`.
- **Config di progetto** — `config/hybrid.php` (domini, JWT, import password).
- **Mail** — SMTP (prod: Aruba, come il legacy). Test rapido:
  `php artisan tinker` → `Mail::to('tu@esempio.it')->send(new \App\Mail\TestMail());`

## Test

```bash
php artisan test
```
