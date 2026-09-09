<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class UltramsgClient
{
    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>|list<mixed>|null, raw: string|null}
     */
    public function sendChatMessage(string $to, string $body, int $priority = 5, ?string $referenceId = null): array
    {
        $instanceId = (string) config('services.ultramsg.instance_id');
        $token = (string) config('services.ultramsg.token');
        $baseUrl = rtrim((string) config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');

        if ($instanceId === '' || $token === '') {
            throw new RuntimeException('Ultramsg no está configurado (ULTRAMSG_INSTANCE_ID / ULTRAMSG_TOKEN).');
        }

        $url = $baseUrl.'/'.$instanceId.'/messages/chat';

        $payload = [
            'token' => $token,
            'to' => $to,
            'body' => $body,
            'priority' => $priority,
        ];

        if ($referenceId !== null && $referenceId !== '') {
            $payload['referenceId'] = $referenceId;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'raw' => $exception->getMessage(),
            ];
        }

        $json = $response->json();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => is_array($json) ? $json : null,
            'raw' => $response->body(),
        ];
    }
}
