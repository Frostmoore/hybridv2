<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client AssiEasy (gestionale polizze esterno), porting delle chiamate dei
 * cron legacy: form-encoded, headers chiave-hi/Host/assi_secret, host per
 * agenzia (`agenzie_new.assiurl`), risposte `{data: …}` con campi UPPERCASE.
 */
class AssiEasyService
{
    private const CHIAVE_HI = 'ASSIHI';

    /** Recupera la password AssiEasy di un cliente (lookup credenziali). */
    public function lookupPassword(string $assiurl, string $assisecret, string $username, string $cf): ?string
    {
        $data = $this->post($assiurl, $assisecret, 'assieasy/clienti/autenticazione/get_credenziali_utente', [
            'username' => $username,
            'codicefiscale' => $cf,
        ]);

        return $data['data']['PASSWORD'] ?? null;
    }

    /** Login: ritorna il token di sessione AssiEasy. */
    public function login(string $assiurl, string $assisecret, string $username, string $password): ?string
    {
        $data = $this->post($assiurl, $assisecret, 'assieasy/clienti/autenticazione/login', [
            'username' => $username,
            'password' => $password,
        ]);

        return $data['data']['TOKEN'] ?? null;
    }

    /** @return list<array<string, mixed>> polizze vive del cliente loggato */
    public function polizzeVive(string $assiurl, string $assisecret, string $token): array
    {
        $data = $this->post($assiurl, $assisecret, 'assieasy/clienti/polizze/get', [
            'ID_POLIZZA' => '0',
            'SOLO_VIVE' => '1',
            'sorts[1][column]' => 'NUMERO_POLIZZA',
            'sorts[1][order]' => 'DESC',
        ], $token);

        return (array) ($data['data'] ?? []);
    }

    /** @return list<array<string, mixed>> titoli attivi (per DATA_EFFETTO) */
    public function titoliAttivi(string $assiurl, string $assisecret, string $token): array
    {
        $data = $this->post($assiurl, $assisecret, 'assieasy/clienti/titoli/get', [
            'STATO_TITOLO' => '1',
        ], $token);

        return (array) ($data['data'] ?? []);
    }

    /** Dettaglio singola polizza (per RAMO e TARGA nel testo della notifica). */
    public function polizzaById(string $assiurl, string $assisecret, string $token, string $idPolizza): ?array
    {
        $data = $this->post($assiurl, $assisecret, 'assieasy/clienti/polizze/get', [
            'ID_POLIZZA' => $idPolizza,
        ], $token);

        return $data['data'][0] ?? null;
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     *
     * @throws \RuntimeException se la risposta non è JSON valido
     */
    private function post(string $assiurl, string $assisecret, string $path, array $body, ?string $token = null): array
    {
        $headers = [
            'chiave-hi' => self::CHIAVE_HI,
            'Host' => $assiurl,
            'assi_secret' => $assisecret,
        ];
        if ($token !== null) {
            $headers['Accept'] = '*/*';
            $headers['token'] = $token;
        }

        $response = Http::withHeaders($headers)
            ->asForm()
            ->timeout(20)
            ->post("https://$assiurl/$path", $body);

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException("AssiEasy $path: risposta non JSON (HTTP {$response->status()})");
        }

        return $json;
    }
}
