<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `preventivi`: richieste di preventivo (no tipo_sinistro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventivi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agenzia')->nullable();
            $table->string('nome_denuncia', 255)->default('');
            $table->string('email_denuncia', 191)->default('');
            $table->string('documenti_denuncia', 255)->default('');
            $table->string('data_denuncia', 32)->default('');
            $table->text('descrizione_denuncia')->nullable();
            $table->string('privacy_denuncia', 8)->default('');

            $table->index('id_agenzia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventivi');
    }
};
