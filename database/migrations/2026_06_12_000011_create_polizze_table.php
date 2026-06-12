<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `polizze`: polizze importate via import_polizze.php (sistema B).
 * Porting 1:1 di legacy database_polizze.sql.
 * ⚠️ Non esisteva in produzione (import mai usato) — vedi critics.md.
 * Date come stringhe (l'import salva 'dd/mm/yyyy').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polizze', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agenzia');
            $table->string('cf', 16);
            $table->string('contraente', 255)->default('');
            $table->string('n_polizza', 100)->default('');
            $table->string('compagnia', 255)->default('');
            $table->string('ramo', 255)->default('');
            $table->string('prodotto', 255)->default('');
            $table->string('targa', 30)->default('');
            $table->string('frazionamento', 50)->default('');
            $table->string('data_decorrenza', 20)->default('');
            $table->string('data_scadenza_titolo', 20)->default('');
            $table->string('data_scadenza_contratto', 20)->default('');
            $table->string('stato_polizza', 100)->default('');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Upsert key legacy: n_polizza + agenzia (prefisso 80 come l'originale)
            $table->unique(['n_polizza', 'id_agenzia'], 'uniq_polizza_agenzia');
            $table->index(['cf', 'id_agenzia'], 'idx_cf_agenzia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polizze');
    }
};
