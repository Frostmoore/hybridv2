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
- **`www.` → apex** — `App\Http\Middleware\RedirectWwwToApex` (middleware globale,
  gira prima del routing). I gruppi `Route::domain(...)` confrontano l'host alla
  lettera, quindi senza redirect ogni pagina web su `www.<dominio>` risponde 404.
  Le route API sono **escluse** di proposito: rispondono già su qualsiasi host e
  le app pubblicate le chiamano su `www.` (redirigere le POST le romperebbe).
  Copertura: `tests/Feature/Web/WwwRedirectTest.php`.
- **Contratto API v2** — identico al legacy: wrapper `{"success":…}`,
  `App\Support\ApiResponse` (`ok()/err()/s()`), errori 404/405/500 in JSON
  (exception handler in `bootstrap/app.php`).
- **Auth API** — `App\Services\JwtService` (HS256 compatibile col legacy, secret in
  `JWT_SECRET`) + middleware `auth.jwt` (`App\Http\Middleware\AuthenticateJwt`);
  i claims sono in `$request->attributes->get('jwt')`.
- **Import polizze da SSF** — `App\Services\SsfImportService` legge lo ZIP di CSV esportato dai
  gestionali delle agenzie (formati v7 e v10, un solo parser perché si legge per nome di colonna) e
  riempie la tabella `polizze`, quella che l'endpoint `polizze/index.php` serve all'app. Le regole
  di selezione — solo polizze attive, solo persone fisiche con codice fiscale, via i numeri di
  polizza usati da più clienti — sono motivate con i numeri in `memory/decisioni.md`; la mappatura
  campo per campo sta in `memory/mappatura_ssf_polizze.md`. Copertura:
  `tests/Feature/Import/SsfImportServiceTest.php`.
- **Import polizze (due pannelli, una pagina)** — il giro sta nel trait
  `App\Http\Controllers\Concerns\ImportaLePolizze`, montato da due controller:
  `Agencies\AgenciesImportController` (`/polizze` sul dominio agencies) e
  `Web\AdminImportPolizzeController` (`/importa-polizze` sul dominio principale, sessione
  admin). L'unica differenza e' da dove esce l'id dell'agenzia: nel pannello agenzie dalla
  sessione dell'operatore e **non e' un campo del form** (altrimenti un'agenzia potrebbe
  scrivere nelle polizze di un'altra), nel pannello admin da una tendina, perche' li' si carica
  per conto di chiunque. L'agenzia scelta al passo 1 viene salvata col file e riusata al passo 3,
  cosi' non puo' cambiare per strada. Le viste stanno in `resources/views/polizze/` e si
  adattano ai due layout via `$layout`/`$base`. Due strade: lo **ZIP SSF** si importa subito
  (`App\Services\SsfImportService`), un **foglio CSV/XLSX/"XLS"** passa dalla **mappatura
  manuale** delle colonne (`App\Services\PolizzeSheetImportService`, che riconosce il formato
  dal contenuto e normalizza le date in ISO). Fra i due passi il file resta in
  `storage/app/private/import-polizze/` e viene cancellato appena l'import finisce. Copertura:
  `tests/Feature/Agencies/PolizzeImportTest.php`, `tests/Feature/Web/AdminImportPolizzeTest.php`,
  `tests/Feature/Import/PolizzeSheetImportServiceTest.php`.
  ⚠️ A `/importa-polizze` stava il vecchio wizard (→ `polizze_importate`, tabella che non legge
  nessuno): e' stato spostato a `/importa-polizze-vecchio` e non cancellato.
- **Config di progetto** — `config/hybrid.php` (domini, JWT, import password).
- **Mail** — SMTP (prod: Aruba, come il legacy). Test rapido:
  `php artisan tinker` → `Mail::to('tu@esempio.it')->send(new \App\Mail\TestMail());`

## Test

```bash
php artisan test
```
