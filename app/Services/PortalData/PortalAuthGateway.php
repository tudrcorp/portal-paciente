<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Services\PortalApi\PortalApiException;
use App\Services\PortalApi\PortalApiSession;
use App\Services\PortalApi\PortalPatientFactory;
use App\Support\PortalDataSource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Login unificado: DB (Eloquent) o API (JWT + snapshot en sesión).
 */
final class PortalAuthGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    public function attemptLogin(string $identityCard, string $password): Authenticatable
    {
        $identityCard = trim($identityCard);
        $password = (string) $password;

        if (PortalDataSource::usesApi()) {
            return $this->loginViaApi($identityCard, $password);
        }

        return $this->loginViaDatabase($identityCard, $password);
    }

    public function logout(): void
    {
        if (PortalDataSource::usesApi()) {
            $this->api->logout();
            PortalApiSession::forget();
        }

        Auth::guard('web')->logout();
    }

    private function loginViaDatabase(string $identityCard, string $password): Authenticatable
    {
        $user = Auth::getProvider()->retrieveByCredentials([
            'nro_identificacion' => $identityCard,
        ]);

        if (! $user instanceof TelemedicinePatient) {
            throw ValidationException::withMessages([
                'identityCard' => __('No pudimos validar esa cédula. Verifica e intenta nuevamente.'),
            ]);
        }

        if (! $user->hasPortalPassword()) {
            throw ValidationException::withMessages([
                'password' => __('Tu cuenta aún no tiene una clave del portal. Contacta a Operaciones por WhatsApp para activarla.'),
            ]);
        }

        if (! Auth::getProvider()->validateCredentials($user, [
            'password' => $password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('La clave no es correcta. Verifica e intenta nuevamente.'),
            ]);
        }

        if (! $user->isPortalAuthorized()) {
            throw ValidationException::withMessages([
                'identityCard' => __('Tu acceso al portal del paciente debe ser autorizado por el equipo de operaciones de TuDrGroup.'),
            ]);
        }

        return $user;
    }

    private function loginViaApi(string $identityCard, string $password): Authenticatable
    {
        try {
            $data = $this->api->login($identityCard, $password);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'maintenance' => __('El portal del paciente se encuentra en mantenimiento. Por favor, intenta más tarde.'),
            ]);
        } catch (PortalApiException $exception) {
            if (in_array($exception->status, [0, 502, 503, 504], true)) {
                throw ValidationException::withMessages([
                    'maintenance' => __('El portal del paciente se encuentra en mantenimiento. Por favor, intenta más tarde.'),
                ]);
            }

            $apiMessage = $this->apiErrorMessage($exception);

            if ($exception->status === 403) {
                throw ValidationException::withMessages([
                    'identityCard' => $apiMessage
                        ?: __('Tu acceso al portal del paciente debe ser autorizado por el equipo de operaciones de TuDrGroup.'),
                ]);
            }

            throw ValidationException::withMessages([
                'password' => $exception->status === 401
                    ? ($apiMessage ?: __('No pudimos validar tu cédula o clave. Verifica e intenta nuevamente.'))
                    : __('No pudimos conectar con el servicio de autenticación. Intenta más tarde.'),
            ]);
        }

        $token = (string) ($data['token'] ?? '');
        $patientPayload = is_array($data['patient'] ?? null) ? $data['patient'] : [];

        if ($token === '' || $patientPayload === []) {
            throw ValidationException::withMessages([
                'identityCard' => __('Respuesta inválida del servicio de autenticación.'),
            ]);
        }

        // Guardamos token primero para que /auth/me pueda autenticarse.
        PortalApiSession::put($token, $patientPayload);

        try {
            $meData = $this->api->me();
            if (is_array($meData['patient'] ?? null)) {
                $patientPayload = array_merge($patientPayload, $meData['patient']);
                PortalApiSession::put($token, $patientPayload);
            }
        } catch (\Throwable) {
            // El payload del login basta para hidratar la sesión Laravel.
        }

        return PortalPatientFactory::fromApiPayload($patientPayload);
    }

    private function apiErrorMessage(PortalApiException $exception): ?string
    {
        $payload = $exception->payload;
        if (! is_array($payload)) {
            return null;
        }

        $message = $payload['error'] ?? $payload['message'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }
}
