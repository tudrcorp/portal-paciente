<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientUploadedDocument;
use App\Services\PortalApi\PortalApiClient;
use App\Support\ClinicalDocumentStorage;
use App\Support\PatientDocumentsPresentation;
use App\Support\PortalDataSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos/casos: listado, upload y download vía DB o API.
 */
final class DocumentsGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    /**
     * @return array{
     *     isPatient: bool,
     *     cases: Collection|array,
     *     generalDocuments: array,
     *     patientCases: Collection|array,
     *     filterOptions: array,
     *     summary: array
     * }
     */
    public function indexPayload(TelemedicinePatient $patient): array
    {
        if (PortalDataSource::usesApi()) {
            // Normalizamos la respuesta del API a la forma que espera Blade
            // (uid, search_blob, date_key, types_label, etc.).
            return $this->normalizeApiDocumentsPayload($this->api->documents(), $patient);
        }

        $payload = PatientDocumentsPresentation::build($patient);

        $patientCases = $payload['cases']->map(fn (array $case): array => [
            'id' => $case['id'],
            'code' => $case['code'],
            'label' => collect([
                $case['code'],
                ($case['reference_date_label'] ?? null) !== __('Sin fecha') ? ($case['reference_date_label'] ?? null) : null,
                ($case['services_label'] ?? null) !== __('Sin servicio registrado') ? ($case['services_label'] ?? null) : null,
            ])->filter()->implode(' · '),
        ]);

        return [
            'isPatient' => true,
            'cases' => $payload['cases'],
            'generalDocuments' => $payload['general_documents'],
            'patientCases' => $patientCases,
            'filterOptions' => $payload['filter_options'],
            'summary' => $payload['summary'],
        ];
    }

    /**
     * IDs de casos del paciente (para validar uploads).
     *
     * @return list<int>
     */
    public function caseIdsFor(TelemedicinePatient $patient): array
    {
        if (PortalDataSource::usesApi()) {
            return collect($this->api->cases())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function storeUpload(
        TelemedicinePatient $patient,
        UploadedFile $file,
        string $documentName,
        string $uploadReason,
        ?int $caseId,
    ): array {
        if (PortalDataSource::usesApi()) {
            return $this->api->uploadDocument(
                [
                    'document_name' => $documentName,
                    'upload_reason' => $uploadReason,
                    'telemedicine_case_id' => $caseId,
                ],
                $file->getRealPath() ?: $file->getPathname(),
                $file->getClientOriginalName()
            );
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = Str::ulid().($extension !== '' ? '.'.$extension : '');
        $directory = 'portal-patient-documents/'.$patient->getKey();
        $file->storeAs($directory, $storedFilename, 'public');

        $document = TelemedicinePatientUploadedDocument::query()->create([
            'telemedicine_patient_id' => $patient->getKey(),
            'telemedicine_case_id' => $caseId,
            'document_name' => $documentName,
            'upload_reason' => $uploadReason,
            'stored_filename' => $storedFilename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return ['id' => $document->id];
    }

    public function download(TelemedicinePatient $patient, string $source, int $id): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        if (PortalDataSource::usesApi()) {
            $response = $this->api->downloadDocument($source, $id);
            $disposition = $response->header('Content-Disposition') ?: 'attachment';
            $contentType = $response->header('Content-Type') ?: 'application/octet-stream';

            return response()->streamDownload(function () use ($response): void {
                echo $response->body();
            }, null, [
                'Content-Type' => $contentType,
                'Content-Disposition' => $disposition,
            ]);
        }

        // Delegamos a la lógica original del controlador.
        return app(\App\Http\Controllers\PatientDocumentsController::class)
            ->downloadFromDatabase($patient, $source, $id);
    }

    /**
     * Adapta el JSON del API a la estructura que usan patient-documents.blade.php
     * y x-portal.document-card (uid, search_blob, date_key, types_label...).
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     isPatient: bool,
     *     cases: Collection,
     *     generalDocuments: array,
     *     patientCases: Collection,
     *     filterOptions: array,
     *     summary: array
     * }
     */
    private function normalizeApiDocumentsPayload(array $data, TelemedicinePatient $patient): array
    {
        $patientName = (string) ($patient->full_name ?? '');

        $cases = collect($data['cases'] ?? [])->map(function (array $case) use ($patientName): array {
            $doctors = array_values(array_filter($case['doctors'] ?? []));
            $services = array_values(array_filter($case['services'] ?? []));
            $documents = collect($case['documents'] ?? [])
                ->map(fn (array $document): array => $this->normalizeApiDocument(
                    $document,
                    $case['code'] ?? null,
                    $doctors,
                    $services,
                    $patientName,
                ))
                ->values()
                ->all();

            $referenceDate = $this->parseApiDate($case['reference_date'] ?? $case['updated_at'] ?? null);

            return [
                'id' => (int) ($case['id'] ?? 0),
                'code' => (string) ($case['code'] ?? ('CASO-'.($case['id'] ?? 0))),
                'status' => filled($case['status'] ?? null)
                    ? str((string) $case['status'])->replace('_', ' ')->upper()->toString()
                    : __('Sin estado'),
                'reason' => $case['reason'] ?? null,
                'updated_at' => $case['updated_at'] ?? null,
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
                'documents' => $documents,
                'document_count' => count($documents),
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    $case['code'] ?? '',
                    $case['reason'] ?? '',
                    $case['status'] ?? '',
                    implode(' ', $doctors),
                    implode(' ', $services),
                    collect($documents)->pluck('document_name')->implode(' '),
                ]))),
            ];
        });

        $generalDocuments = collect($data['general_documents'] ?? [])
            ->map(fn (array $document): array => $this->normalizeApiDocument(
                $document,
                __('Documentos generales'),
                patientName: $patientName,
            ))
            ->values()
            ->all();

        $patientCases = collect($data['patient_cases'] ?? [])->map(fn (array $case): array => [
            'id' => (int) ($case['id'] ?? 0),
            'code' => (string) ($case['code'] ?? ('CASO-'.($case['id'] ?? 0))),
            'label' => (string) ($case['label'] ?? ($case['code'] ?? ('CASO-'.($case['id'] ?? 0)))),
        ]);

        if ($patientCases->isEmpty()) {
            $patientCases = $cases->map(fn (array $case): array => [
                'id' => $case['id'],
                'code' => $case['code'],
                'label' => collect([
                    $case['code'],
                    $case['reference_date_label'] !== __('Sin fecha') ? $case['reference_date_label'] : null,
                    $case['services_label'] !== __('Sin servicio registrado') ? $case['services_label'] : null,
                ])->filter()->implode(' · '),
            ]);
        }

        return [
            'isPatient' => true,
            'cases' => $cases,
            'generalDocuments' => $generalDocuments,
            'patientCases' => $patientCases,
            'filterOptions' => $this->buildFilterOptionsFromNormalized($cases, $generalDocuments),
            'summary' => $data['summary'] ?? [
                'cases' => $cases->count(),
                'documents' => $cases->sum('document_count') + count($generalDocuments),
                'patient_uploads' => collect($generalDocuments)
                    ->merge($cases->flatMap(fn (array $case) => $case['documents']))
                    ->where('source', 'patient_upload')
                    ->count(),
            ],
        ];
    }

    /**
     * Opciones de filtro (años, meses, doctores, servicios) para la UI de búsqueda.
     *
     * @param  Collection<int, array<string, mixed>>  $cases
     * @param  array<int, array<string, mixed>>  $generalDocuments
     * @return array<string, mixed>
     */
    private function buildFilterOptionsFromNormalized(Collection $cases, array $generalDocuments): array
    {
        $dateKeys = $cases
            ->flatMap(function (array $case): Collection {
                return collect($case['documents'] ?? [])
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
            ->flatMap(fn (array $case) => $case['doctors'] ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $doctor): array => ['value' => $doctor, 'label' => $doctor])
            ->all();

        $services = $cases
            ->flatMap(fn (array $case) => $case['services'] ?? [])
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

    /**
     * Completa campos que la UI usa y el API MVP aún no envía.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $doctors
     * @param  list<string>  $services
     * @return array<string, mixed>
     */
    private function normalizeApiDocument(
        array $document,
        ?string $caseLabel = null,
        array $doctors = [],
        array $services = [],
        string $patientName = '',
    ): array {
        $source = (string) ($document['source'] ?? 'unknown');
        $sourceId = (int) ($document['source_id'] ?? 0);
        $caseId = isset($document['case_id']) ? (int) $document['case_id'] : null;
        $filePath = ltrim((string) ($document['file_path'] ?? ''), '/');
        $types = array_values(array_filter(array_map('strval', $document['types'] ?? [])));
        $extension = strtolower((string) pathinfo($filePath !== '' ? $filePath : (string) ($document['document_name'] ?? ''), PATHINFO_EXTENSION));
        $timestamp = $this->parseApiDate($document['uploaded_at'] ?? null);
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);

        $uid = (string) ($document['uid'] ?? md5($source.'|'.$sourceId.'|'.$caseId.'|'.$filePath));
        $documentName = PatientDocumentsPresentation::formatClinicalDocumentName(
            (string) ($document['document_name'] ?? 'documento'),
            $patientName,
        );

        $normalized = [
            ...$document,
            'uid' => $uid,
            'source' => $source,
            'source_id' => $sourceId,
            'case_id' => $caseId,
            'case_code' => $document['case_code'] ?? $caseLabel,
            'category' => (string) ($document['category'] ?? ''),
            'document_name' => $documentName,
            'upload_reason' => $document['upload_reason'] ?? null,
            'types' => $types,
            'types_label' => $types !== [] ? implode(' · ', $types) : '—',
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'file_path' => $filePath,
            'uploaded_at' => $timestamp,
            'uploaded_at_label' => $timestamp?->translatedFormat('d M Y H:i') ?? '—',
            'uploaded_at_relative' => $timestamp?->diffForHumans() ?? '',
            'date_key' => $timestamp?->format('Y-m-d') ?? '',
            'year_key' => $timestamp?->format('Y') ?? '',
            'month_part' => $timestamp?->format('m') ?? '',
            'month_key' => $timestamp?->format('Y-m') ?? '',
            'exists' => (bool) ($document['exists'] ?? false)
                || ($filePath !== '' && ClinicalDocumentStorage::exists($filePath)),
            'is_image' => (bool) ($document['is_image'] ?? $isImage),
            'preview_url' => $document['preview_url']
                ?? (($filePath !== '' && ClinicalDocumentStorage::exists($filePath) && $isImage)
                    ? (Storage::disk('public')->exists($filePath)
                        ? Storage::disk('public')->url($filePath)
                        : ClinicalDocumentStorage::publicUrl($filePath))
                    : null),
        ];

        $normalized['search_blob'] = mb_strtolower(implode(' ', array_filter([
            $caseLabel,
            $normalized['document_name'],
            $normalized['upload_reason'] ?? '',
            $normalized['category'],
            $normalized['types_label'],
            $normalized['extension'],
            implode(' ', $doctors),
            implode(' ', $services),
            $timestamp?->format('d/m/Y') ?? '',
        ])));

        return $normalized;
    }

    private function parseApiDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
