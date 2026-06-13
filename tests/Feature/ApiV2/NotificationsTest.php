<?php

namespace Tests\Feature\ApiV2;

use App\Models\Notifica;
use App\Models\NotificaGenerale;

class NotificationsTest extends V2TestCase
{
    // ─── GET notifications/index.php ─────────────────────────────────────

    public function test_index_requires_token(): void
    {
        $this->getJson('/res/api/v2/notifications/index.php')->assertStatus(401);
    }

    public function test_index_filters_by_destinatari_and_agency(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $cliente = $this->makeCliente($agency);

        // per lui, sua agenzia → visibile
        $mine = Notifica::create([
            'titolo' => 'Per Mario', 'contenuto' => 'Testo1',
            'destinatari' => 'mario.rossi,altro,', 'letta_da' => '',
            'agenziaid' => $agency->id, 'dataora' => '2026-06-01 10:00:00',
            'link' => 'https://x.it', 'immagine' => '',
        ]);
        // non destinatario → invisibile
        Notifica::create([
            'titolo' => 'Per altri', 'contenuto' => 'x',
            'destinatari' => 'qualcunaltro,', 'agenziaid' => $agency->id,
        ]);
        // altra agenzia (anche se destinatario) → invisibile (fix vs legacy)
        Notifica::create([
            'titolo' => 'Altra agenzia', 'contenuto' => 'x',
            'destinatari' => 'mario.rossi,', 'agenziaid' => $other->id,
        ]);

        $response = $this->getJson('/res/api/v2/notifications/index.php', $this->authHeaders($cliente))
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame([
            'id' => (string) $mine->id,
            'titolo' => 'Per Mario',
            'testo' => 'Testo1',           // ← mappato da `contenuto`
            'link' => 'https://x.it',
            'immagine' => '',
            'dataora' => '2026-06-01 10:00:00',
            'letta' => false,
        ], $data[0]);
    }

