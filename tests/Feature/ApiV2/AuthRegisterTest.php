<?php

namespace Tests\Feature\ApiV2;

use App\Mail\LegacyHtmlMail;
use App\Models\Cliente;
use Illuminate\Support\Facades\Mail;

class AuthRegisterTest extends V2TestCase
{
    private const URL = '/res/api/v2/auth/register.php';

    private function validPayload(int $agencyId): array
    {
        return [
            'agency_id' => (string) $agencyId,
            'username' => 'nuovo.utente',
            'password' => 'Password!1',
            'email' => 'Nuovo@Test.IT',
            'nome' => 'Nuovo',
            'cognome' => 'Utente',
            'cf' => 'nvutnt90a01h501x',
            'telefono' => '3331234567',
            'datadinascita' => '1990-01-01',
            'playerid' => 'player-new',
            'privacy1' => '1',
            'privacy2' => '1',
            'privacy3' => '0',
            'privacy4' => '0',
        ];
    }

    public function test_missing_fields_422(): void
    {
        $this->postJson(self::URL, ['agency_id' => '1', 'username' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'I campi agency_id, username, password ed email sono obbligatori.');
    }

    public function test_duplicate_cf_409(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $this->makeCliente($agency, ['cf' => 'NVUTNT90A01H501X', 'username' => 'altro', 'email' => 'altro@test.it']);

        $this->postJson(self::URL, $this->validPayload($agency->id))
            ->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_CF');
    }

    public function test_duplicate_username_409(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $this->makeCliente($agency, ['username' => 'nuovo.utente', 'cf' => 'ALTRO', 'email' => 'altro@test.it']);

        $this->postJson(self::URL, $this->validPayload($agency->id))
            ->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_USERNAME');
    }

    public function test_duplicate_email_normalized_409(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        // Email legacy con formato "email|extra": va normalizzata nel confronto
        $this->makeCliente($agency, ['email' => 'NUOVO@test.it|vecchio', 'username' => 'altro', 'cf' => 'ALTRO']);

        $this->postJson(self::URL, $this->validPayload($agency->id))
            ->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_EMAIL');
    }

    public function test_same_data_in_other_agency_is_allowed(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $this->makeCliente($other, ['cf' => 'NVUTNT90A01H501X', 'username' => 'nuovo.utente', 'email' => 'nuovo@test.it']);

        $this->postJson(self::URL, $this->validPayload($agency->id))->assertStatus(201);
    }

    public function test_successful_registration(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();

        $this->postJson(self::URL, $this->validPayload($agency->id))
            ->assertStatus(201)
            ->assertJsonPath('data.message', 'Registrazione completata. Controlla la tua email per attivare l\'account.');

        $user = Cliente::where('username', 'nuovo.utente')->first();
        $this->assertNotNull($user);
        $this->assertSame('0', $user->active);
        $this->assertSame('NVUTNT90A01H501X', $user->cf);          // CF uppercased
        $this->assertSame('nuovo@test.it', $user->email);          // email normalizzata
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $user->activation_token);
        $this->assertMatchesRegularExpression('/^1\|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $user->privacyuno);
        $this->assertStringStartsWith('0|', $user->privacytre);
        $this->assertTrue(password_verify('Password!1', $user->password));

        // Email: attivazione all'utente + avviso all'agenzia (info_email_sedi normalizzata)
        Mail::assertSent(LegacyHtmlMail::class, 2);
        Mail::assertSent(LegacyHtmlMail::class, function (LegacyHtmlMail $mail) use ($user) {
            return $mail->hasTo('nuovo@test.it')
                && str_contains($mail->htmlBody, '/attivacliente.php?a='.$user->id.'&b='.$user->activation_token)
                && $mail->subjectLine === 'Attiva il tuo account su Agenzia Test.';
        });
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $mail) => $mail->hasTo('info@test.it'));
    }

    public function test_no_agency_notice_when_agency_email_empty(): void
    {
        Mail::fake();
        $agency = $this->makeAgency(['info_email_sedi' => '']);

        $this->postJson(self::URL, $this->validPayload($agency->id))->assertStatus(201);

        Mail::assertSent(LegacyHtmlMail::class, 1);
    }
}
