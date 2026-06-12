<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `polizze_importate`: secondo sistema di import (importa_polizze.php
 * + res/import_process.php). Colonne dedotte dall'INSERT del codice legacy.
 * ⚠️ Non esisteva in produzione (import mai usato) — vedi critics.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polizze_importate', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agenzia');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cf', 16)->default('');
            $table->string('numero_polizza', 100)->default('');
            $table->string('desc_ramo', 255)->nullable();
            $table->string('desc_prodotto', 255)->nullable();
            $table->string('compagnia', 255)->nullable();
            $table->string('targa', 30)->nullable();
            $table->date('data_effetto')->nullable();
            $table->date('data_scadenza')->nullable();
            $table->date('data_effetto_titolo')->nullable();  // usata dal cron notifiche
            $table->string('stato', 100)->nullable();
            $table->decimal('premio', 10, 2)->nullable();
            $table->text('raw_data')->nullable();             // riga sorgente JSON
            $table->string('fonte', 50)->default('import');
            $table->string('nome_file', 255)->default('');
            $table->dateTime('importato_il')->useCurrent();

            $table->index(['cf', 'id_agenzia']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polizze_importate');
    }
};
