<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dominio principale — hybridandgogsv2.it
|--------------------------------------------------------------------------
| Pagine web (admin + pubbliche) del sito principale. Path identici al
| legacy dove l'URL è esposto (link email, store, form pubblici).
| I contenuti arrivano con le fasi 5 e 6.
*/

Route::get('/', fn () => view('welcome'))->name('main.home');

// ── Pubbliche (fase 5) ──────────────────────────────────────────────────
// Route::get('denuncia_sinistro.php', …);
// Route::get('preventivo.php', …);
// Route::get('documento.php', …);
// Route::get('attivacliente.php', …);
// Route::get('cambiapassword.php', …);
// Route::match(['GET','POST'], 'delete_account.php', …);
// Route::match(['GET','POST'], 'disable_account.php', …);
// Route::match(['GET','POST'], 'remove_account.php', …);

// ── Admin (fase 6, sessione) ────────────────────────────────────────────
// login, home, agenzia, creagenzia, importa_polizze, import_polizze, notifiche
