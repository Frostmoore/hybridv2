<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `delete_requests`: richieste di cancellazione account (flusso store).
 * Token valido 60 minuti (campo `expiration`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delete_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->default('');
            $table->string('token', 64)->default('');
            $table->dateTime('expiration')->nullable();

            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delete_requests');
    }
};
