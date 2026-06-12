<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `notifiche_generali`: notifiche pubbliche per agenzia (con scadenza).
 * L'endpoint general.php filtra `notifica_scadenza >= oggi` (confronto stringa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifiche_generali', function (Blueprint $table) {
            $table->id();
            $table->string('notifica_titolo', 255)->default('');
            $table->text('notifica_testo')->nullable();
            $table->string('notifica_link', 255)->nullable();
            $table->string('notifica_immagine', 255)->nullable();
            $table->dateTime('notifica_scadenza')->nullable();
            $table->unsignedBigInteger('notifica_agid')->nullable();

            $table->index('notifica_agid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifiche_generali');
    }
};
