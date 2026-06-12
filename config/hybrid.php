<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domini dell'applicazione
    |--------------------------------------------------------------------------
    | Il progetto serve due domini dalla stessa codebase:
    | - main:     sito principale + API v2 (prod: hybridandgogsv2.it)
    | - agencies: pannello operatori     (prod: agencies.hybridandgogsv2.it)
    */

    'domain_main' => env('APP_DOMAIN_MAIN', 'hybridandgogsv2.test'),
    'domain_agencies' => env('APP_DOMAIN_AGENCIES', 'agencies.hybridandgogsv2.test'),

    /*
    |--------------------------------------------------------------------------
    | JWT API v2
    |--------------------------------------------------------------------------
    | Stesso formato del legacy (HS256 manuale, claims sub/username/agency_id)
    | ma con secret nuovo. Expiry in secondi (default 30 giorni).
    */

    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expiry' => (int) env('JWT_EXPIRY', 2592000),

    /*
    |--------------------------------------------------------------------------
    | Pagina import polizze (sistema B)
    |--------------------------------------------------------------------------
    */

    'import_password' => env('IMPORT_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Super-admin del pannello agencies
    |--------------------------------------------------------------------------
    | Il legacy hardcodava questi username in operators.php/user_panel.php
    | (vedi critics.md). Qui almeno sono configurabili.
    */

    'agencies_superadmins' => ['Sara Appolloni', 'smp-webmaster'],

    /*
    |--------------------------------------------------------------------------
    | Cron scadenze polizze
    |--------------------------------------------------------------------------
    | days_before: giorni di preavviso default; override per agenzia
    | (il legacy hardcodava [37 => 5] in cron_notifiche_scadenze.php).
    */

    'scadenze_days_before' => 15,
    'scadenze_days_override' => [
        37 => 5,
    ],

];
