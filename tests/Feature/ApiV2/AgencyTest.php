<?php

namespace Tests\Feature\ApiV2;

class AgencyTest extends V2TestCase
{
    public function test_missing_params_422(): void
    {
        $this->get('/res/api/v2/agency.php')
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error' => 'I parametri id e token sono obbligatori.',
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_wrong_token_401(): void
    {
        $agency = $this->makeAgency();

        $this->get('/res/api/v2/agency.php?id='.$agency->id.'&token=sbagliato')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_unknown_agency_401_like_legacy(): void
    {
        $this->get('/res/api/v2/agency.php?id=999&token=x')
            ->assertStatus(401)
            ->assertJsonPath('error', 'Token agenzia non valido.');
    }

    public function test_success_returns_all_56_fields_as_strings_in_legacy_order(): void
    {
        $agency = $this->makeAgency(['os_app_id' => 'os-123', 'colori' => '0xff25347b|0xffdf842c']);

        $response = $this->get('/res/api/v2/agency.php?id='.$agency->id.'&token=agency-token-123')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(56, $data);

        // Ordine chiavi identico alla SELECT legacy
        $this->assertSame(
            ['nome_app', 'nome_agenzia', 'logo_agenzia', 'header_agenzia', 'colori'],
            array_slice(array_keys($data), 0, 5),
        );
        $this->assertSame(['os_app_id', 'os_api_key'], array_slice(array_keys($data), -2));

        // Tutti i valori sono stringhe (anche i null → '')
        foreach ($data as $key => $value) {
            $this->assertIsString($value, "Campo $key non è stringa");
        }
        $this->assertSame('os-123', $data['os_app_id']);
        $this->assertSame('', $data['sintesi_token']);
    }
}
