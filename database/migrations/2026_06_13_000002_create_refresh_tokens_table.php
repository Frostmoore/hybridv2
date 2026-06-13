<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `refresh_tokens`: refresh token per l'auto-login dell'app senza
 * salvare la password (vedi critics.md). Si memorizza solo l'HASH del token
 * (il valore in chiaro è dato all'app una volta sola). Rotazione one-time:
 * a ogni /refresh il token usato viene revocato e ne viene emesso uno nuovo.
 * Tabella interna: mai toccata dall'importer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('agency_id');
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
