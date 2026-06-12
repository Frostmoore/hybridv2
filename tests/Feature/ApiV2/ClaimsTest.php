<?php

namespace Tests\Feature\ApiV2;

use App\Mail\LegacyHtmlMail;
use App\Models\Documento;
use App\Models\Preventivo;
use App\Models\Sinistro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ClaimsTest extends V2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    // ─── sinistro.php ────────────────────────────────────────────────────

    public function test_sinistro_requires_token(): void
    {
        $this->post('/res/api/v2/claims/sinistro.php')->assertStatus(401);
    }

    public function test_sinistro_invalid_data_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/sinistro.php', ['data' => 'non-json'], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Campo POST[data] mancante o JSON non valido.');
    }

    public function test_sinistro_invalid_option_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/sinistro.php', [
            'data' => json_encode(['option' => 9]),
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_sinistro_option1_creates_row_zip_and_mail(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $response = $this->post('/res/api/v2/claims/sinistro.php', [
            'data' => json_encode([
                'option' => 1,
                'nome' => 'Mario', 'cognome' => 'Rossi',
                'email' => 'Mario@Test.IT',
                'dataSinistro' => '2026-06-10',
                'descrizione' => 'Tamponamento',
                'privacy' => '1',
            ]),
            'fotoCAI' => UploadedFile::fake()->image('cai.jpg'),
            'fronteDoc' => UploadedFile::fake()->image('fronte.png'),
            'retroDoc' => UploadedFile::fake()->image('retro.png'),
        ], $this->authHeaders($cliente))
            ->assertStatus(201)
            ->assertJsonPath('data.message', 'Denuncia inviata con successo.');

        $id = $response->json('data.id');
        $this->assertIsInt($id);

        $sinistro = Sinistro::find($id);
        $this->assertSame('Auto (CAI Compilato)', $sinistro->tipo_sinistro);
        $this->assertSame('Rossi Mario', $sinistro->nome_denuncia);
        $this->assertSame('mario@test.it', $sinistro->email_denuncia);
        $this->assertSame('2026-06-10', $sinistro->data_denuncia);
        $this->assertSame('1', $sinistro->privacy_denuncia);

        // ZIP esiste e contiene le 3 entry coi nomi legacy
        $zipRel = $sinistro->documenti_denuncia;
        $this->assertMatchesRegularExpression('#^uploads/sinistri/\d{8}-'.$agency->id.'-'.$id.'-[0-9a-f]{8}\.zip$#', $zipRel);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipRel)));
        $this->assertSame(3, $zip->numFiles);
        $this->assertNotFalse($zip->locateName($id.'CAI-Rossi Mario.jpg'));
        $this->assertNotFalse($zip->locateName($id.'FDOC-Rossi Mario.png'));
        $zip->close();

        Mail::assertSent(LegacyHtmlMail::class, function (LegacyHtmlMail $mail) {
            return $mail->hasTo('sinistri@test.it')
                && str_contains($mail->subjectLine, 'Nuova denuncia sinistro da Rossi Mario')
                && str_contains($mail->htmlBody, 'CAI COMPILATO')
                && $mail->attachmentPath !== null;
        });
    }

    public function test_sinistro_option2_uses_contraente_data(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/sinistro.php', [
            'data' => json_encode([
                'option' => 2,
                'dataOraIncidente' => '2026-06-10 15:30',
                'luogoIncidente' => 'Via Roma 1, Milano',
                'feriti' => 'no',
                'descrizione' => 'CAI non compilato',
                'privacy' => '1',
                'contraente' => ['nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@test.it'],
                'veicoloA' => ['targaTelaio' => 'AB123CD', 'marca' => 'Fiat Panda'],
                'veicoloB' => ['targaTelaio' => 'EF456GH'],
            ]),
        ], $this->authHeaders($cliente))->assertStatus(201);

        $sinistro = Sinistro::latest('id')->first();
        $this->assertSame('Auto (CAI non Compilato)', $sinistro->tipo_sinistro);
        $this->assertSame('2026-06-10 15:30', $sinistro->data_denuncia);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => str_contains($m->htmlBody, 'AB123CD')
            && str_contains($m->htmlBody, 'Via Roma 1, Milano'));
    }

    public function test_sinistro_option3_non_auto(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/sinistro.php', [
            'data' => json_encode([
                'option' => 3,
                'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@test.it',
                'dataSinistro' => '2026-06-09', 'descrizione' => 'Danno casa', 'privacy' => '1',
            ]),
            'documentazione' => UploadedFile::fake()->create('doc.pdf', 10),
        ], $this->authHeaders($cliente))->assertStatus(201);

        $this->assertSame('Non Auto', Sinistro::latest('id')->first()->tipo_sinistro);
    }

    // ─── preventivo.php ──────────────────────────────────────────────────

    public function test_preventivo_requires_email_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/preventivo.php', [
            'data' => json_encode(['nome' => 'Mario']),
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Il campo email è obbligatorio.');
    }

    public function test_preventivo_success(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/preventivo.php', [
            'data' => json_encode([
                'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@test.it',
                'telefono' => '333', 'indirizzo' => 'Via X', 'descrizione' => 'RC Auto', 'privacy' => '1',
            ]),
            'documentazione' => UploadedFile::fake()->create('libretto.pdf', 5),
        ], $this->authHeaders($cliente))
            ->assertStatus(201)
            ->assertJsonPath('data.message', 'Richiesta preventivo inviata con successo.');

        $prev = Preventivo::latest('id')->first();
        $this->assertSame('Rossi Mario', $prev->nome_denuncia);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $prev->data_denuncia);

        // dest = denuncia_mail (fallback quick_email)
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('sinistri@test.it')
            && str_contains($m->htmlBody, 'NUOVA RICHIESTA DI PREVENTIVO'));
    }

    public function test_preventivo_falls_back_to_quick_email(): void
    {
        $agency = $this->makeAgency(['denuncia_mail' => '']);
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/preventivo.php', [
            'data' => json_encode(['email' => 'mario@test.it']),
        ], $this->authHeaders($cliente))->assertStatus(201);

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('quick@test.it'));
    }

    // ─── documento.php ───────────────────────────────────────────────────

    public function test_documento_success(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->post('/res/api/v2/claims/documento.php', [
            'data' => json_encode([
                'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@test.it',
                'descrizione' => 'Documento richiesto', 'privacy' => '1',
            ]),
            'documentazione' => UploadedFile::fake()->create('carta.pdf', 5),
        ], $this->authHeaders($cliente))
            ->assertStatus(201)
            ->assertJsonPath('data.message', 'Documento inviato con successo.');

        $this->assertSame('Rossi Mario', Documento::latest('id')->first()->nome_denuncia);

        // dest = quick_email
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('quick@test.it')
            && str_contains($m->htmlBody, 'NUOVO DOCUMENTO CARICATO'));
    }
}
