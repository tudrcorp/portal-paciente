<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CTA de recuperación de clave vía WhatsApp / MediChat (sin autoservicio).
 */
final class PasswordRecoverySupport
{
    public static function defaultMessage(?string $identityCard = null): string
    {
        $identityCard = trim((string) $identityCard);

        if ($identityCard !== '') {
            return __('Hola, olvidé mi clave del Portal del Paciente. Mi cédula es :id. ¿Me ayudan a actualizarla?', [
                'id' => $identityCard,
            ]);
        }

        return __('Hola, olvidé mi clave del Portal del Paciente. ¿Me ayudan a actualizarla?');
    }

    public static function contactName(): string
    {
        $name = config('portal.support_whatsapp_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'MediChat';
    }

    public static function phoneDisplay(): string
    {
        $phone = config('portal.support_whatsapp_phone');

        return CorporateWhatsApp::formatDisplayPhone(is_string($phone) ? $phone : null);
    }

    public static function whatsappUrl(?string $identityCard = null): ?string
    {
        $fallbackPhone = config('portal.support_whatsapp_phone');

        return CorporateWhatsApp::buildWaMeUrl(
            is_string($fallbackPhone) ? $fallbackPhone : null,
            self::defaultMessage($identityCard)
        );
    }

    public static function contactMessage(): string
    {
        return __('Hola, quiero comunicarme con :name desde el Portal del Paciente.', [
            'name' => self::contactName(),
        ]);
    }

    public static function contactWhatsappUrl(): ?string
    {
        $phone = config('portal.support_whatsapp_phone');

        return CorporateWhatsApp::buildWaMeUrl(
            is_string($phone) ? $phone : null,
            self::contactMessage()
        );
    }
}
