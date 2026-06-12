<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 — /res/api/v2/*
|--------------------------------------------------------------------------
| Path identici al legacy (incluso il suffisso .php): il contratto è
| documentato in legacy/public_html/API_V2.md e in memory/codebase_reference.md §5.
| Registrate SENZA middleware web: niente sessione, niente CSRF.
| Gli endpoint arrivano con la fase 3; qui solo la struttura dei gruppi.
*/

Route::prefix('res/api/v2')->group(function () {

    // ── Pubblici (no auth) ──────────────────────────────────────────────
    // Route::get('agency.php', …);
    // Route::post('auth/login.php', …);
    // Route::post('auth/register.php', …);
    // Route::post('auth/forgot-password.php', …);
    // Route::get('notifications/general.php', …);

    // ── Autenticati (Bearer JWT) ────────────────────────────────────────
    Route::middleware('auth.jwt')->group(function () {
        // Route::match(['GET', 'PATCH'], 'user/me.php', …);
        // Route::patch('user/password.php', …);
        // Route::patch('user/privacy.php', …);
        // Route::get('notifications/index.php', …);
        // Route::get('notifications/single.php', …);
        // Route::post('notifications/read.php', …);
        // Route::post('claims/sinistro.php', …);
        // Route::post('claims/preventivo.php', …);
        // Route::post('claims/documento.php', …);
        // Route::get('polizze/index.php', …);
    });
});
