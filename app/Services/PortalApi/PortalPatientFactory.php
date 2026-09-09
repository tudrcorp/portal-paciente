<?php

declare(strict_types=1);

namespace App\Services\PortalApi;

use App\Models\TelemedicinePatient;

/**
 * Hidrata un TelemedicinePatient desde el JSON del API (sin tocar MySQL).
 */
final class PortalPatientFactory
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApiPayload(array $payload): TelemedicinePatient
    {
        $patient = new TelemedicinePatient;
        $patient->forceFill([
            'id' => (int) ($payload['id'] ?? 0),
            'full_name' => trim((string) ($payload['full_name'] ?? $payload['name'] ?? '')),
            'nro_identificacion' => $payload['nro_identificacion'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'phone_contact' => $payload['phone_contact'] ?? null,
            'address' => $payload['address'] ?? null,
            'birth_date' => $payload['birth_date'] ?? null,
            'sex' => $payload['sex'] ?? null,
            'age' => $payload['age'] ?? null,
            'afilliation_corporate_id' => $payload['afilliation_corporate_id'] ?? 0,
            'afilliation_id' => $payload['afilliation_id'] ?? 0,
        ]);
        // Asegura el nombre aunque fillable esté vacío / guarded.
        if (($patient->getAttributes()['full_name'] ?? '') === '' && isset($payload['full_name'])) {
            $patient->setAttribute('full_name', trim((string) $payload['full_name']));
        }
        $patient->exists = true;

        return $patient;
    }
}
