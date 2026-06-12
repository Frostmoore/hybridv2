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

Route::get('/', fn () => view('welcome'))->name('main.home');

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

// ── Admin (fase 6, sessione) ────────────────────────────────────────────
// login, home, agenzia, creagenzia, importa_polizze, import_polizze, notifiche
