<?php

declare(strict_types=1);

namespace App\Services\PortalApi;

/**
 * Guarda en sesión el JWT y el snapshot del paciente cuando DATA_SOURCE=api.
 * Así Auth::user() funciona sin pegarle a MySQL en cada request.
 */
final class PortalApiSession
{
    public const TOKEN_KEY = 'portal_api_token';

    public const PATIENT_KEY = 'portal_api_patient';

    public static function token(): ?string
    {
        $token = session(self::TOKEN_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $patient
     */
    public static function put(string $token, array $patient): void
    {
        session([
            self::TOKEN_KEY => $token,
            self::PATIENT_KEY => $patient,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function patient(): ?array
    {
        $patient = session(self::PATIENT_KEY);

        return is_array($patient) ? $patient : null;
    }

    public static function forget(): void
    {
        session()->forget([self::TOKEN_KEY, self::PATIENT_KEY]);
    }
}
