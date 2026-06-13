<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `scadenze_notificate`: deduplica delle notifiche di scadenza polizza.
 * Garantisce che ogni coppia (id_polizza, data_effetto_titolo) sia notificata
 * UNA sola volta, anche con la finestra di recupero del cron (vedi critics.md).
 * Tabella interna (non proviene dal legacy): mai toccata dall'importer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scadenze_notificate', function (Blueprint $table) {
            $table->id();
            $table->string('id_polizza', 64);
            $table->string('data_effetto_titolo', 32);
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->dateTime('notified_at');

            $table->unique(['id_polizza', 'data_effetto_titolo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scadenze_notificate');
    }
};
