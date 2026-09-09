<?php

use App\Support\PasswordRecoverySupport;

test('password recovery builds whatsapp url for MediChat', function () {
    config([
        'portal.support_whatsapp_phone' => '+584242132112',
        'portal.support_whatsapp_name' => 'MediChat',
    ]);

    $url = PasswordRecoverySupport::whatsappUrl('V12345678');

    $contactUrl = PasswordRecoverySupport::contactWhatsappUrl();

    expect(PasswordRecoverySupport::contactName())->toBe('MediChat')
        ->and(PasswordRecoverySupport::phoneDisplay())->toContain('424')
        ->and($url)->toBeString()
        ->and($url)->toContain('wa.me/584242132112')
        ->and($url)->toContain(rawurlencode('V12345678'))
        ->and($contactUrl)->toBeString()
        ->and($contactUrl)->toContain('wa.me/584242132112')
        ->and($contactUrl)->toContain(rawurlencode('MediChat'));
});
