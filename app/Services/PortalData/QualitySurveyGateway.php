<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\PatientCaseQualitySurvey;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Support\PortalDataSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Control 1:1 por caso (formulario in-app obligatorio).
 */
final class QualitySurveyGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    /**
     * @return array<int, bool> caseId => completed
     */
    public function completedMapFor(TelemedicinePatient $patient): array
    {
        if (PortalDataSource::usesApi()) {
            return [];
        }

        if (! Schema::hasTable('patient_case_quality_surveys')) {
            return [];
        }

        return PatientCaseQualitySurvey::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->pluck('telemedicine_case_id')
            ->mapWithKeys(fn ($caseId): array => [(int) $caseId => true])
            ->all();
    }

    public function isCompleted(TelemedicinePatient $patient, int $caseId): bool
    {
        if ($caseId <= 0) {
            return true;
        }

        if (PortalDataSource::usesApi()) {
            try {
                $status = $this->api->qualitySurveyStatus($caseId);

                return (bool) ($status['completed'] ?? false);
            } catch (\Throwable) {
                return false;
            }
        }

        if (! Schema::hasTable('patient_case_quality_surveys')) {
            return false;
        }

        return PatientCaseQualitySurvey::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->where('telemedicine_case_id', $caseId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function markCompleted(TelemedicinePatient $patient, int $caseId, array $answers): array
    {
        if (PortalDataSource::usesApi()) {
            return $this->api->completeQualitySurvey($caseId, $answers);
        }

        $case = TelemedicineCase::query()
            ->whereKey($caseId)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->firstOrFail();

        $survey = PatientCaseQualitySurvey::query()->updateOrCreate(
            [
                'telemedicine_patient_id' => $patient->getKey(),
                'telemedicine_case_id' => $case->getKey(),
            ],
            [
                'status' => 'completed',
                'form_url' => null,
                'source' => 'portal_form',
                'answers' => $answers,
                'completed_at' => Carbon::now(),
            ]
        );

        return [
            'telemedicine_case_id' => (int) $case->getKey(),
            'case_code' => (string) ($case->code ?: 'CASO-'.$case->getKey()),
            'completed' => true,
            'completed_at' => optional($survey->completed_at)?->toIso8601String(),
            'source' => 'portal_form',
            'answers' => $answers,
        ];
    }

    public function assertCompletedOrAbort(TelemedicinePatient $patient, ?int $caseId): void
    {
        if ($caseId === null || $caseId <= 0) {
            return;
        }

        abort_unless(
            $this->isCompleted($patient, $caseId),
            403,
            __('Debes completar el cuestionario de control de calidad de este caso antes de descargar documentos.')
        );
    }
}
