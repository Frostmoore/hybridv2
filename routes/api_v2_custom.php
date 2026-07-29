<?php

use App\Http\Controllers\ApiV2\Custom\ConsulenzaController;
use App\Http\Controllers\ApiV2\Custom\FeaturesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 — implementazioni SPECIALI per singole agenzie
|--------------------------------------------------------------------------
| Fuori dal contratto canonico di API_V2.md: questi path esistono solo per le
| agenzie che hanno la relativa feature attiva in `agenzie_speciale` (tab
| "Speciale" del pannello admin). Documentati in API_V2_CUSTOM.md.
|
| File separato da api_v2.php di proposito: la roba custom non deve sporcare il
| contratto standard, e smantellare una feature speciale = cancellare una riga
| qui più la sua colonna in `agenzie_speciale`.
|
| Nessuna app standard chiama questi endpoint. Chi non è abilitato riceve 404.
*/

Route::prefix('res/api/v2')->group(function () {

    // Flag delle feature speciali (pre-login, come agency.php)
    Route::get('custom/features.php', [FeaturesController::class, 'show']);

    Route::middleware('auth.jwt')->group(function () {
        Route::post('claims/consulenza.php', [ConsulenzaController::class, 'store']);
    });
});
