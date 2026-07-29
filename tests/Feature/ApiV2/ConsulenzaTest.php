<?php

namespace Tests\Feature\ApiV2;

use App\Mail\LegacyHtmlMail;
use App\Models\AgenziaNew;
use App\Models\AgenziaSpeciale;
use App\Models\Consulenza;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * POST claims/consulenza.php — endpoint SPECIALE (fuori contratto v2).
 * Verifica in particolare il gate: per le agenzie non abilitate l'endpoint
 * deve essere indistinguibile da una route inesistente.
 */
class ConsulenzaTest extends V2TestCase
{
    private const URL = '/res/api/v2/claims/consulenza.php';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function enable(AgenziaNew $agency, array $overrides = []): AgenziaSpeciale
    {
        return AgenziaSpeciale::create(array_merge([
            'id_agenzia' => $agency->id,
            'consulenza_attiva' => true,
            'consulenza_titolo' => 'Richiedi una Consulenza',
            'consulenza_testo' => 'Prenota un appuntamento',
            'consulenza_mail' => 'consulenze@test.it',
        ], $overrides));
    }

    /** Data futura, per non far scadere i test col passare del tempo. */
    private function futuro(): Carbon
    {
        return Carbon::now()->addDays(7)->setTime(15, 30);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $quando = $this->futuro();

        return array_merge([
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'Mario@Test.IT',
            'telefono' => '3331234567',
            'indirizzo' => 'Via Roma 1, Milano',
            'descrizione' => 'Vorrei parlare della polizza casa',
            'privacy' => '1',
            'data_appuntamento' => $quando->format('Y-m-d'),
            'ora_appuntamento' => $quando->format('H:i'),
        ], $overrides);
    }

    // ─── Auth e gate ─────────────────────────────────────────────────────

    public function test_requires_token(): void
    {
        $this->post(self::URL)->assertStatus(401);
    }

    public function test_404_when_agency_has_no_special_row(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(404)
            // Stesso corpo del 404 generico: non si deve capire che l'endpoint esiste
            ->assertJsonPath('error', 'Risorsa non trovata.')
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame(0, Consulenza::count());
    }

    public function test_404_when_feature_disabled(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency, ['consulenza_attiva' => false]);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(404)
            ->assertJsonPath('error', 'Risorsa non trovata.');

