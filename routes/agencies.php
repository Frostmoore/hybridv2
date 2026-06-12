<?php

use App\Http\Controllers\Agencies\AgenciesAuthController;
use App\Http\Controllers\Agencies\AgenciesNotificationController;
use App\Http\Controllers\Agencies\AgenciesPanelController;
use App\Http\Controllers\Agencies\OperatorApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pannello agenzie — agencies.hybridandgogsv2.it
|--------------------------------------------------------------------------
| Path identici al legacy (pagine .php + api/v1 interna).
| Guard `operatore` (tabella `operatori`), timeout inattività 30 minuti.
*/

// ── Pubbliche ────────────────────────────────────────────────────────────
Route::get('/', [AgenciesPanelController::class, 'index']);
Route::get('index.php', [AgenciesPanelController::class, 'index']);
Route::get('login.php', [AgenciesAuthController::class, 'showLogin']);
Route::post('api/v1/log.php', [AgenciesAuthController::class, 'apiLogin']);
Route::get('api/v1/logout.php', [AgenciesAuthController::class, 'logout']);

// Registrazione operatore (aperta come il legacy; l'account nasce disattivato)
Route::get('register.php', [AgenciesPanelController::class, 'register']);
Route::post('api/v1/reg.php', [OperatorApiController::class, 'store']);

// ── Autenticate (operatore) ──────────────────────────────────────────────
Route::middleware(['auth:operatore', 'operatore.timeout'])->group(function () {
    Route::get('home.php', [AgenciesPanelController::class, 'home']);
    Route::get('user_panel.php', [AgenciesPanelController::class, 'home']);
    Route::get('utenti.php', [AgenciesPanelController::class, 'utenti']);
    Route::get('export_utenti.php', [AgenciesPanelController::class, 'exportUtenti']);

    // Gestione operatori (la pagina operators è gated super-admin nel controller)
    Route::get('operators.php', [AgenciesPanelController::class, 'operators']);
    Route::get('addoperator.php', [AgenciesPanelController::class, 'addOperator']);
    Route::post('api/v1/addop.php', [OperatorApiController::class, 'store']);
    Route::post('api/v1/activate_operator.php', [OperatorApiController::class, 'activate']);
    Route::post('api/v1/update_agenzia.php', [OperatorApiController::class, 'updateAgenzia']);

    // Notifiche
    Route::get('new_notification_all.php', [AgenciesNotificationController::class, 'pageAll']);
    Route::get('new_notification_private.php', [AgenciesNotificationController::class, 'pagePrivate']);
    Route::get('new_notification_selected.php', [AgenciesNotificationController::class, 'pageSelected']);
    Route::post('api/v1/send_notification_all.php', [AgenciesNotificationController::class, 'sendAll']);
    Route::post('api/v1/send_notification_private.php', [AgenciesNotificationController::class, 'sendPrivate']);
    Route::post('api/v1/send_notification_selected.php', [AgenciesNotificationController::class, 'sendSelected']);
});
