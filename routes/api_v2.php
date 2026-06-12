<?php

use App\Http\Controllers\ApiV2\AgencyController;
use App\Http\Controllers\ApiV2\AuthController;
use App\Http\Controllers\ApiV2\ClaimsController;
use App\Http\Controllers\ApiV2\NotificationController;
use App\Http\Controllers\ApiV2\PolizzeController;
use App\Http\Controllers\ApiV2\UserController;
use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 — /res/api/v2/*
|--------------------------------------------------------------------------
| Path identici al legacy (incluso il suffisso .php). Contratto documentato
| in legacy/public_html/API_V2.md e memory/codebase_reference.md §5.
| Registrate SENZA middleware web: niente sessione, niente CSRF.
*/

Route::prefix('res/api/v2')->group(function () {

    // ── Pubblici (no auth) ──────────────────────────────────────────────
    Route::get('agency.php', [AgencyController::class, 'show']);
    Route::post('auth/login.php', [AuthController::class, 'login']);
    Route::post('auth/register.php', [AuthController::class, 'register']);
    Route::post('auth/forgot-password.php', [AuthController::class, 'forgotPassword']);
    Route::get('notifications/general.php', [NotificationController::class, 'general']);

    // ── Autenticati (Bearer JWT) ────────────────────────────────────────
    Route::middleware('auth.jwt')->group(function () {
        Route::match(['GET', 'PATCH'], 'user/me.php', [UserController::class, 'me']);
        Route::patch('user/password.php', [UserController::class, 'password']);
        Route::patch('user/privacy.php', [UserController::class, 'privacy']);
        Route::get('notifications/index.php', [NotificationController::class, 'index']);
        Route::get('notifications/single.php', [NotificationController::class, 'single']);
        Route::post('notifications/read.php', [NotificationController::class, 'read']);
        Route::post('claims/sinistro.php', [ClaimsController::class, 'sinistro']);
        Route::post('claims/preventivo.php', [ClaimsController::class, 'preventivo']);
        Route::post('claims/documento.php', [ClaimsController::class, 'documento']);
        Route::get('polizze/index.php', [PolizzeController::class, 'index']);
    });
});

// Immagini per-agenzia agli stessi path del legacy (img/, img_<id>/)
Route::get('res/{path}', [AssetController::class, 'show'])
    ->where('path', 'img[^/]*/.+');
