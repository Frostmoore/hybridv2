<?php

namespace Tests\Feature\Web;

use App\Mail\LegacyHtmlMail;
use App\Models\Documento;
use App\Models\Preventivo;
use App\Models\Sinistro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ApiV2\V2TestCase;
use ZipArchive;

class PublicClaimsTest extends V2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_main').'/'.ltrim($path, '/');
    }

    // ─── Pagine ──────────────────────────────────────────────────────────

    public function test_pages_render_with_agency_data(): void
    {
        $agency = $this->makeAgency(['nome_agenzia' => 'Agenzia Bella']);

        foreach (['denuncia_sinistro.php', 'preventivo.php', 'documento.php'] as $page) {
            $this->get($this->host($page.'?id='.$agency->id))
                ->assertOk()
                ->assertSee('Agenzia Bella');
        }
    }

    public function test_page_without_id_400(): void
    {
        $this->get($this->host('denuncia_sinistro.php'))->assertStatus(400);
    }

    public function test_page_unknown_agency_404(): void
    {
        $this->get($this->host('preventivo.php?id=999'))->assertNotFound();
    }

    // ─── Handler sinistro ────────────────────────────────────────────────

    public function test_sinistro_auto_form_submission(): void
    {
        $agency = $this->makeAgency();

        $response = $this->post($this->host('res/denunciasinistro.php'), [
            'agenzia_id_auto' => (string) $agency->id,
            'primo_nome_denuncia_auto' => 'Mario',
            'cognome_denuncia_auto' => 'Rossi',
            'email_denuncia_auto' => 'mario@test.it',
            'descrizione_denuncia_auto' => 'Tamponamento in via Roma',
            'checkbox_privacy_auto' => 'on',
            'cai_denuncia_auto' => [UploadedFile::fake()->image('cai1.jpg'), UploadedFile::fake()->image('cai2.jpg')],
            'documenti_denuncia_auto' => [UploadedFile::fake()->image('patente.png')],
        ]);

        $response->assertOk()->assertSee('Denuncia inoltrata!');

        $sinistro = Sinistro::latest('id')->first();
        $this->assertSame('Rossi Mario', $sinistro->nome_denuncia);
        $this->assertSame('auto', $sinistro->tipo_sinistro);
        $this->assertSame('on', $sinistro->privacy_denuncia);
        $this->assertSame(date('d/m/Y'), $sinistro->data_denuncia);

        // ZIP con entry numerate in stile legacy
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($sinistro->documenti_denuncia)));
        $this->assertNotFalse($zip->locateName('CAI-0.jpg'));
        $this->assertNotFalse($zip->locateName('CAI-1.jpg'));
        $this->assertNotFalse($zip->locateName('DOCUMENTI-0.png'));
        $zip->close();

        // Mail all'indirizzo del DB (non dal form!)
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('sinistri@test.it')
            && $m->subjectLine === 'Nuova denuncia di Sinistro da Rossi Mario');
    }

    public function test_sinistro_nonauto_form_submission(): void
    {
        $agency = $this->makeAgency();

        $this->post($this->host('res/denunciasinistro.php'), [
            'agenzia_id_nonauto' => (string) $agency->id,
            'primo_nome_denuncia_nonauto' => 'Luigi',
            'cognome_denuncia_nonauto' => 'Verdi',
            'email_denuncia_nonauto' => 'luigi@test.it',
            'descrizione_denuncia_nonauto' => 'Danni da grandine',
            'checkbox_privacy_nonauto' => 'on',
            'documenti_denuncia_nonauto' => [UploadedFile::fake()->image('doc.jpg')],
        ])->assertOk();

        $this->assertSame('nonauto', Sinistro::latest('id')->first()->tipo_sinistro);
    }

    public function test_sinistro_without_agency_field_403(): void
    {
        $this->post($this->host('res/denunciasinistro.php'), [])->assertStatus(403);
    }

    public function test_sinistro_ignores_mail_from_form(): void
    {
        $agency = $this->makeAgency(['denuncia_mail' => 'vera@test.it']);

        $this->post($this->host('res/denunciasinistro.php'), [
            'agenzia_id_auto' => (string) $agency->id,
            'primo_nome_denuncia_auto' => 'X',
            'cognome_denuncia_auto' => 'Y',
            'email_denuncia_auto' => 'x@y.it',
            'descrizione_denuncia_auto' => 'test',
            'checkbox_privacy_auto' => 'on',
            'denuncia_mail_auto' => 'attaccante@evil.it',   // campo legacy: ora ignorato
        ])->assertOk();

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('vera@test.it'));
        Mail::assertNotSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('attaccante@evil.it'));
    }

    // ─── Handler preventivo / documento ──────────────────────────────────

    public function test_preventivo_form_submission(): void
    {
        $agency = $this->makeAgency();

        $this->post($this->host('res/richiestapreventivo.php'), [
            'agenzia_id_preventivo' => (string) $agency->id,
            'primo_nome_preventivo' => 'Mario',
            'cognome_preventivo' => 'Rossi',
            'email_preventivo' => 'mario@test.it',
            'descrizione_preventivo' => 'RC Auto',
            'checkbox_privacy_preventivo' => 'on',
            'documenti_preventivo' => [UploadedFile::fake()->create('doc.pdf', 5)],
        ])->assertOk()->assertSee('Complimenti!');

        $this->assertSame('Rossi Mario', Preventivo::latest('id')->first()->nome_denuncia);
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->subjectLine === 'Nuova Richiesta di Preventivo da Rossi Mario');
    }

    public function test_documento_form_submission(): void
    {
        $agency = $this->makeAgency();

        $this->post($this->host('res/caricadocumenti.php'), [
            'agenzia_id_documenti' => (string) $agency->id,
            'primo_nome_documenti' => 'Mario',
            'cognome_documenti' => 'Rossi',
            'email_documenti' => 'mario@test.it',
            'descrizione_documenti' => 'Carta identità',
            'checkbox_privacy_documenti' => 'on',
            'documenti_documenti' => [UploadedFile::fake()->image('ci.jpg')],
        ])->assertOk();

        $this->assertSame('Rossi Mario', Documento::latest('id')->first()->nome_denuncia);
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->subjectLine === 'Nuovo Documento caricato da Rossi Mario');
    }
}
