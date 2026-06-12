<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `agenzie_new`: configurazione white-label per agenzia.
 * Schema ricostruito dal dump di produzione (backup JSON) + codice v2.
 * Stringhe e TEXT dove i valori sono multi-campo separati da `|` o lunghi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenzie_new', function (Blueprint $table) {
            $table->id();

            $table->string('nome_app', 100)->default('');
            $table->string('nome_agenzia', 100)->default('');

            // Social
            $table->string('facebook_agenzia', 255)->default('');
            $table->string('instagram_agenzia', 255)->default('');
            $table->string('linkedin_agenzia', 255)->default('');
            $table->string('google_agenzia', 255)->default('');
            $table->string('sito_agenzia', 255)->default('');

            // Info & sedi (multi-sede separate da `|`)
            $table->string('info_titolo', 100)->default('');
            $table->string('info_immagine', 255)->default('');
            $table->text('info_nomi_sedi')->nullable();
            $table->text('info_indirizzi_sedi')->nullable();
            $table->text('info_testo_orari')->nullable();
            $table->text('info_orari_sedi')->nullable();
            $table->text('info_recensioni_sedi')->nullable();
            $table->text('info_telefono_sedi')->nullable();
            $table->text('info_email_sedi')->nullable();
            $table->text('info_mappa_sedi')->nullable();
            $table->text('info_sito_sedi')->nullable();

            // Notifica "vetrina" (campi sull'agenzia, spesso vuoti)
            $table->string('notifica_titolo', 255)->default('');
            $table->text('notifica_testo')->nullable();
            $table->string('notifica_link', 255)->default('');
            $table->string('notifica_immagine', 255)->default('');
            $table->string('notifica_scadenza', 30)->default('');

            // Contatti / numeri utili (categorie separate da `|`, voci "Label.Numero")
            $table->string('contatti_titolo', 100)->default('');
            $table->string('contatti_immagine', 255)->default('');
            $table->string('numeri_utili_labels', 255)->default('');
            $table->string('numeri_utili_colori', 255)->default('');
            $table->text('numeri_utili_salute')->nullable();
            $table->text('numeri_utili_assistenza')->nullable();
            $table->text('numeri_utili_noleggio')->nullable();

            // Sezioni azione
            $table->string('denuncia_titolo', 100)->default('');
            $table->string('denuncia_testo_grassetto', 255)->default('');
            $table->string('denuncia_immagine', 255)->default('');
            $table->string('denuncia_mail', 255)->default('');
            $table->string('preventivo_titolo', 100)->default('');
            $table->string('preventivo_testo_grassetto', 255)->default('');
            $table->string('preventivo_immagine', 255)->default('');
            $table->string('documento_titolo', 100)->default('');
            $table->string('documento_testo_grassetto', 255)->default('');
            $table->string('documento_immagine', 255)->default('');

            // Contatti rapidi
            $table->string('quick_telefono', 50)->default('');
            $table->string('quick_whatsapp', 255)->default('');
            $table->string('quick_email', 255)->default('');

            // Palette (formato "0xAARRGGBB|0x..."), logo/header
            $table->text('colori')->nullable();
            $table->string('logo_agenzia', 255)->default('');
            $table->string('header_agenzia', 255)->default('');

            // Stato / privacy / codici
            $table->string('attiva', 1)->default('1');
            $table->string('privacy_agenzia', 255)->default('');
            $table->string('codiceagenzia', 64)->default('');

            // Token
            $table->string('token', 64)->nullable();            // token pubblico app
            $table->string('token_interno', 64)->nullable();    // token API polizze

            // Servizi esterni
            $table->string('assisecret', 64)->nullable();
            $table->string('assiurl', 100)->nullable();
            $table->string('sintesi_token', 191)->nullable();
            $table->string('sintesi_lic', 32)->nullable();
            $table->string('sintesi_azi', 32)->nullable();
            $table->string('sintesi_age', 32)->nullable();
            $table->string('os_app_id', 64)->nullable();
            $table->string('os_api_key', 191)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenzie_new');
    }
};
