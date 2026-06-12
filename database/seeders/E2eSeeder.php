<?php

namespace Database\Seeders;

use App\Models\AgenziaNew;
use App\Models\NotificaGenerale;
use App\Models\Operatore;
use App\Models\Utente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures per il test end-to-end locale (scripts/e2e-local.ps1).
 * Crea SOLO record marcati "E2E" (upsert idempotente): è pensato per girare
 * sopra il DB popolato coi dati di produzione senza toccarli.
 * Le chiavi OneSignal sono FINTE di proposito: nessun push reale.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $agency = AgenziaNew::updateOrCreate(['nome_agenzia' => 'E2E Test Agency'], [
            'nome_app' => 'E2E Test App',
            'token' => 'E2ETOKEN123',
            'token_interno' => 'E2EINTERNO456',
            'attiva' => '1',
            'quick_email' => 'quick.e2e@example.test',
            'denuncia_mail' => 'sinistri.e2e@example.test',
            'info_email_sedi' => 'info.e2e@example.test',
            'os_app_id' => 'e2e-fake-app-id',
            'os_api_key' => 'e2e-fake-api-key',
            'privacy_agenzia' => 'https://example.test/privacy',
        ]);

        NotificaGenerale::updateOrCreate(
            ['notifica_agid' => $agency->id, 'notifica_titolo' => 'E2E Notifica Generale'],
            ['notifica_testo' => 'Visibile nel test e2e.', 'notifica_scadenza' => now()->addYear()],
        );

        Utente::updateOrCreate(['nomeutente' => 'e2e.admin'], [
            'password' => Hash::make('E2ePass!1'),
            'email' => 'e2e.admin@example.test',
            'authorized' => '1',
            'activation_code' => 'activated',
        ]);

        Operatore::updateOrCreate(['email' => 'e2e.operatore@example.test'], [
            'username' => 'e2e.operatore',
            'password' => Hash::make('E2ePass!1'),
            'active' => '1',
            'agid' => $agency->id,
        ]);

        $this->command?->info('E2E agency id: '.$agency->id);
    }
}
