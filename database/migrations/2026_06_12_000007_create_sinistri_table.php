<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `sinistri`: denunce sinistro.
 * `data_denuncia` formato misto ('d/m/Y' dai form web, 'Y-m-d' dalla v2) → stringa.
 * `privacy_denuncia` 'on'/'1' → stringa. Legacy: id manuale (qui AUTO_INCREMENT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinistri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agenzia')->nullable();
            $table->string('nome_denuncia', 255)->default('');
            $table->string('tipo_sinistro', 64)->default('');
            $table->string('email_denuncia', 191)->default('');
            $table->string('documenti_denuncia', 255)->default('');  // path zip
            $table->string('data_denuncia', 32)->default('');
            $table->text('descrizione_denuncia')->nullable();
            $table->string('privacy_denuncia', 8)->default('');

            $table->index('id_agenzia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinistri');
    }
};
