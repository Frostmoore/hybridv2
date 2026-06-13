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
| URL pagine puliti (interni). Gli endpoint AJAX restano sotto `api/v1/*.php`
| (API interna del pannello). Guard `operatore`, timeout inattività 30 min.
*/

// ── Pubbliche ────────────────────────────────────────────────────────────
Route::get('/', [AgenciesPanelController::class, 'index']);
Route::get('login', [AgenciesAuthController::class, 'showLogin']);
Route::post('api/v1/log.php', [AgenciesAuthController::class, 'apiLogin']);
Route::get('api/v1/logout.php', [AgenciesAuthController::class, 'logout']);

// Registrazione operatore (aperta come il legacy; l'account nasce disattivato)
Route::get('registrazione', [AgenciesPanelController::class, 'register']);
Route::post('api/v1/reg.php', [OperatorApiController::class, 'store']);

// Back-compat coi vecchi path .php (redirect interni)
Route::get('index.php', [AgenciesPanelController::class, 'index']);
Route::get('login.php', fn () => redirect('/login'.(request()->query('session_expired') ? '?session_expired=1' : '')));
Route::get('register.php', fn () => redirect('/registrazione'));

// ── Autenticate (operatore) ──────────────────────────────────────────────
Route::middleware(['auth:operatore', 'operatore.timeout'])->group(function () {
    Route::get('home', [AgenciesPanelController::class, 'home']);
    Route::get('utenti', [AgenciesPanelController::class, 'utenti']);
    Route::get('export-utenti', [AgenciesPanelController::class, 'exportUtenti']);

    // Gestione operatori (la pagina operatori è gated super-admin nel controller)
    Route::get('operatori', [AgenciesPanelController::class, 'operators']);
    Route::get('operatori/nuovo', [AgenciesPanelController::class, 'addOperator']);
    Route::post('api/v1/addop.php', [OperatorApiController::class, 'store']);
    Route::post('api/v1/activate_operator.php', [OperatorApiController::class, 'activate']);
    Route::post('api/v1/update_agenzia.php', [OperatorApiController::class, 'updateAgenzia']);

    // Notifiche
    Route::get('notifiche/tutti', [AgenciesNotificationController::class, 'pageAll']);
    Route::get('notifiche/privata', [AgenciesNotificationController::class, 'pagePrivate']);
    Route::get('notifiche/selezionati', [AgenciesNotificationController::class, 'pageSelected']);
    Route::post('api/v1/send_notification_all.php', [AgenciesNotificationController::class, 'sendAll']);
    Route::post('api/v1/send_notification_private.php', [AgenciesNotificationController::class, 'sendPrivate']);
    Route::post('api/v1/send_notification_selected.php', [AgenciesNotificationController::class, 'sendSelected']);

    // Back-compat coi vecchi path pagina .php
    Route::get('home.php', fn () => redirect('/home'));
    Route::get('user_panel.php', fn () => redirect('/home'));
    Route::get('utenti.php', fn () => redirect('/utenti'));
    Route::get('export_utenti.php', fn () => redirect('/export-utenti'));
    Route::get('operators.php', fn () => redirect('/operatori'));
    Route::get('addoperator.php', fn () => redirect('/operatori/nuovo'));
    Route::get('new_notification_all.php', fn () => redirect('/notifiche/tutti'));
    Route::get('new_notification_private.php', fn () => redirect('/notifiche/privata'));
    Route::get('new_notification_selected.php', fn () => redirect('/notifiche/selezionati'));
});
