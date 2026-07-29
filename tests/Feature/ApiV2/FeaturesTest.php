<?php

namespace Tests\Feature\ApiV2;

use App\Models\AgenziaSpeciale;

/**
 * GET custom/features.php — flag delle feature speciali per l'app.
 * Endpoint separato da agency.php proprio per non toccarne il contratto.
 */
class FeaturesTest extends V2TestCase
{
    private const URL = '/res/api/v2/custom/features.php';

    public function test_requires_id_and_token(): void
    {
        $this->getJson(self::URL)
            ->assertStatus(422)
            ->assertJsonPath('error', 'I parametri id e token sono obbligatori.');
    }

    public function test_wrong_token_401(): void
    {
        $agency = $this->makeAgency();

        $this->getJson(self::URL.'?id='.$agency->id.'&token=sbagliato')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_unknown_agency_401(): void
    {
        $this->getJson(self::URL.'?id=99999&token=qualsiasi')->assertStatus(401);
    }

    /** Nessuna riga in agenzie_speciale → tutto spento, non un errore. */
    public function test_defaults_to_all_disabled(): void
    {
        $agency = $this->makeAgency();

        $this->getJson(self::URL.'?id='.$agency->id.'&token='.$agency->token)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consulenza.attiva', false)
            ->assertJsonPath('data.consulenza.titolo', '')
            ->assertJsonPath('data.consulenza.testo', '');
    }

    public function test_returns_enabled_feature_with_texts(): void
    {
        $agency = $this->makeAgency();
        AgenziaSpeciale::create([
            'id_agenzia' => $agency->id,
            'consulenza_attiva' => true,
            'consulenza_titolo' => 'Richiedi una Consulenza',
            'consulenza_testo' => 'Prenota un appuntamento in agenzia',
            'consulenza_mail' => 'consulenze@test.it',
        ]);

        $this->getJson(self::URL.'?id='.$agency->id.'&token='.$agency->token)
            ->assertOk()
            // booleano JSON vero, non la stringa '1' del contratto legacy
            ->assertJsonPath('data.consulenza.attiva', true)
            ->assertJsonPath('data.consulenza.titolo', 'Richiedi una Consulenza')
            ->assertJsonPath('data.consulenza.testo', 'Prenota un appuntamento in agenzia');
    }

    /** La casella email è configurazione interna: non deve uscire verso l'app. */
    public function test_does_not_leak_internal_mail(): void
    {
        $agency = $this->makeAgency();
        AgenziaSpeciale::create([
            'id_agenzia' => $agency->id,
            'consulenza_attiva' => true,
            'consulenza_mail' => 'consulenze@test.it',
        ]);

        $this->getJson(self::URL.'?id='.$agency->id.'&token='.$agency->token)
            ->assertOk()
            ->assertDontSee('consulenze@test.it');
    }

    /** agency.php resta il contratto standard: nessun campo speciale dentro. */
    public function test_agency_endpoint_is_untouched(): void
    {
        $agency = $this->makeAgency();
        AgenziaSpeciale::create(['id_agenzia' => $agency->id, 'consulenza_attiva' => true]);

        $this->getJson('/res/api/v2/agency.php?id='.$agency->id.'&token='.$agency->token)
            ->assertOk()
            ->assertJsonMissingPath('data.consulenza_attiva')
            ->assertJsonMissingPath('data.consulenza');
    }
}
