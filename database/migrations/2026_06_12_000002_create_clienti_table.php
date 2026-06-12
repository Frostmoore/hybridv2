<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `clienti`: utenti delle app.
 * Date e privacy come stringhe per preservare i formati misti legacy
 * (datadinascita 'd/m/Y' o 'Y-m-d', privacy* "valore|timestamp").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clienti', function (Blueprint $table) {
            $table->id();

            $table->string('username', 100)->default('');
            $table->string('password', 255)->default('');     // bcrypt (60), margine
            $table->string('email', 191)->default('');
            $table->string('telefono', 30)->nullable();
            $table->string('nome', 100)->default('');
            $table->string('cognome', 100)->default('');
            $table->string('cf', 32)->nullable();
            $table->string('piva', 32)->nullable();
            $table->string('datadinascita', 32)->nullable();  // formato misto → stringa
            $table->unsignedBigInteger('agenziaid')->nullable();
            $table->string('playerid', 100)->nullable();

            // Consensi privacy: "1|YYYY-MM-DD HH:MM:SS" (o "1|d/m/Y")
            $table->string('privacyuno', 64)->nullable();
            $table->string('privacydue', 64)->nullable();
            $table->string('privacytre', 64)->nullable();
            $table->string('privacyquattro', 64)->nullable();

            $table->dateTime('firstlogin')->nullable();
            $table->dateTime('lastlogin')->nullable();
            $table->string('codiceagenzia', 64)->nullable();
            $table->string('active', 1)->nullable();          // '0'/'1'/null nel legacy
            $table->string('activation_token', 64)->nullable();

            $table->index('agenziaid');
            $table->index(['agenziaid', 'cf']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clienti');
    }
};