    public function test_index_letta_flag(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        Notifica::create([
            'titolo' => 'Letta', 'contenuto' => 'x',
            'destinatari' => 'mario.rossi,', 'letta_da' => 'mario.rossi,',
            'agenziaid' => $agency->id,
        ]);

        $this->getJson('/res/api/v2/notifications/index.php', $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonPath('data.0.letta', true);
    }

    // ─── GET notifications/general.php ───────────────────────────────────

    public function test_general_missing_agency_422(): void
    {
        $this->getJson('/res/api/v2/notifications/general.php')
            ->assertStatus(422)
            ->assertJsonPath('error', 'Il parametro agency_id è obbligatorio.');
    }

    public function test_general_filters_by_agency_and_expiry(): void
    {
        $agency = $this->makeAgency();
        $valid = NotificaGenerale::create([
            'notifica_titolo' => 'Valida', 'notifica_testo' => 'x',
            'notifica_scadenza' => now()->addDay(), 'notifica_agid' => $agency->id,
        ]);
        NotificaGenerale::create([
            'notifica_titolo' => 'Scaduta', 'notifica_testo' => 'x',
            'notifica_scadenza' => now()->subYear(), 'notifica_agid' => $agency->id,
        ]);
        NotificaGenerale::create([
            'notifica_titolo' => 'AltraAgenzia', 'notifica_testo' => 'x',
            'notifica_scadenza' => now()->addDay(), 'notifica_agid' => 999,
        ]);

        $response = $this->getJson('/res/api/v2/notifications/general.php?agency_id='.$agency->id)
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Valida', $data[0]['titolo']);
        $this->assertSame((string) $valid->id, $data[0]['id']);
        $this->assertSame(
            ['id', 'titolo', 'testo', 'link', 'immagine', 'scadenza'],
            array_keys($data[0]),
        );
    }

    // ─── GET notifications/single.php ────────────────────────────────────

    public function test_single_not_found_404(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->getJson('/res/api/v2/notifications/single.php?id=999', $this->authHeaders($cliente))
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_single_returns_notification(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        $n = Notifica::create([
            'titolo' => 'Una', 'contenuto' => 'Testo', 'destinatari' => 'mario.rossi,',
            'letta_da' => '', 'agenziaid' => $agency->id, 'dataora' => '2026-06-01 10:00:00',
        ]);

        $this->getJson('/res/api/v2/notifications/single.php?id='.$n->id, $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonPath('data.titolo', 'Una')
            ->assertJsonPath('data.testo', 'Testo')
            ->assertJsonPath('data.letta', false);
    }

    public function test_single_other_agency_is_404(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $cliente = $this->makeCliente($agency);

        // Notifica di un'altra agenzia: anche col proprio username tra i destinatari
        // non deve essere leggibile (IDOR). 404 per non rivelarne l'esistenza.
        $altrui = Notifica::create([
            'titolo' => 'Segreta', 'contenuto' => 'x', 'destinatari' => 'mario.rossi,',
            'agenziaid' => $other->id,
        ]);

        $this->getJson('/res/api/v2/notifications/single.php?id='.$altrui->id, $this->authHeaders($cliente))
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_single_non_recipient_is_404(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        // Stessa agenzia ma l'utente non è tra i destinatari → 404
        $n = Notifica::create([
            'titolo' => 'Non per lui', 'contenuto' => 'x', 'destinatari' => 'qualcunaltro,',
            'agenziaid' => $agency->id,
        ]);

        $this->getJson('/res/api/v2/notifications/single.php?id='.$n->id, $this->authHeaders($cliente))
            ->assertStatus(404);
    }

    // ─── POST notifications/read.php ─────────────────────────────────────

    public function test_read_marks_notification_clean_csv(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        $n = Notifica::create([
            'titolo' => 'X', 'contenuto' => 'x', 'destinatari' => 'mario.rossi,',
            'letta_da' => 'qualcuno,', 'agenziaid' => $agency->id,
        ]);

        $this->postJson('/res/api/v2/notifications/read.php', ['id' => $n->id], $this->authHeaders($cliente))
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['id' => $n->id, 'letta' => true]]);

        // CSV pulito: nessuna virgola finale (la voce vuota legacy viene scartata)
        $this->assertSame('qualcuno,mario.rossi', $n->fresh()->letta_da);
    }

    public function test_recipient_match_is_exact_not_substring(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);   // username: mario.rossi

        // 'mario.rossi' è SOTTOSTRINGA di 'mario.rossi2': non deve renderlo destinatario
        $n = Notifica::create([
            'titolo' => 'Per un altro', 'contenuto' => 'x',
            'destinatari' => 'mario.rossi2,supermario,', 'agenziaid' => $agency->id,
        ]);

        // index: non compare
        $this->getJson('/res/api/v2/notifications/index.php', $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // single: 404
        $this->getJson('/res/api/v2/notifications/single.php?id='.$n->id, $this->authHeaders($cliente))
            ->assertStatus(404);
    }

    public function test_read_is_idempotent(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        $n = Notifica::create([
            'titolo' => 'X', 'contenuto' => 'x', 'destinatari' => 'mario.rossi,',
            'letta_da' => 'mario.rossi,', 'agenziaid' => $agency->id,
        ]);

        $this->postJson('/res/api/v2/notifications/read.php', ['id' => $n->id], $this->authHeaders($cliente))
            ->assertOk();

        $this->assertSame('mario.rossi,', $n->fresh()->letta_da);
    }

    public function test_read_unknown_id_404(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->postJson('/res/api/v2/notifications/read.php', ['id' => 12345], $this->authHeaders($cliente))
            ->assertStatus(404);
    }

    public function test_read_other_agency_is_404_and_does_not_mutate(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $cliente = $this->makeCliente($agency);

        // Notifica di un'altra agenzia: l'utente non deve poterla marcare letta
        // (IDOR in scrittura). 404 e letta_da intatto.
        $altrui = Notifica::create([
            'titolo' => 'Segreta', 'contenuto' => 'x', 'destinatari' => 'mario.rossi,',
            'letta_da' => 'pippo,', 'agenziaid' => $other->id,
        ]);

        $this->postJson('/res/api/v2/notifications/read.php', ['id' => $altrui->id], $this->authHeaders($cliente))
            ->assertStatus(404);

        $this->assertSame('pippo,', $altrui->fresh()->letta_da);
    }
}
