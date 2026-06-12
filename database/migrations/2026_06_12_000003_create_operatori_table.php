<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `operatori`: utenti del pannello agencies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operatori', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->default('');
            $table->string('email', 191)->default('');
            $table->string('password', 255)->default('');     // bcrypt
            $table->dateTime('first_login')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->string('active', 1)->default('0');
            $table->unsignedBigInteger('agid')->nullable();   // id agenzia

            $table->index('agid');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operatori');
    }
};
