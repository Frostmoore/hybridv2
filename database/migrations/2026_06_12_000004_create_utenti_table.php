<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `utenti`: admin del pannello principale.
 * In produzione contiene un solo record (amministrazione).
 * La registrazione admin NON viene portata (decisione §7.6): gli admin
 * arrivano solo via import dati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utenti', function (Blueprint $table) {
            $table->id();
            $table->string('nomeutente', 100)->default('');
            $table->string('password', 255)->default('');     // bcrypt
            $table->string('hash', 64)->nullable();
            $table->string('email', 191)->default('');
            $table->string('last_connected', 32)->nullable(); // legacy: microsecondi → stringa
            $table->string('authorized', 1)->default('0');
            $table->string('activation_code', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utenti');
    }
};
