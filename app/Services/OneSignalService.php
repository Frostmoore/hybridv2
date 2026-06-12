<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Invio push via OneSignal REST (stesso endpoint/header del legacy).
 */
class OneSignalService
{
    private const URL = 'https://api.onesignal.com/notifications';

    /**
     * @param  array<string, mixed>  $payload  payload OneSignal completo (app_id incluso)
     * @return array{0: int, 1: string}  [status HTTP, body]
     */
    public function send(string $apiKey, array $payload): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Key '.$apiKey,
        ])->timeout(20)->post(self::URL, $payload);

        return [$response->status(), (string) $response->body()];
    }

    /**
     * Costruisce il payload come i send_notification_* legacy.
     *
     * @param  list<string>|null  $externalUserIds  null = tutti gli iscritti
     */
    public function buildPayload(string $appId, string $titolo, string $testo, ?array $externalUserIds, ?string $imageUrl): array
    {
        $payload = [
            'app_id' => $appId,
            'headings' => ['en' => $titolo],
            'contents' => ['en' => $testo],
            'target_channel' => 'push',
        ];

        if ($externalUserIds === null) {
            $payload['included_segments'] = ['Total Subscriptions'];
        } else {
            $payload['include_external_user_ids'] = $externalUserIds;
        }

        if ($imageUrl) {
            $payload['big_picture'] = $imageUrl;
            $payload['ios_attachments'] = ['id' => $imageUrl];
        }

        return $payload;
    }

    /** Messaggio d'errore come il legacy (estrae 'errors' dal body). */
    public static function errorMessage(string $prefix, string $body): string
    {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['errors'])) {
            return $prefix.' '.implode(' | ', (array) $decoded['errors']);
        }

        return $prefix.' Risposta grezza: '.$body;
    }
}
