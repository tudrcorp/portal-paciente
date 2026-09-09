<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Services\PortalApi\PortalApiException;
use App\Support\PortalDataSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historia clínica: último registro desde DB o API.
 * La generación del PDF se delega siempre al API (sin render en Laravel).
 */
final class ClinicalHistoryGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    public function hasHistory(TelemedicinePatient $patient): bool
    {
        if (PortalDataSource::usesApi()) {
            try {
                $data = $this->api->clinicalHistory();
                $historyPayload = $data['history'] ?? null;

                return is_array($historyPayload) && $historyPayload !== [];
            } catch (\Throwable) {
                return false;
            }
        }

        return TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->exists();
    }

    /**
     * @return array{history: mixed, isPatient: bool, patient: TelemedicinePatient|null}
     */
    public function forRequest(Request $request): array
    {
        $user = $request->user();
        $isPatient = $user instanceof TelemedicinePatient;

        if (! $isPatient) {
            return ['history' => null, 'isPatient' => false, 'patient' => null];
        }

        if (PortalDataSource::usesApi()) {
            $data = $this->api->clinicalHistory();
            $historyPayload = $data['history'] ?? null;

            // La vista usa getAttributes() de Eloquent: hidratamos el modelo sin guardar.
            $history = null;
            if (is_array($historyPayload) && $historyPayload !== []) {
                $history = new TelemedicineHistoryPatient;
                $history->forceFill($historyPayload);
                $history->exists = true;
            }

            return [
                'history' => $history,
                'isPatient' => true,
                'patient' => $user,
            ];
        }

        $history = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($history) {
            $this->attachRelatedSectionObservations($history, (int) $user->getKey());
        }

        return [
            'history' => $history,
            'isPatient' => true,
            'patient' => $user,
        ];
    }

    /**
     * Alta inicial de historia clínica desde el wizard del paciente.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromPatientInput(TelemedicinePatient $patient, array $data): TelemedicineHistoryPatient
    {
        if ($this->hasHistory($patient)) {
            abort(409, __('Ya tienes una historia clínica registrada.'));
        }

        $payload = $this->normalizePatientHistoryPayload($patient, $data);

        if (PortalDataSource::usesApi()) {
            try {
                $response = $this->api->storeClinicalHistory($payload);
            } catch (PortalApiException $exception) {
                abort($exception->status >= 400 ? $exception->status : 502, $exception->getMessage());
            }

            $historyPayload = is_array($response['history'] ?? null)
                ? $response['history']
                : $payload;

            $history = new TelemedicineHistoryPatient;
            $history->forceFill($historyPayload);
            $history->exists = true;

            return $history;
        }

        return TelemedicineHistoryPatient::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePatientHistoryPayload(TelemedicinePatient $patient, array $data): array
    {
        $booleanKeys = array_merge(
            array_keys(\App\Support\ClinicalHistoryPresentation::pathologicalFlags()),
            array_keys(\App\Support\ClinicalHistoryPresentation::appDeclaredFlags()),
            array_keys(\App\Support\ClinicalHistoryPresentation::habitFlags()),
        );

        $payload = [
            'telemedicine_patient_id' => $patient->getKey(),
            // Columnas NOT NULL en Integracorp.
            'code' => sprintf(
                'HIS-P%d-%s',
                (int) $patient->getKey(),
                strtoupper(base_convert((string) (int) round(microtime(true) * 1000), 10, 36))
            ),
            'created_by' => 'Portal del Paciente',
            'history_date' => now()->toDateString(),
            'allergies' => $this->nullableText($data['allergies'] ?? null),
            'observations_allergies' => $this->nullableText($data['observations_allergies'] ?? null),
            'history_surgical' => $this->nullableText($data['history_surgical'] ?? null),
            'observations_pathological' => $this->nullableText($data['observations_pathological'] ?? null),
            'observations_personal' => $this->nullableText($data['observations_personal'] ?? null),
            'observations_not_pathological' => $this->nullableText($data['observations_not_pathological'] ?? null),
            'medications_supplements' => $this->nullableText($data['medications_supplements'] ?? null),
            'observations_medication' => $this->nullableText($data['observations_medication'] ?? null),
            'observations_ginecologica' => $this->nullableText($data['observations_ginecologica'] ?? null),
            'edad_primera_menstruation' => $this->nullableText($data['edad_primera_menstruation'] ?? null),
            'fecha_ultima_regla' => $this->nullableText($data['fecha_ultima_regla'] ?? null),
            'numero_embarazos' => $this->nullableInt($data['numero_embarazos'] ?? null),
            'numero_partos' => $this->nullableInt($data['numero_partos'] ?? null),
            'numero_abortos' => $this->nullableInt($data['numero_abortos'] ?? null),
            'cesareas' => $this->nullableInt($data['cesareas'] ?? null),
        ];

        foreach ($booleanKeys as $key) {
            $payload[$key] = filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // Integracorp: allergies tiene CHECK json_valid(...).
        $payload['allergies'] = $this->encodeAllergiesJson(
            $data['allergies'] ?? null,
            (bool) ($data['no_allergies'] ?? false)
        );

        return $payload;
    }

    /**
     * Serializa alergias como JSON array (requisito de la BD Integracorp).
     */
    private function encodeAllergiesJson(mixed $value, bool $noAllergies): string
    {
        if ($noAllergies) {
            return '[]';
        }

        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                $value
            )));

            return json_encode($items, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === 'Ninguna conocida') {
            return '[]';
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $items = array_values(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                $decoded
            )));

            return json_encode($items, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        $items = preg_split('/[,;\n]+/', $text) ?: [];
        $items = array_values(array_filter(array_map('trim', $items)));

        return json_encode($items !== [] ? $items : [$text], JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Adjunta observaciones de tablas satélite (familiares, quirúrgicos, etc.).
     */
    private function attachRelatedSectionObservations(TelemedicineHistoryPatient $history, int $patientId): void
    {
        $historyId = (int) $history->getKey();

        $pickLatest = static function (string $table) use ($historyId, $patientId): ?string {
            $row = DB::table($table)
                ->where(function ($query) use ($historyId, $patientId): void {
                    $query->where('telemedicine_history_patient_id', $historyId)
                        ->orWhere('telemedicine_patient_id', $patientId);
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('observations');

            if ($row === null) {
                return null;
            }

            $text = trim((string) $row);

            return $text === '' ? null : $text;
        };

        $history->forceFill([
            'section_family_observations' => $pickLatest('family_histories'),
            'section_pathological_observations' => $pickLatest('pathological_histories'),
            'section_surgical_observations' => $pickLatest('surgical_histories'),
            'section_gynecological_observations' => $pickLatest('gynecological_histories'),
            'section_no_pathological_observations' => $pickLatest('no_pathological_histories'),
        ]);
    }

    /**
     * Proxifica el PDF generado por portal-paciente-api.
     */
    public function downloadPdf(TelemedicinePatient $patient): StreamedResponse
    {
        if (! PortalDataSource::usesApi()) {
            abort(503, __('La descarga en PDF está disponible cuando el portal opera con el API del paciente.'));
        }

        try {
            $response = $this->api->downloadClinicalHistoryPdf();
        } catch (PortalApiException $exception) {
            abort($exception->status >= 400 ? $exception->status : 502, $exception->getMessage());
        }

        $disposition = $response->header('Content-Disposition')
            ?: 'attachment; filename="historia-clinica.pdf"';
        $contentType = $response->header('Content-Type') ?: 'application/pdf';

        return response()->streamDownload(function () use ($response): void {
            echo $response->body();
        }, null, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
