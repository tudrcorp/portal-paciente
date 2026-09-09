<?php

declare(strict_types=1);

namespace App\Support;

final class CorporateWhatsApp
{
    public static function normalizePhoneForWhatsApp(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+58'.substr($digits, 1);
        }

        if (str_starts_with($digits, '4') && strlen($digits) === 10) {
            return '+58'.$digits;
        }

        if (str_starts_with($digits, '58') && strlen($digits) > 12) {
            return '+'.$digits;
        }

        return str_starts_with($digits, '+') ? $digits : '+'.$digits;
    }

    public static function buildWaMeUrl(?string $phone, ?string $message = null): ?string
    {
        $normalized = self::normalizePhoneForWhatsApp($phone);
        if ($normalized === null) {
            return null;
        }

        $digits = ltrim($normalized, '+');
        $url = 'https://wa.me/'.$digits;

        $message = trim((string) $message);
        if ($message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }

    public static function formatDisplayPhone(?string $phone): string
    {
        $normalized = self::normalizePhoneForWhatsApp($phone);
        if ($normalized === null) {
            return '';
        }

        $digits = ltrim($normalized, '+');

        if (strlen($digits) === 12 && str_starts_with($digits, '58')) {
            $local = substr($digits, 2);

            return sprintf(
                '+58 %s-%s-%s',
                substr($local, 0, 3),
                substr($local, 3, 3),
                substr($local, 6)
            );
        }

        return $normalized;
    }
}
