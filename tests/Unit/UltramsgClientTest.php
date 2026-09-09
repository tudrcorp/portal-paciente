<?php

declare(strict_types=1);

use App\Services\Notifications\UltramsgClient;
use Illuminate\Support\Facades\Http;

test('ultramsg client posts chat payload to the configured instance', function () {
    config([
        'services.ultramsg.instance_id' => 'instance123',
        'services.ultramsg.token' => 'secret-token',
        'services.ultramsg.base_url' => 'https://api.ultramsg.com',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance123/messages/chat' => Http::response([
            'sent' => 'true',
            'message' => 'ok',
            'id' => 99,
        ], 200),
    ]);

    $result = (new UltramsgClient)->sendChatMessage('+584121234567', 'Hola paciente', 5, 'ref-1');

    expect($result['ok'])->toBeTrue()
        ->and($result['status'])->toBe(200)
        ->and($result['body']['id'] ?? null)->toBe(99);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.ultramsg.com/instance123/messages/chat'
            && $request['token'] === 'secret-token'
            && $request['to'] === '+584121234567'
            && $request['body'] === 'Hola paciente'
            && $request['referenceId'] === 'ref-1';
    });
});

test('ultramsg client fails clearly when credentials are missing', function () {
    config([
        'services.ultramsg.instance_id' => '',
        'services.ultramsg.token' => '',
    ]);

    (new UltramsgClient)->sendChatMessage('+584121234567', 'Hola');
})->throws(RuntimeException::class);
