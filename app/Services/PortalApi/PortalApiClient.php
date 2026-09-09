<?php

declare(strict_types=1);

namespace App\Services\PortalApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP hacia portal-paciente-api.
 * Centraliza timeouts, retries, Bearer token y parsing de { success, data }.
 */
final class PortalApiClient
{
    private function baseRequest(?string $token = null): PendingRequest
    {
        $config = config('portal.api');

        $request = Http::baseUrl((string) $config['base_url'])
            ->acceptJson()
            ->timeout((float) $config['timeout_seconds'])
            ->connectTimeout((float) $config['connect_timeout_seconds'])
            ->retry(
                (int) $config['retry_times'],
                (int) $config['retry_sleep_ms'],
                throw: false
            );

        $token ??= PortalApiSession::token();
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $identityCard, string $password = ''): array
    {
        $payload = [
            'nro_identificacion' => $identityCard,
        ];

        if ($password !== '') {
            $payload['password'] = $password;
            $payload['patient_portal_password'] = $password;
        }

        $response = $this->baseRequest(null)
            ->post('/api/auth/login', $payload);

        return $this->unwrap($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeClinicalHistory(array $payload): array
    {
        return $this->unwrap(
            $this->baseRequest()->post('/api/clinical-history', $payload)
        );
    }

    public function logout(): void
    {
        try {
            $this->baseRequest()->post('/api/auth/logout');
        } catch (\Throwable) {
            // Logout local siempre debe continuar aunque el API no responda.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/auth/me'));
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/me/profile'));
    }

    /**
     * @return array<string, mixed>
     */
    public function clinicalHistory(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/clinical-history'));
    }

    /**
     * Descarga el PDF de historia clínica generado por el API (streaming).
     */
    public function downloadClinicalHistoryPdf(): Response
    {
        $config = config('portal.api');

        $response = $this->baseRequest()
            ->timeout(max(30.0, (float) $config['timeout_seconds']))
            ->withOptions(['stream' => true])
            ->get('/api/clinical-history/pdf');

        if (! $response->successful()) {
            throw new PortalApiException(
                $response->json('error')
                    ?: $response->json('message')
                    ?: 'No se pudo generar el PDF de historia clínica',
                $response->status(),
                $response->json()
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function documents(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/documents'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cases(): array
    {
        $data = $this->unwrap($this->baseRequest()->get('/api/cases'));

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function uploadDocument(array $fields, string $absolutePath, string $originalName): array
    {
        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            throw new PortalApiException('No se pudo leer el archivo a subir', 400);
        }

        $form = array_filter([
            'document_name' => $fields['document_name'] ?? null,
            'upload_reason' => $fields['upload_reason'] ?? null,
            'telemedicine_case_id' => $fields['telemedicine_case_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->baseRequest()
            ->attach('document_file', $contents, $originalName)
            ->post('/api/documents', $form);

        return $this->unwrap($response);
    }

    public function downloadDocument(string $source, int $id): Response
    {
        $response = $this->baseRequest()
            ->withOptions(['stream' => true])
            ->get("/api/documents/{$source}/{$id}/download");

        if (! $response->successful()) {
            throw new PortalApiException(
                $response->json('error') ?: 'No se pudo descargar el documento',
                $response->status(),
                $response->json()
            );
        }

        return $response;
    }

    /**
     * Citas médicas operativas (operation_medical_appointments).
     *
     * @return array<string, mixed>
     */
    public function appointments(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/appointments'));
    }

    /**
     * Envía por WhatsApp el PDF de la OS generado por Integracorp.
     *
     * @return array<string, mixed>
     */
    public function sendAppointmentServiceOrderWhatsApp(int $appointmentId, ?string $phone = null): array
    {
        $payload = [];
        if (is_string($phone) && $phone !== '') {
            $payload['phone'] = $phone;
        }

        return $this->unwrap(
            $this->baseRequest()->post("/api/appointments/{$appointmentId}/service-order/whatsapp", $payload)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reminders(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/reminders'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createReminder(array $payload): array
    {
        return $this->unwrap($this->baseRequest()->post('/api/reminders', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateReminder(int $id, array $payload): array
    {
        return $this->unwrap($this->baseRequest()->put("/api/reminders/{$id}", $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleReminder(int $id): array
    {
        return $this->unwrap($this->baseRequest()->patch("/api/reminders/{$id}/toggle"));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteReminder(int $id): array
    {
        return $this->unwrap($this->baseRequest()->delete("/api/reminders/{$id}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function helpContacts(): array
    {
        return $this->unwrap($this->baseRequest()->get('/api/help/contacts'));
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(Response $response): array
    {
        if ($response->status() === 401) {
            throw new PortalApiException('Sesión API expirada o inválida', 401, $response->json());
        }

        if (! $response->successful()) {
            $message = $response->json('error')
                ?: $response->json('message')
                ?: 'Error al consultar portal-paciente-api';

            throw new PortalApiException((string) $message, $response->status(), $response->json());
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? $json;

        return is_array($data) ? $data : [];
    }
}
