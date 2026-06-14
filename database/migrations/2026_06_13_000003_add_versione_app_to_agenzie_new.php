<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `agenzie_new.versione_app`: versione dell'app usata dall'agenzia durante la
 * transizione vecchio→nuovo server ('v1' = vecchia app/vecchio server, 'v2' =
 * nuova). Il nuovo server invia le notifiche push (cron scadenze) SOLO alle
 * agenzie 'v2', per non sovrapporsi al vecchio server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenzie_new', function (Blueprint $table) {
            $table->string('versione_app', 8)->default('v1')->after('os_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('agenzie_new', function (Blueprint $table) {
            $table->dropColumn('versione_app');
        });
    }
};
