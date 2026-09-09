<?php

namespace App\Auth;

use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Services\PortalApi\PortalApiSession;
use App\Services\PortalApi\PortalPatientFactory;
use App\Support\PortalDataSource;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Support\Str;

class TelemedicinePatientUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?UserContract
    {
        // Modo API: el paciente vive en sesión (+ refresh opcional vía /auth/me).
        if (PortalDataSource::usesApi()) {
            return $this->retrieveFromApiSession((int) $identifier);
        }

        return parent::retrieveById($identifier);
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?UserContract
    {
        // En modo API el login lo hace PortalAuthGateway (JWT); no buscamos en MySQL.
        if (PortalDataSource::usesApi()) {
            return null;
        }

        $identityCard = isset($credentials['nro_identificacion'])
            ? trim((string) $credentials['nro_identificacion'])
            : '';

        if ($identityCard !== '') {
            return $this->newModelQuery()
                ->where('nro_identificacion', $identityCard)
                ->first();
        }

        $email = isset($credentials['email']) ? trim((string) $credentials['email']) : '';

        if ($email === '') {
            return null;
        }

        return $this->newModelQuery()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }

    public function validateCredentials(UserContract $user, #[\SensitiveParameter] array $credentials): bool
    {
        if (! $user instanceof TelemedicinePatient) {
            return parent::validateCredentials($user, $credentials);
        }

        $plainPassword = isset($credentials['password'])
            ? (string) $credentials['password']
            : '';

        $storedPassword = trim((string) ($user->patient_portal_password ?? ''));

        if ($storedPassword === '' || $plainPassword === '') {
            return false;
        }

        return hash_equals($storedPassword, $plainPassword);
    }

    public function rehashPasswordIfRequired(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        if ($user instanceof TelemedicinePatient) {
            return;
        }

        parent::rehashPasswordIfRequired($user, $credentials, $force);
    }

    private function retrieveFromApiSession(int $identifier): ?TelemedicinePatient
    {
        if ($identifier <= 0 || PortalApiSession::token() === null) {
            return null;
        }

        $cached = PortalApiSession::patient();
        if (is_array($cached) && (int) ($cached['id'] ?? 0) === $identifier) {
            return PortalPatientFactory::fromApiPayload($cached);
        }

        // Sesión incompleta: pedimos /auth/me para reconstruir el snapshot.
        try {
            $me = app(PortalApiClient::class)->me();
            $patientPayload = is_array($me['patient'] ?? null) ? $me['patient'] : null;
            if (! $patientPayload || (int) ($patientPayload['id'] ?? 0) !== $identifier) {
                return null;
            }

            PortalApiSession::put((string) PortalApiSession::token(), $patientPayload);

            return PortalPatientFactory::fromApiPayload($patientPayload);
        } catch (\Throwable) {
            return null;
        }
    }
}