        $this->assertSame(0, Consulenza::count());
        Mail::assertNothingSent();
    }

    // ─── Validazione ─────────────────────────────────────────────────────

    public function test_invalid_data_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => 'non-json'], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Campo POST[data] mancante o JSON non valido.');
    }

    public function test_requires_email_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload(['email' => '']))], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Il campo email è obbligatorio.');
    }

    public function test_requires_appuntamento_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, [
            'data' => json_encode($this->payload(['ora_appuntamento' => ''])),
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'I campi data_appuntamento e ora_appuntamento sono obbligatori.');
    }

    /** 31 febbraio: PHP lo farebbe scivolare a marzo, noi lo rifiutiamo. */
    public function test_rejects_rollover_date_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, [
            'data' => json_encode($this->payload([
                'data_appuntamento' => Carbon::now()->addYear()->format('Y').'-02-31',
            ])),
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame(0, Consulenza::count());
    }

    public function test_rejects_malformed_time_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, [
            'data' => json_encode($this->payload(['ora_appuntamento' => '9.30'])),
        ], $this->authHeaders($cliente))
            ->assertStatus(422);
    }

    public function test_rejects_past_appointment_422(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $ieri = Carbon::now()->subDay();

        $this->post(self::URL, [
            'data' => json_encode($this->payload([
                'data_appuntamento' => $ieri->format('Y-m-d'),
                'ora_appuntamento' => '10:00',
            ])),
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'L\'appuntamento non può essere nel passato.');

        $this->assertSame(0, Consulenza::count());
    }

    // ─── Successo ────────────────────────────────────────────────────────

    public function test_success_creates_row_zip_and_mail(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);
        $quando = $this->futuro();

        $response = $this->post(self::URL, [
            'data' => json_encode($this->payload()),
            'documentazione' => UploadedFile::fake()->create('polizza.pdf', 5),
            'fronteDoc' => UploadedFile::fake()->image('fronte.png'),
            'retroDoc' => UploadedFile::fake()->image('retro.png'),
        ], $this->authHeaders($cliente))
            ->assertStatus(201)
            ->assertJsonPath('data.message', 'Richiesta di consulenza inviata con successo.');

        $id = $response->json('data.id');
        $this->assertIsInt($id);

        $consulenza = Consulenza::find($id);
        $this->assertSame((int) $agency->id, (int) $consulenza->id_agenzia);
        $this->assertSame('Mario', $consulenza->nome);
        $this->assertSame('Rossi', $consulenza->cognome);
        $this->assertSame('mario@test.it', $consulenza->email);            // normalizzata
        // A differenza dei preventivi, telefono e indirizzo NON si perdono
        $this->assertSame('3331234567', $consulenza->telefono);
        $this->assertSame('Via Roma 1, Milano', $consulenza->indirizzo);
        $this->assertTrue($consulenza->privacy);
        $this->assertSame($quando->format('Y-m-d H:i:00'), $consulenza->appuntamento_il->format('Y-m-d H:i:s'));

        // ZIP con i 3 allegati
        $zipRel = $consulenza->documenti;
        $this->assertMatchesRegularExpression('#^uploads/consulenze/\d{8}-'.$agency->id.'-Rossi Mario-[0-9a-f]{8}\.zip$#', $zipRel);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipRel)));
        $this->assertSame(3, $zip->numFiles);
        $this->assertNotFalse($zip->locateName('DOC-Rossi Mario.pdf'));
        $this->assertNotFalse($zip->locateName('FDOC-Rossi Mario.png'));
        $this->assertNotFalse($zip->locateName('RDOC-Rossi Mario.png'));
        $zip->close();

        Mail::assertSent(LegacyHtmlMail::class, function (LegacyHtmlMail $mail) use ($quando) {
            return $mail->hasTo('consulenze@test.it')
                && str_contains($mail->subjectLine, 'Nuova richiesta di consulenza da Rossi Mario')
                && str_contains($mail->htmlBody, 'NUOVA RICHIESTA DI CONSULENZA')
                && str_contains($mail->htmlBody, $quando->format('d/m/Y'))
                && str_contains($mail->htmlBody, '15:30')
                && str_contains($mail->htmlBody, '3331234567')
                && $mail->attachmentPath !== null;
        });
    }

    /**
     * Senza allegati ZipArchive non scrive alcun file: in `documenti` non deve
     * finire il path di un file inesistente (e la mail parte senza allegato).
     */
    public function test_without_attachments_stores_no_zip_path(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(201);

        $this->assertSame('', Consulenza::latest('id')->first()->documenti);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->attachmentPath === null
            && str_contains($m->htmlBody, 'NUOVA RICHIESTA DI CONSULENZA'));
    }

    public function test_mail_falls_back_to_preventivo_chain(): void
    {
        $agency = $this->makeAgency();                       // denuncia_mail: sinistri@test.it
        $this->enable($agency, ['consulenza_mail' => '']);   // nessuna casella dedicata
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(201);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('sinistri@test.it'));
    }

    public function test_mail_falls_back_to_quick_email(): void
    {
        $agency = $this->makeAgency(['denuncia_mail' => '']);
        $this->enable($agency, ['consulenza_mail' => '']);
        $cliente = $this->makeCliente($agency);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(201);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('quick@test.it'));
    }

    public function test_config_override_wins_when_no_dedicated_mail(): void
    {
        $agency = $this->makeAgency();
        $this->enable($agency, ['consulenza_mail' => '']);
        $cliente = $this->makeCliente($agency);

        config(['hybrid.agency_mail_overrides' => [
            $agency->id => ['consulenza' => 'override@consulenze.it'],
        ]]);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(201);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('override@consulenze.it'));
    }

    /** L'agenzia di un'altra riga non deve poter usare il flag altrui. */
    public function test_gate_is_per_agency(): void
    {
        $abilitata = $this->makeAgency();
        $this->enable($abilitata);

        $altra = $this->makeAgency(['token' => 'altro-token', 'nome_agenzia' => 'Altra']);
        $cliente = $this->makeCliente($altra, ['username' => 'altro.cliente', 'cf' => 'RSSMRA80A01H502Z']);

        $this->post(self::URL, ['data' => json_encode($this->payload())], $this->authHeaders($cliente))
            ->assertStatus(404);

        $this->assertSame(0, Consulenza::count());
    }
}
