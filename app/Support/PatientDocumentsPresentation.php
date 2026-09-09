<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PatientDocumentsPresentation
{
    /**
     * Normaliza nombres clínicos tipo:
     * "26-03-2026 RESULTADOS LABS TIROIDEO BECKY ACOSTA TDC-1226"
     * → "RESULTADOS LABS TIROIDEO"
     */
    public static function formatClinicalDocumentName(string $raw, ?string $patientName = null): string
    {
        $original = trim($raw);
        if ($original === '') {
            return $original;
        }

        $withoutExtension = pathinfo($original, PATHINFO_FILENAME);
        $base = trim($withoutExtension !== '' ? $withoutExtension : $original);

        if (! preg_match('/^(\d{2}-\d{2}-\d{4})[\s_]+(.+)$/u', $base, $matches)) {
            return $original;
        }

        $rest = trim((string) $matches[2]);

        // Quita códigos de caso (TDC-1226, TDC1029-1, TDC1226, etc.).
        $rest = preg_replace('/\bTDC[-\s]?\d+(?:-\d+)?\b/iu', ' ', $rest) ?? $rest;

        $rest = self::stripPatientNameTokens($rest, $patientName);

        $rest = preg_replace('/\s*[-–,._]+\s*/u', ' ', $rest) ?? $rest;
        $rest = preg_replace('/\s+/u', ' ', $rest) ?? $rest;
        $rest = trim($rest, " \t\n\r\0\x0B-_,.");

        if ($rest === '') {
            return $original;
        }

        return $rest;
    }

    /**
     * Elimina del título tokens del nombre del paciente (completo o abreviado).
     */
    private static function stripPatientNameTokens(string $text, ?string $patientName): string
    {
        if (! filled($patientName)) {
            return $text;
        }

        $normalizedPatient = preg_replace('/\s+/u', ' ', trim($patientName)) ?? '';
        if ($normalizedPatient === '') {
            return $text;
        }

        // Primero intenta coincidencia completa.
        $escaped = preg_quote($normalizedPatient, '/');
        $fullPattern = preg_replace('/\s+/u', '\s+', $escaped) ?? $escaped;
        $text = preg_replace('/'.$fullPattern.'/iu', ' ', $text) ?? $text;

        $particles = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'das', 'dos'];
        $tokens = collect(preg_split('/\s+/u', mb_strtoupper($normalizedPatient)) ?: [])
            ->filter(fn (string $token): bool => $token !== '' && ! in_array(mb_strtolower($token), $particles, true))
            ->values()
            ->all();

        if ($tokens === []) {
            return $text;
        }

        // Quita cualquier token del nombre que aparezca en el título.
        foreach ($tokens as $token) {
            $text = preg_replace('/\b'.preg_quote($token, '/').'\b/iu', ' ', $text) ?? $text;
        }

        return $text;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public static function clinicalDocumentNameLines(string $documentName): array
    {
        $parts = preg_split("/\r\n|\n|\r/", $documentName) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return ['', null];
        }

        if (count($parts) === 1) {
            return [$parts[0], null];
        }

        return [$parts[0], implode(' ', array_slice($parts, 1))];
    }

    /**
     * @return array{
     *     cases: Collection<int, array<string, mixed>>,
     *     general_documents: array<int, array<string, mixed>>,
     *     filter_options: array<string, mixed>,
     *     summary: array<string, int>
     * }
     */
    public static function build(TelemedicinePatient $patient): array
    {
        $serviceMap = DB::table('telemedicine_service_lists')->pluck('name', 'id');
        $doctorMap = DB::table('telemedicine_doctors')->pluck('full_name', 'id');

        $cases = TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $caseIds = $cases->pluck('id')->all();

        $consultationsByCase = TelemedicineConsultationPatient::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->when($caseIds !== [], fn ($query) => $query->whereIn('telemedicine_case_id', $caseIds))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('telemedicine_case_id');

        $catalogByCase = collect(PatientClinicalDocumentsCatalog::casesWithDocuments($patient))->keyBy('id');

        $casesPayload = $cases->map(function (TelemedicineCase $case) use (
            $patient,
            $consultationsByCase,
            $catalogByCase,
            $serviceMap,
            $doctorMap
        ): array {
            $caseConsultations = ($consultationsByCase->get($case->id) ?? collect())->values();
            $documents = $catalogByCase->get($case->id)['documents']
                ?? PatientClinicalDocumentsCatalog::documentsForCase($case, $patient);

            $doctors = self::resolveDoctors($case, $caseConsultations, $doctorMap);
            $services = self::resolveServices($caseConsultations, $serviceMap);
            $referenceDate = self::resolveReferenceDate($case, $caseConsultations);

            return [
                'id' => $case->id,
                'code' => filled($case->code) ? (string) $case->code : 'CASO-'.$case->id,
                'status' => self::formatStatus($case->status),
                'reason' => $case->reason,
                'updated_at' => $case->updated_at,
                'reference_date' => $referenceDate,
                'reference_date_key' => $referenceDate?->format('Y-m-d') ?? '',
                'reference_date_label' => $referenceDate?->translatedFormat('d M Y') ?? __('Sin fecha'),
                'year_key' => $referenceDate?->format('Y') ?? '',
                'month_key' => $referenceDate?->format('Y-m') ?? '',
                'month_part' => $referenceDate?->format('m') ?? '',
                'month_label' => $referenceDate?->translatedFormat('F Y') ?? __('Sin fecha'),
                'doctors' => $doctors,
                'doctors_label' => $doctors !== [] ? implode(', ', $doctors) : __('Sin doctor registrado'),
                'services' => $services,
                'services_label' => $services !== [] ? implode(', ', $services) : __('Sin servicio registrado'),
                'documents' => self::enrichDocuments($documents, $case, $doctors, $services, $referenceDate),
                'document_count' => count($documents),
                'search_blob' => self::caseSearchBlob($case, $doctors, $services, $documents),
            ];
        });

        $generalDocuments = self::enrichGeneralDocuments(
            PatientClinicalDocumentsCatalog::generalPatientDocuments($patient)
        );

        $filterOptions = self::buildFilterOptions($casesPayload, $generalDocuments);

        $patientUploadCount = $casesPayload
            ->flatMap(fn (array $case) => $case['documents'])
            ->filter(fn (array $document): bool => ($document['source'] ?? '') === 'patient_upload')
            ->count()
            + collect($generalDocuments)
                ->filter(fn (array $document): bool => ($document['source'] ?? '') === 'patient_upload')
                ->count();

        return [
            'cases' => $casesPayload,
            'general_documents' => $generalDocuments,
            'filter_options' => $filterOptions,
            'summary' => [
                'cases' => $casesPayload->count(),
                'documents' => $casesPayload->sum('document_count') + count($generalDocuments),
                'patient_uploads' => $patientUploadCount,
            ],
        ];
    }

    /**
     * @param  Collection<int, TelemedicineConsultationPatient>  $consultations
     * @return array<int, string>
     */
    private static function resolveDoctors(
        TelemedicineCase $case,
        Collection $consultations,
        Collection $doctorMap,
    ): array {
        $doctorIds = collect([$case->telemedicine_doctor_id])
            ->merge($consultations->pluck('telemedicine_doctor_id'))
            ->filter()
            ->unique()
            ->values();

        return $doctorIds
            ->map(fn ($id) => trim((string) ($doctorMap[$id] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TelemedicineConsultationPatient>  $consultations
     * @return array<int, string>
     */
    private static function resolveServices(Collection $consultations, Collection $serviceMap): array
    {
        return $consultations
            ->map(fn (TelemedicineConsultationPatient $consultation) => $serviceMap[$consultation->telemedicine_service_list_id] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TelemedicineConsultationPatient>  $consultations
     */
    private static function resolveReferenceDate(TelemedicineCase $case, Collection $consultations): ?Carbon
    {
        $consultationDate = $consultations
            ->map(fn (TelemedicineConsultationPatient $consultation) => $consultation->created_at ?? $consultation->updated_at)
            ->filter()
            ->sortDesc()
            ->first();

        return $consultationDate instanceof Carbon
            ? $consultationDate
            : ($case->updated_at instanceof Carbon ? $case->updated_at : $case->created_at);
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private static function enrichDocuments(array $documents, TelemedicineCase $case, array $doctors = [], array $services = [], ?Carbon $referenceDate = null): array
    {
        $caseCode = filled($case->code) ? (string) $case->code : 'CASO-'.$case->id;

        return collect($documents)
            ->map(function (array $document) use ($caseCode, $doctors, $services, $referenceDate): array {
                $document['case_code'] = $caseCode;
                $document = self::applyDocumentDateMetadata($document, $referenceDate);
                $document['search_blob'] = self::documentSearchBlob(
                    $document,
                    $caseCode,
                    $doctors,
                    $services,
                    $referenceDate,
                );

                return $document;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private static function applyDocumentDateMetadata(array $document, ?Carbon $fallbackDate = null): array
    {
        $timestamp = $document['uploaded_at'] ?? null;

        if (! $timestamp instanceof Carbon && filled($timestamp)) {
            try {
                $timestamp = Carbon::parse((string) $timestamp);
            } catch (\Throwable) {
                $timestamp = null;
            }
        }

        if (! $timestamp instanceof Carbon) {
            $timestamp = $fallbackDate instanceof Carbon ? $fallbackDate : null;
        }

        $document['date_key'] = $timestamp?->format('Y-m-d') ?? '';
        $document['year_key'] = $timestamp?->format('Y') ?? '';
        $document['month_part'] = $timestamp?->format('m') ?? '';
        $document['month_key'] = $timestamp?->format('Y-m') ?? '';

        return $document;
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private static function enrichGeneralDocuments(array $documents): array
    {
        return collect($documents)
            ->map(function (array $document): array {
                $document['case_code'] = null;
                $document = self::applyDocumentDateMetadata($document);
                $document['search_blob'] = self::documentSearchBlob($document, __('Documentos generales'));

                return $document;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    private static function caseSearchBlob(
        TelemedicineCase $case,
        array $doctors,
        array $services,
        array $documents,
    ): string {
        return mb_strtolower(implode(' ', array_filter([
            $case->code,
            $case->reason,
            $case->status,
            implode(' ', $doctors),
            implode(' ', $services),
            collect($documents)->pluck('document_name')->implode(' '),
            collect($documents)->pluck('upload_reason')->implode(' '),
            collect($documents)->pluck('category')->implode(' '),
        ])));
    }

    private static function documentSearchBlob(
        array $document,
        ?string $caseLabel = null,
        array $doctors = [],
        array $services = [],
        ?Carbon $referenceDate = null,
    ): string {
        return mb_strtolower(implode(' ', array_filter([
            $caseLabel,
            $document['document_name'] ?? '',
            $document['upload_reason'] ?? '',
            $document['category'] ?? '',
            $document['types_label'] ?? '',
            $document['extension'] ?? '',
            $document['uploaded_at_label'] ?? '',
            implode(' ', $doctors),
            implode(' ', $services),
            $referenceDate?->translatedFormat('d M Y'),
            $referenceDate?->translatedFormat('F Y'),
            $referenceDate?->format('Y-m'),
        ])));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cases
     * @param  array<int, array<string, mixed>>  $generalDocuments
     * @return array<string, mixed>
     */
    private static function buildFilterOptions(Collection $cases, array $generalDocuments): array
    {
        $dateKeys = $cases
            ->flatMap(function (array $case): Collection {
                return collect($case['documents'])
                    ->pluck('date_key')
                    ->push($case['reference_date_key'] ?? '');
            })
            ->merge(collect($generalDocuments)->pluck('date_key'))
            ->filter(fn (mixed $value): bool => filled($value))
            ->unique()
            ->sort()
            ->values();

        $years = $dateKeys
            ->map(fn (string $dateKey): string => substr($dateKey, 0, 4))
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $year): array => ['value' => $year, 'label' => $year])
            ->all();

        $months = collect(range(1, 12))
            ->map(function (int $month): array {
                $value = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

                return [
                    'value' => $value,
                    'label' => Carbon::createFromDate(2000, $month, 1)->translatedFormat('F'),
                ];
            })
            ->all();

        $doctors = $cases
            ->flatMap(fn (array $case) => $case['doctors'])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $doctor): array => ['value' => $doctor, 'label' => $doctor])
            ->all();

        $services = $cases
            ->flatMap(fn (array $case) => $case['services'])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $service): array => ['value' => $service, 'label' => $service])
            ->all();

        return [
            'years' => $years,
            'months' => $months,
            'doctors' => $doctors,
            'services' => $services,
            'date_min' => $dateKeys->first() ?: '',
            'date_max' => $dateKeys->last() ?: '',
            'has_general_documents' => count($generalDocuments) > 0,
        ];
    }

    private static function formatStatus(?string $status): string
    {
        return $status
            ? str($status)->replace('_', ' ')->upper()->toString()
            : __('Sin estado');
    }
}
