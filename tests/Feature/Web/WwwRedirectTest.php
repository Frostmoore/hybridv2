<?php

namespace Tests\Feature\Web;

use Tests\Feature\ApiV2\V2TestCase;

/**
 * Le pagine web stanno dentro `Route::domain(...)`: senza il middleware
 * RedirectWwwToApex una richiesta a `www.<dominio>` non matcha nessun gruppo e
 * risponde 404. È il bug che mandava le app white-label (PATH cablato con
 * `www.` in constants.template.dart) su un 404 aprendo delete_account.php.
 */
class WwwRedirectTest extends V2TestCase
{
    private function main(): string
    {
        return (string) config('hybrid.domain_main');
    }

    public function test_www_su_pagina_web_redirige_all_apex(): void
    {
        $this->get('http://www.'.$this->main().'/delete_account.php')
            ->assertStatus(301)
            ->assertRedirect('http://'.$this->main().'/delete_account.php');
    }

    public function test_redirect_conserva_la_query_string(): void
    {
        $this->get('http://www.'.$this->main().'/remove_account.php?token=abc123')
            ->assertStatus(301)
            ->assertRedirect('http://'.$this->main().'/remove_account.php?token=abc123');
    }

    public function test_post_usa_308_per_non_perdere_il_metodo(): void
    {
        $this->post('http://www.'.$this->main().'/delete_account.php', ['email' => 'x@y.it'])
            ->assertStatus(308)
            ->assertRedirect('http://'.$this->main().'/delete_account.php');
    }

    public function test_anche_il_sottodominio_agenzie_perde_il_www(): void
    {
        $agencies = (string) config('hybrid.domain_agencies');

        $this->get('http://www.'.$agencies.'/login')
            ->assertStatus(301)
            ->assertRedirect('http://'.$agencies.'/login');
    }

    /**
     * Le route API rispondono già su qualsiasi host e le app in circolazione le
     * chiamano su `www.`: redirigerle romperebbe login e refresh (i client HTTP
     * degradano le POST redirette a GET). Devono passare intatte.
     */
    public function test_le_route_api_non_vengono_redirette(): void
    {
        $response = $this->postJson('http://www.'.$this->main().'/res/api/v2/agency.php', []);

        $this->assertNotContains($response->getStatusCode(), [301, 302, 307, 308]);
    }

    public function test_apex_non_viene_toccato(): void
    {
        $this->get('http://'.$this->main().'/delete_account.php')->assertOk();
    }

    public function test_host_sconosciuto_col_www_non_viene_riscritto(): void
    {
        $this->get('http://www.dominio-che-non-esiste.it/delete_account.php')
            ->assertNotFound();
    }
}
