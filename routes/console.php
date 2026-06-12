<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler — stessi orari del crontab legacy
|--------------------------------------------------------------------------
| Legacy:  14:10 cron_scadenze.php (backup + fetch AssiEasy)
|          14:30 cron_notifiche_scadenze.php
| Il backup era la prima parte di cron_scadenze: qui è un comando separato
| schedulato per primo nello stesso minuto (esecuzione sequenziale).
*/

Schedule::command('hybrid:backup-db')
    ->dailyAt('14:10')
    ->timezone('Europe/Rome');

Schedule::command('hybrid:scadenze-fetch')
    ->dailyAt('14:10')
    ->timezone('Europe/Rome');

Schedule::command('hybrid:scadenze-notifiche')
    ->dailyAt('14:30')
    ->timezone('Europe/Rome');
