<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountDeletionController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminImportPolizzeController;
use App\Http\Controllers\Web\AdminNotificationController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\AgencyAdminController;
use App\Http\Controllers\Web\ImportaPolizzeController;
use App\Http\Controllers\Web\ImportPolizzeController;
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
Route::get('/', [AdminAuthController::class, 'showLogin'])->name('main.home');

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
Route::get('login', [AdminAuthController::class, 'showLogin']);
Route::post('login', [AdminAuthController::class, 'login']);
Route::get('logout', [AdminAuthController::class, 'logout']);
// Back-compat coi vecchi path (redirect interni, host preservato)
Route::get('index.html', fn () => redirect('/login'));
Route::post('authenticate.php', [AdminAuthController::class, 'login']);
Route::get('logout.php', fn () => redirect('/logout'));

Route::middleware('auth:admin')->group(function () {
    Route::get('home', [AgencyAdminController::class, 'home']);
    Route::get('home.php', fn () => redirect('/home'));

    // Agenzie — URL puliti
    Route::get('agenzia/nuova', [AgencyAdminController::class, 'create']);
    Route::post('agenzia', [AgencyAdminController::class, 'store']);
    Route::get('agenzia/{id}', [AgencyAdminController::class, 'edit'])->whereNumber('id');
    Route::post('agenzia/{id}', [AgencyAdminController::class, 'update'])->whereNumber('id');
    Route::post('agenzia/{id}/elimina', [AgencyAdminController::class, 'destroy'])->whereNumber('id');
    // Back-compat coi vecchi path (redirect GET, alias POST)
    Route::get('creagenzia.php', fn () => redirect('/agenzia/nuova'));
    Route::get('agenzia.php', fn () => redirect('/agenzia/'.(int) request()->query('id')));
    Route::post('res/updateagenzia.php', [AgencyAdminController::class, 'update']);
    Route::post('res/nuovagenzia.php', [AgencyAdminController::class, 'store']);

    // Import polizze (→ tabella `polizze`, quella che l'app legge davvero):
    // la stessa pagina del pannello agenzie, qui con la tendina dell'agenzia
    // perche' l'amministratore carica per conto di chiunque.
    Route::get('importa-polizze', [AdminImportPolizzeController::class, 'page']);
    Route::post('importa-polizze/carica', [AdminImportPolizzeController::class, 'carica']);
    Route::get('importa-polizze/mappatura', [AdminImportPolizzeController::class, 'mappatura']);
    Route::post('importa-polizze/importa', [AdminImportPolizzeController::class, 'importa']);
    Route::post('importa-polizze/annulla', [AdminImportPolizzeController::class, 'annulla']);

    // Vecchio wizard (sistema A → `polizze_importate`): spostato qui il
    // 2026-11-11. Scrive in una tabella che NON legge nessuno — ne' l'app, ne'
    // l'area agenzie, ne' il sito — quindi non merita l'URL buono. Non e'
    // stato cancellato solo perche' cancellare non l'ha chiesto nessuno.
    Route::match(['GET', 'POST'], 'importa-polizze-vecchio', [ImportaPolizzeController::class, 'page']);
    Route::post('importa-polizze-vecchio/process', [ImportaPolizzeController::class, 'process']);
    // Back-compat
    Route::get('importa_polizze.php', fn () => redirect('/importa-polizze'));
    Route::post('res/import_process.php', [ImportaPolizzeController::class, 'process']);

    // Notifiche admin: broadcast globale a TUTTI gli utenti del sistema
    Route::get('notifiche', [AdminNotificationController::class, 'page']);
    Route::post('notifiche', [AdminNotificationController::class, 'broadcast']);
    Route::post('notifiche/elimina', [AdminNotificationController::class, 'deleteBroadcast']);
    Route::post('notifiche/scadenza', [AdminNotificationController::class, 'updateBroadcastScadenza']);
    Route::get('notifiche.php', fn () => redirect('/notifiche'));

    // Utenti (tutte le agenzie): elenco, attivazione, invio reset password
    Route::get('utenti', [AdminUserController::class, 'index']);
    Route::post('utenti/{id}/attiva', [AdminUserController::class, 'activate'])->whereNumber('id');
    Route::post('utenti/{id}/reset-password', [AdminUserController::class, 'sendReset'])->whereNumber('id');
});

// Import polizze (sistema B → polizze): gate a password dedicato, NO sessione admin (come il legacy)
Route::match(['GET', 'POST'], 'import-polizze', [ImportPolizzeController::class, 'page']);
// Back-compat (GET redirige, POST resta alias per non perdere il body)
Route::get('import_polizze.php', fn () => redirect('/import-polizze'));
Route::post('import_polizze.php', [ImportPolizzeController::class, 'page']);
