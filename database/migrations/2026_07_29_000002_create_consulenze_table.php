<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `consulenze`: richieste di consulenza con appuntamento (feature
 * speciale, vedi agenzie_speciale.consulenza_attiva).
 *
 * NON è una tabella legacy: non arriva dall'export, non passa dal sync, non
 * viene mai importata → qui usiamo tipi veri (boolean/datetime/timestamps)
 * invece delle stringhe che `preventivi` & co. si portano dietro dal legacy.
 * A differenza di `preventivi`, telefono e indirizzo sono PERSISTITI: su una
 * richiesta di appuntamento serve poter richiamare la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consulenze', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agenzia');
            $table->string('nome', 255)->default('');
            $table->string('cognome', 255)->default('');
            $table->string('email', 191)->default('');
            $table->string('telefono', 255)->default('');
            $table->string('indirizzo', 255)->default('');
            $table->text('descrizione')->nullable();
            $table->boolean('privacy')->default(false);
            // data_appuntamento + ora_appuntamento dell'app, unite lato server
            $table->dateTime('appuntamento_il');
            // Path relativo allo ZIP degli allegati (storage locale, mai via URL)
            $table->string('documenti', 255)->default('');
            $table->timestamps();

            $table->index('id_agenzia');
            $table->index('appuntamento_il');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulenze');
    }
};
