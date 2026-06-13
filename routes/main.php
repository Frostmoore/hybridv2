<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountDeletionController;
use App\Http\Controllers\Web\PublicClaimController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dominio principale — hybridandgogsv2.it
|--------------------------------------------------------------------------
| Path identici al legacy dove l'URL è esposto (link nelle email, store,
| form pubblici per-agenzia).
*/

// Come il legacy: la root del dominio principale è la login admin (index.html)
Route::get('/', [\App\Http\Controllers\Web\AdminAuthController::class, 'showLogin'])->name('main.home');

// ── Form pubblici per-agenzia (fase 5) ──────────────────────────────────
Route::get('denuncia_sinistro.php', [PublicClaimController::class, 'showSinistro']);
Route::get('preventivo.php', [PublicClaimController::class, 'showPreventivo']);
Route::get('documento.php', [PublicClaimController::class, 'showDocumento']);
Route::post('res/denunciasinistro.php', [PublicClaimController::class, 'handleSinistro']);
Route::post('res/richiestapreventivo.php', [PublicClaimController::class, 'handlePreventivo']);
Route::post('res/caricadocumenti.php', [PublicClaimController::class, 'handleDocumento']);

// ── Attivazione account / reset password (link nelle email) ─────────────
Route::get('attivacliente.php', [AccountController::class, 'attivaCliente']);
Route::get('cambiapassword.php', [AccountController::class, 'cambiaPassword']);
Route::post('res/userpasswordhandler.php', [AccountController::class, 'userPasswordHandler']);

// ── Cancellazione account (URL dichiarati negli store) ──────────────────
Route::get('delete_account.php', [AccountDeletionController::class, 'showRequestForm']);
Route::post('delete_account.php', [AccountDeletionController::class, 'submitRequest']);
Route::get('disable_account.php', [AccountDeletionController::class, 'showRequestForm']);
Route::post('disable_account.php', [AccountDeletionController::class, 'submitRequest']);
Route::get('remove_account.php', [AccountDeletionController::class, 'showConfirmForm']);
Route::post('remove_account.php', [AccountDeletionController::class, 'confirmDeletion']);

// ── Admin (sessione, guard `admin` su tabella `utenti`) ─────────────────
// URL puliti (le pagine admin sono interne: nessun riferimento esterno).
Route::get('login', [\App\Http\Controllers\Web\AdminAuthController::class, 'showLogin']);
Route::post('login', [\App\Http\Controllers\Web\AdminAuthController::class, 'login']);
Route::get('logout', [\App\Http\Controllers\Web\AdminAuthController::class, 'logout']);
// Back-compat coi vecchi path (redirect interni, host preservato)
Route::get('index.html', fn () => redirect('/login'));
Route::post('authenticate.php', [\App\Http\Controllers\Web\AdminAuthController::class, 'login']);
Route::get('logout.php', fn () => redirect('/logout'));

Route::middleware('auth:admin')->group(function () {
    Route::get('home', [\App\Http\Controllers\Web\AgencyAdminController::class, 'home']);
    Route::get('home.php', fn () => redirect('/home'));

    // Agenzie — URL puliti
    Route::get('agenzia/nuova', [\App\Http\Controllers\Web\AgencyAdminController::class, 'create']);
    Route::post('agenzia', [\App\Http\Controllers\Web\AgencyAdminController::class, 'store']);
    Route::get('agenzia/{id}', [\App\Http\Controllers\Web\AgencyAdminController::class, 'edit'])->whereNumber('id');
    Route::post('agenzia/{id}', [\App\Http\Controllers\Web\AgencyAdminController::class, 'update'])->whereNumber('id');
    // Back-compat coi vecchi path (redirect GET, alias POST)
    Route::get('creagenzia.php', fn () => redirect('/agenzia/nuova'));
    Route::get('agenzia.php', fn () => redirect('/agenzia/'.(int) request()->query('id')));
    Route::post('res/updateagenzia.php', [\App\Http\Controllers\Web\AgencyAdminController::class, 'update']);
    Route::post('res/nuovagenzia.php', [\App\Http\Controllers\Web\AgencyAdminController::class, 'store']);

    // Wizard import polizze (sistema A → polizze_importate) — URL pulito
    Route::match(['GET', 'POST'], 'importa-polizze', [\App\Http\Controllers\Web\ImportaPolizzeController::class, 'page']);
    Route::post('importa-polizze/process', [\App\Http\Controllers\Web\ImportaPolizzeController::class, 'process']);
    // Back-compat
    Route::get('importa_polizze.php', fn () => redirect('/importa-polizze'));
    Route::post('res/import_process.php', [\App\Http\Controllers\Web\ImportaPolizzeController::class, 'process']);

    // Pagina notifiche (incompleta anche nel legacy — vedi critics.md)
    Route::get('notifiche', fn () => view('admin.notifiche'));
    Route::post('notifiche', fn () => redirect('notifiche')->with('status', 'Funzione non operativa: lo era anche nel sistema precedente. In attesa di specifiche.'));
    Route::get('notifiche.php', fn () => redirect('/notifiche'));
});

// Import polizze (sistema B → polizze): gate a password dedicato, NO sessione admin (come il legacy)
Route::match(['GET', 'POST'], 'import-polizze', [\App\Http\Controllers\Web\ImportPolizzeController::class, 'page']);
// Back-compat (GET redirige, POST resta alias per non perdere il body)
Route::get('import_polizze.php', fn () => redirect('/import-polizze'));
Route::post('import_polizze.php', [\App\Http\Controllers\Web\ImportPolizzeController::class, 'page']);
