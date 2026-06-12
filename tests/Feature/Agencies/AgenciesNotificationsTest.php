<?php

namespace Tests\Feature\Agencies;

use App\Models\Notifica;
use App\Models\NotificaGenerale;
use App\Models\Operatore;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Feature\ApiV2\V2TestCase;

class AgenciesNotificationsTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_agencies').'/'.ltrim($path, '/');
    }

    private function operatore(int $agid): Operatore
    {
        return Operatore::create([
            'username' => 'op.notifiche', 'email' => 'opn@test.it',
            'password' => Hash::make('x'), 'active' => '1', 'agid' => $agid,
        ]);
    }

    /** Da chiamare nei test che si aspettano un push riuscito. */
    private function fakeOneSignalOk(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'fake-os-id'], 200)]);
    }

    // ─── send_notification_all ───────────────────────────────────────────

    public function test_send_all_inserts_both_tables_and_pushes(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency(['os_app_id' => 'os-app', 'os_api_key' => 'os-key']);
        $this->makeCliente($agency, ['username' => 'cliente.uno']);
        $this->makeCliente($agency, ['username' => 'cliente.due', 'email' => 'due@test.it', 'cf' => 'DUE']);
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_all.php'), [
            'agenziaid' => $agency->id,
            'titolo' => 'Avviso generale',
            'testo' => 'Testo avviso',
            'link' => 'https://esempio.it/promo',
            'notifica_scadenza' => '2027-01-01 12:00:00',
        ])->assertOk()
            ->assertJsonPath('success', true);

        // notifiche: destinatari = CSV di tutti gli username dell'agenzia
        $n = Notifica::latest('id')->first();
        $this->assertSame('Avviso generale', $n->titolo);
        $this->assertSame('Testo avviso', $n->contenuto);
        $this->assertStringContainsString('cliente.uno', $n->destinatari);
        $this->assertStringContainsString('cliente.due', $n->destinatari);
        $this->assertSame('Visita Ora!', $n->testolink);

        // notifiche_generali
        $g = NotificaGenerale::latest('id')->first();
        $this->assertSame('Avviso generale', $g->notifica_titolo);
        $this->assertSame((int) $agency->id, (int) $g->notifica_agid);

        // Push a tutti gli iscritti
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'onesignal')
                && $request['app_id'] === 'os-app'
                && $request['included_segments'] === ['Total Subscriptions']
                && $request['headings']['en'] === 'Avviso generale'
                && $request->hasHeader('Authorization', 'Key os-key');
        });
    }

    public function test_send_all_requires_scadenza(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency();
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_all.php'), [
            'agenziaid' => $agency->id, 'titolo' => 'X', 'testo' => 'Y',
        ])->assertJsonPath('success', false);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_all.php'), [
            'agenziaid' => $agency->id, 'titolo' => 'X', 'testo' => 'Y', 'notifica_scadenza' => 'data-sbagliata',
        ])->assertJsonPath('message', 'Data di scadenza non valida.');
    }

    public function test_send_all_without_onesignal_config(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency(['os_app_id' => null, 'os_api_key' => null]);
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_all.php'), [
            'agenziaid' => $agency->id, 'titolo' => 'X', 'testo' => 'Y', 'notifica_scadenza' => '2027-01-01 12:00:00',
        ])->assertJsonPath('message', 'Configurazione OneSignal mancante per questa agenzia.');
    }

    // ─── send_notification_private ───────────────────────────────────────

    public function test_send_private_resolves_username_by_playerid(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency(['os_app_id' => 'os-app', 'os_api_key' => 'os-key']);
        $cliente = $this->makeCliente($agency, ['playerid' => 'player-mario']);
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_private.php'), [
            'agenziaid' => $agency->id,
            'playerid' => 'player-mario',
            'titolo' => 'Solo per te',
            'testo' => 'Messaggio privato',
        ])->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Notifica salvata e inviata con successo!']);

        $n = Notifica::latest('id')->first();
        $this->assertSame('mario.rossi', $n->destinatari);   // username, non playerid
        $this->assertNull(NotificaGenerale::latest('id')->first());   // NIENTE notifiche_generali

        Http::assertSent(fn ($request) => $request['include_external_user_ids'] === ['player-mario']);
    }

    public function test_send_private_unknown_recipient(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency();
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_private.php'), [
            'agenziaid' => $agency->id, 'playerid' => 'ignoto', 'titolo' => 'X', 'testo' => 'Y',
        ])->assertJsonPath('message', 'Destinatario non trovato.');
    }

    // ─── send_notification_selected ──────────────────────────────────────

    public function test_send_selected_multiple_recipients(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency(['os_app_id' => 'os-app', 'os_api_key' => 'os-key']);
        $this->makeCliente($agency, ['username' => 'primo', 'playerid' => 'p1']);
        $this->makeCliente($agency, ['username' => 'secondo', 'email' => 's@t.it', 'cf' => 'SEC', 'playerid' => 'p2']);
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_selected.php'), [
            'agenziaid' => $agency->id,
            'playerid' => ['p1', 'p2'],
            'titolo' => 'Per alcuni',
            'testo' => 'Testo selezionati',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $n = Notifica::latest('id')->first();
        $this->assertSame('primo,secondo', $n->destinatari);

        Http::assertSent(fn ($request) => $request['include_external_user_ids'] === ['p1', 'p2']);
    }

    public function test_send_selected_requires_recipients(): void
    {
        $this->fakeOneSignalOk();
        $agency = $this->makeAgency();
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_selected.php'), [
            'agenziaid' => $agency->id, 'titolo' => 'X', 'testo' => 'Y',
        ])->assertJsonPath('message', 'Campi obbligatori mancanti o nessun destinatario.');
    }

    public function test_onesignal_error_is_reported(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['errors' => ['App non valida']], 400)]);
        $agency = $this->makeAgency(['os_app_id' => 'os-app', 'os_api_key' => 'os-key']);
        $this->makeCliente($agency, ['playerid' => 'p1']);
        $op = $this->operatore($agency->id);

        $this->actingAs($op, 'operatore')->post($this->host('api/v1/send_notification_private.php'), [
            'agenziaid' => $agency->id, 'playerid' => 'p1', 'titolo' => 'X', 'testo' => 'Y',
        ])->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Errore invio notifica OneSignal. App non valida');
    }

    public function test_guest_cannot_send(): void
    {
        $this->post($this->host('api/v1/send_notification_all.php'), [])->assertRedirect();
    }
}
