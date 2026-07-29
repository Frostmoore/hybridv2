<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `agenzie_speciale`: interruttori delle implementazioni FUORI CONTRATTO
 * richieste da singole agenzie (una riga per agenzia, una colonna per feature).
 *
 * Perché una tabella a parte e non colonne su `agenzie_new`:
 *  1. `hybrid:import-legacy` fa TRUNCATE di ogni tabella dell'export prima di
 *     reinserire: queste colonne non esistono nel legacy, quindi un re-import
 *     azzererebbe silenziosamente tutte le configurazioni speciali. Qui la
 *     tabella è in LegacyRowSanitizer::PROTECTED_TABLES → mai toccata.
 *  2. `agenzie_new` è lo specchio della tabella legacy (la stessa che il sync
 *     dal vecchio server aggiorna con updateOrInsert): tenerla pulita da roba
 *     nostra evita ambiguità su cosa è legacy e cosa no.
 *
 * La riga si crea on-demand al primo salvataggio dal pannello admin: assenza di
 * riga = tutte le feature speciali spente (nessun backfill sulle agenzie note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenzie_speciale', function (Blueprint $table) {
            // PK = id dell'agenzia (1:1 con agenzie_new.id, nessuna FK come nel
            // resto dello schema: la cascata è in AgencyAdminController::destroy)
            $table->unsignedBigInteger('id_agenzia')->primary();

            // ── Feature "consulenza" (agenzie con appuntamento su richiesta) ──
            $table->boolean('consulenza_attiva')->default(false);
            $table->string('consulenza_titolo', 255)->default('');
            $table->string('consulenza_testo', 255)->default('');
            // Vuota → si usa la stessa catena dei preventivi (denuncia_mail || quick_email)
            $table->string('consulenza_mail', 191)->default('');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenzie_speciale');
    }
};
