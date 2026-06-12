<?php

namespace Database\Seeders;

use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\NotificaGenerale;
use App\Models\Operatore;
use App\Models\Utente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dati minimi per lo sviluppo locale (NON per produzione).
 * Riproduce un'agenzia di test stile Assidim (id 6), un cliente attivo,
 * un operatore e l'admin. Lanciare con: php artisan db:seed --class=DevSeeder
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        $agenzia = AgenziaNew::updateOrCreate(['id' => 6], [
            'nome_app' => 'Assidim Dev',
            'nome_agenzia' => 'Assidim Dev',
            'info_titolo' => 'Sedi e contatti',
            'numeri_utili_labels' => 'Salute|Assistenza stradale|Noleggio',
            'numeri_utili_colori' => '0xff44d267|0xff960f16|0xff0050b4',
            'numeri_utili_salute' => 'UniSalute.800016635',
            'numeri_utili_assistenza' => 'Unipol.800279279',
            'numeri_utili_noleggio' => 'UnipolRental.800936603',
            'denuncia_titolo' => 'Denuncia un Sinistro',
            'preventivo_titolo' => 'Richiedi un Preventivo',
            'documento_titolo' => 'Carica i tuoi documenti',
            'colori' => '0xff25347b|0xffdf842c|0xffdf842c',
            'token' => 'GwAqon0pX',
            'token_interno' => 'devtokeninterno000000000000000000',
            'attiva' => '1',
            'quick_email' => 'dev@assidim.test',
            'denuncia_mail' => 'sinistri@assidim.test',
            'info_email_sedi' => 'info@assidim.test',
        ]);

        Cliente::updateOrCreate(
            ['agenziaid' => $agenzia->id, 'username' => 'mario.rossi'],
            [
                'password' => Hash::make('password'),
                'email' => 'mario.rossi@assidim.test',
                'nome' => 'Mario',
                'cognome' => 'Rossi',
                'cf' => 'RSSMRA80A01H501Z',
                'datadinascita' => '1980-01-01',
                'playerid' => 'dev-player-mario',
                'privacyuno' => '1|2026-01-01 10:00:00',
                'privacydue' => '1|2026-01-01 10:00:00',
                'privacytre' => '0|2026-01-01 10:00:00',
                'privacyquattro' => '0|2026-01-01 10:00:00',
                'active' => '1',
            ]
        );

        // Cliente non ancora attivato (per testare ACCOUNT_INACTIVE)
        Cliente::updateOrCreate(
            ['agenziaid' => $agenzia->id, 'username' => 'luigi.verdi'],
            [
                'password' => Hash::make('password'),
                'email' => 'luigi.verdi@assidim.test',
                'nome' => 'Luigi',
                'cognome' => 'Verdi',
                'cf' => 'VRDLGU85B02H501Y',
                'active' => '0',
                'activation_token' => str_repeat('a', 32),
            ]
        );

        NotificaGenerale::updateOrCreate(
            ['notifica_agid' => $agenzia->id, 'notifica_titolo' => 'Benvenuto'],
            [
                'notifica_testo' => 'Notifica generale di prova.',
                'notifica_scadenza' => now()->addYear(),
            ]
        );

        Operatore::updateOrCreate(
            ['email' => 'operatore@assidim.test'],
            [
                'username' => 'operatore.dev',
                'password' => Hash::make('password'),
                'active' => '1',
                'agid' => $agenzia->id,
            ]
        );

        Utente::updateOrCreate(
            ['nomeutente' => 'amministrazione'],
            [
                'password' => Hash::make('password'),
                'email' => 'admin@hybridandgogsv2.test',
                'authorized' => '1',
                'activation_code' => 'activated',
            ]
        );
    }
}
