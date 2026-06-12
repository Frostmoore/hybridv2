<?php

namespace Tests\Feature\ApiV2;

use App\Mail\LegacyHtmlMail;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordTest extends V2TestCase
{
    private const URL = '/res/api/v2/auth/forgot-password.php';

    private const GENERIC = 'Se il codice fiscale è registrato, riceverai una email con le istruzioni.';

    public function test_missing_identifier_422(): void
    {
        $this->postJson(self::URL, ['agency_id' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_unknown_cf_returns_generic_message_without_mail(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();

        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'cf' => 'SCONOSCIUTO'])
            ->assertOk()
            ->assertJsonPath('data.message', self::GENERIC);

        Mail::assertNothingSent();
    }

    public function test_known_cf_sends_reset_mail_with_legacy_link(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'cf' => 'rssmra80a01h501z'])
            ->assertOk()
            ->assertJsonPath('data.message', self::GENERIC);

        Mail::assertSent(LegacyHtmlMail::class, function (LegacyHtmlMail $mail) use ($cliente, $agency) {
            return $mail->hasTo('mario@test.it')
                && $mail->subjectLine === 'Reimposta la tua password su Agenzia Test'
                && str_contains(
                    $mail->htmlBody,
                    '/cambiapassword.php?a='.$cliente->id.'&b='.$cliente->activation_token.'&c='.$agency->id,
                );
        });
    }

    public function test_username_identifier_supported_for_flutter_app(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        // L'app manda {agency_id, username}: esteso per decisione §7.1
        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'username' => 'MARIO@TEST.IT'])
            ->assertOk();

        Mail::assertSent(LegacyHtmlMail::class, 1);
    }
}
