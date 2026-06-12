<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pannello agenzie — agencies.hybridandgogsv2.it
|--------------------------------------------------------------------------
| Pagine operatori + api interna (sessione). I contenuti arrivano con la fase 7.
*/

Route::get('/', fn () => view('welcome'))->name('agencies.home');
