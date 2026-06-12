<?php

namespace Tests\Feature\ApiV2;

use App\Models\Polizza;

class PolizzeTest extends V2TestCase
{
    private const URL = '/res/api/v2/polizze/index.php';

    public function test_requires_token(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_user_without_cf_404(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['cf' => '']);

        $this->getJson(self::URL, $this->authHeaders($cliente))
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_agency_without_token_interno_403(): void
    {
        $agency = $this->makeAgency(['token_interno' => null]);
        $cliente = $this->makeCliente($agency);

        $this->getJson(self::URL, $this->authHeaders($cliente))
            ->assertStatus(403)
            ->assertJsonPath('code', 'NOT_CONFIGURED');
    }

    public function test_returns_user_policies_sorted(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        Polizza::create([
            'id_agenzia' => $agency->id, 'cf' => 'RSSMRA80A01H501Z', 'n_polizza' => 'B2',
            'contraente' => 'ROSSI MARIO', 'compagnia' => 'Generali', 'ramo' => 'Auto',
            'data_scadenza_titolo' => '2026-12-31',
        ]);
        Polizza::create([
            'id_agenzia' => $agency->id, 'cf' => 'RSSMRA80A01H501Z', 'n_polizza' => 'A1',
            'data_scadenza_titolo' => '2026-01-01',
        ]);
        // Di un altro CF: esclusa
        Polizza::create(['id_agenzia' => $agency->id, 'cf' => 'ALTRO', 'n_polizza' => 'C3']);
        // Di un'altra agenzia: esclusa
        Polizza::create(['id_agenzia' => 999, 'cf' => 'RSSMRA80A01H501Z', 'n_polizza' => 'D4']);

        $response = $this->getJson(self::URL, $this->authHeaders($cliente))->assertOk();

        $data = $response->json('data');
        // Ordine chiavi del codice legacy: cf, polizze, count
        $this->assertSame(['cf', 'polizze', 'count'], array_keys($data));
        $this->assertSame('RSSMRA80A01H501Z', $data['cf']);
        $this->assertSame(2, $data['count']);
        // Ordinate per data_scadenza_titolo ASC
        $this->assertSame(['A1', 'B2'], array_column($data['polizze'], 'n_polizza'));
        // Tutti i campi stringa
        foreach ($data['polizze'][0] as $key => $value) {
            $this->assertIsString($value, "Campo polizza $key non stringa");
        }
        $this->assertSame(
            ['n_polizza', 'contraente', 'compagnia', 'ramo', 'prodotto', 'targa', 'frazionamento',
                'data_decorrenza', 'data_scadenza_titolo', 'data_scadenza_contratto', 'stato_polizza'],
            array_keys($data['polizze'][0]),
        );
    }
}
