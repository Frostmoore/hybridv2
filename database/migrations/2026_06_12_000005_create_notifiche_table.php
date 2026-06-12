<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `notifiche`: notifiche private (in-app + push).
 * `destinatari` e `letta_da` sono CSV di username (spesso con virgola finale).
 * `dataora` ha formati misti nel legacy ('20-12-2024' o datetime) → stringa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifiche', function (Blueprint $table) {
            $table->id();
            $table->string('titolo', 255)->default('');
            $table->text('contenuto')->nullable();
            $table->string('immagine', 255)->nullable();
            $table->text('destinatari')->nullable();          // CSV username
            $table->text('letta_da')->nullable();             // CSV username
            $table->string('link', 512)->nullable();
            $table->string('testolink', 64)->nullable();
            $table->string('dataora', 32)->nullable();        // formato misto → stringa
            $table->unsignedBigInteger('agenziaid')->nullable();

            $table->index('agenziaid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifiche');
    }
};
