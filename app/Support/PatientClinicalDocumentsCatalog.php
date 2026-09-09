<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientUploadedDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PatientClinicalDocumentsCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function casesWithDocuments(TelemedicinePatient $patient): array
    {
        $cases = TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($cases->isEmpty()) {
            return [];
        }

        return $cases
            ->map(function (TelemedicineCase $case) use ($patient): array {
                $documents = self::documentsForCase($case, $patient);

                return [
                    'id' => $case->id,
                    'code' => filled($case->code) ? (string) $case->code : 'CASO-'.$case->id,
                    'status' => self::formatStatus($case->status),
                    'reason' => $case->reason,
                    'updated_at' => $case->updated_at,
                    'documents' => $documents,
                    'document_count' => count($documents),
                ];
            })
            ->filter(fn (array $case): bool => $case['document_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function documentsForCase(TelemedicineCase $case, TelemedicinePatient $patient): array
    {
        if ((int) $case->telemedicine_patient_id !== (int) $patient->getKey()) {
            return [];
        }

        $entries = collect();
        $caseLabel = filled($case->code) ? (string) $case->code : 'CASO-'.$case->id;
        $patientName = (string) ($patient->full_name ?? '');

        TelemedicineDocument::query()
            ->where('telemedicine_case_id', $case->id)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->each(function (TelemedicineDocument $document) use ($entries, $case, $caseLabel, $patientName): void {
                $fileName = ltrim((string) $document->name, '/');
                $relativePath = 'telemedicina-doc/'.$fileName;

                $entries->push(self::makeEntry(
                    source: 'telemedicine_document',
                    sourceId: (int) $document->id,
                    caseId: (int) $case->id,
                    caseCode: $caseLabel,
                    category: __('Referencia médica'),
                    documentName: basename($fileName),
                    uploadReason: null,
                    types: [__('Consignación del caso')],
                    filePath: $relativePath,
                    uploadedAt: $document->created_at,
                    patientName: $patientName,
                ));
            });

        self::appendConsultationDocuments($entries, $case, $patientName);
        self::appendCoordinationDocuments($entries, $case, $caseLabel, $patientName);
        self::appendPatientUploadedDocuments($entries, $case, $patient);

        return self::finalizeEntries($entries);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private static function appendConsultationDocuments(Collection $entries, TelemedicineCase $case, string $patientName = ''): void
    {
        if (! Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            return;
        }

        $consultations = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('created_at')
            ->get();

        foreach ($consultations as $consultation) {
            $reference = filled($consultation->code_reference)
                ? (string) $consultation->code_reference
                : 'CONS-'.$consultation->id;

            foreach (self::normalizeUploadedDocuments($consultation->uploaded_documents) as $uploaded) {
                $entry = self::entryFromUploadedDocument(
                    uploaded: $uploaded,
                    source: 'consultation_upload',
                    sourceId: (int) $consultation->id,
                    caseId: (int) $case->id,
                    caseCode: filled($case->code) ? (string) $case->code : 'CASO-'.$case->id,
                    category: __('Consulta telemedicina'),
                    reference: $reference,
                    fallbackUploadedAt: $consultation->updated_at ?? $consultation->created_at,
                    patientName: $patientName,
                );

                if ($entry !== null) {
                    $entries->push($entry);
                }
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private static function appendCoordinationDocuments(
        Collection $entries,
        TelemedicineCase $case,
        string $caseLabel,
        string $patientName = '',
    ): void {
        if (! Schema::hasTable('operation_coordination_services')) {
            return;
        }

        $coordinations = DB::table('operation_coordination_services')
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('created_at')
            ->get();

        if ($coordinations->isEmpty()) {
            return;
        }

        $coordinationIds = $coordinations->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($coordinations as $coordination) {
            $coordinationReference = filled($coordination->reference_number ?? null)
                ? (string) $coordination->reference_number
                : 'COO-'.str_pad((string) $coordination->id, 6, '0', STR_PAD_LEFT);

            if (Schema::hasColumn('operation_coordination_services', 'uploaded_documents')) {
                foreach (self::normalizeUploadedDocuments($coordination->uploaded_documents ?? null) as $uploaded) {
                    $entry = self::entryFromUploadedDocument(
                        uploaded: $uploaded,
                        source: 'coordination_upload',
                        sourceId: (int) $coordination->id,
                        caseId: (int) $case->id,
                        caseCode: $caseLabel,
                        category: __('Coordinación clínica'),
                        reference: $coordinationReference,
                        fallbackUploadedAt: $coordination->updated_at ?? $coordination->created_at,
                        patientName: $patientName,
                    );

                    if ($entry !== null) {
                        $entries->push($entry);
                    }
                }
            }
        }

        if (Schema::hasTable('operation_coordination_clinic_documents')) {
            $clinicDocuments = DB::table('operation_coordination_clinic_documents')
                ->whereIn('operation_coordination_service_id', $coordinationIds)
                ->orderByDesc('created_at')
                ->get();

            foreach ($clinicDocuments as $clinicDocument) {
                $category = match ((string) ($clinicDocument->category ?? '')) {
                    'ingreso' => __('Clínica · Ingreso'),
                    'egreso' => __('Clínica · Egreso'),
                    default => __('Documento clínico'),
                };

                $path = ltrim((string) ($clinicDocument->path ?? ''), '/');
                if ($path === '') {
                    continue;
                }

                $entries->push(self::makeEntry(
                    source: 'clinic_document',
                    sourceId: (int) $clinicDocument->id,
                    caseId: (int) $case->id,
                    caseCode: $caseLabel,
                    category: $category,
                    documentName: filled($clinicDocument->original_filename ?? null)
                        ? (string) $clinicDocument->original_filename
                        : basename($path),
                    uploadReason: null,
                    types: [ucfirst((string) ($clinicDocument->category ?? __('clínico')))],
                    filePath: $path,
                    uploadedAt: $clinicDocument->created_at ?? null,
                    patientName: $patientName,
                ));
            }
        }

        if (! Schema::hasTable('operation_service_orders')) {
            return;
        }

        $orders = DB::table('operation_service_orders')
            ->whereIn('operation_coordination_service_id', $coordinationIds)
            ->orderByDesc('created_at')
            ->get();

        foreach ($orders as $order) {
            $orderReference = filled($order->order_number ?? null)
                ? (string) $order->order_number
                : 'OS-'.$order->id;

            if (filled($order->service_order_pdf_path ?? null)) {
                $entries->push(self::makeEntry(
                    source: 'service_order',
                    sourceId: (int) $order->id,
                    caseId: (int) $case->id,
                    caseCode: $caseLabel,
                    category: __('Orden de servicio'),
                    documentName: 'orden-servicio-'.$order->id.'.pdf',
                    uploadReason: null,
                    types: array_filter([(string) ($order->service_type ?? ''), (string) ($order->status ?? '')]),
                    filePath: (string) $order->service_order_pdf_path,
                    uploadedAt: $order->updated_at ?? $order->created_at,
                    patientName: $patientName,
                ));
            }

            if (Schema::hasColumn('operation_service_orders', 'uploaded_documents')) {
                foreach (self::normalizeUploadedDocuments($order->uploaded_documents ?? null) as $uploaded) {
                    $entry = self::entryFromUploadedDocument(
                        uploaded: $uploaded,
                        source: 'service_order_upload',
                        sourceId: (int) $order->id,
                        caseId: (int) $case->id,
                        caseCode: $caseLabel,
                        category: __('Orden de servicio'),
                        reference: $orderReference,
                        fallbackUploadedAt: $order->updated_at ?? $order->created_at,
                        patientName: $patientName,
                    );

                    if ($entry !== null) {
                        $entries->push($entry);
                    }
                }
            }
        }
    }

    /**
     * Documentos cargados por el paciente sin caso asociado.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function generalPatientDocuments(TelemedicinePatient $patient): array
    {
        if (! Schema::hasTable('telemedicine_patient_uploaded_documents')) {
            return [];
        }

        $entries = collect();
        $patientName = (string) ($patient->full_name ?? '');

        TelemedicinePatientUploadedDocument::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->whereNull('telemedicine_case_id')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (TelemedicinePatientUploadedDocument $document) use ($entries, $patientName): void {
                $entries->push(self::makeEntry(
                    source: 'patient_upload',
                    sourceId: (int) $document->id,
                    caseId: null,
                    caseCode: __('Sin caso asociado'),
                    category: __('Mis documentos'),
                    documentName: (string) $document->document_name,
                    uploadReason: (string) $document->upload_reason,
                    types: [__('Documento externo'), __('Cargado por ti')],
                    filePath: $document->storageRelativePath(),
                    uploadedAt: $document->created_at,
                    patientName: $patientName,
                ));
            });

        return self::finalizeEntries($entries);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private static function appendPatientUploadedDocuments(Collection $entries, TelemedicineCase $case, TelemedicinePatient $patient): void
    {
        if (! Schema::hasTable('telemedicine_patient_uploaded_documents')) {
            return;
        }

        $caseLabel = filled($case->code) ? (string) $case->code : 'CASO-'.$case->id;
        $patientName = (string) ($patient->full_name ?? '');

        TelemedicinePatientUploadedDocument::query()
            ->where('telemedicine_case_id', $case->id)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->each(function (TelemedicinePatientUploadedDocument $document) use ($entries, $case, $caseLabel, $patientName): void {
                $entries->push(self::makeEntry(
                    source: 'patient_upload',
                    sourceId: (int) $document->id,
                    caseId: (int) $case->id,
                    caseCode: $caseLabel,
                    category: __('Mis documentos'),
                    documentName: (string) $document->document_name,
                    uploadReason: (string) $document->upload_reason,
                    types: [__('Cargado por ti')],
                    filePath: $document->storageRelativePath(),
                    uploadedAt: $document->created_at,
                    patientName: $patientName,
                ));
            });
    }

    /**
     * @param  array<string, mixed>  $uploaded
     * @return array<string, mixed>|null
     */
    private static function entryFromUploadedDocument(
        array $uploaded,
        string $source,
        int $sourceId,
        ?int $caseId,
        string $caseCode,
        string $category,
        string $reference,
        mixed $fallbackUploadedAt,
        string $patientName = '',
    ): ?array {
        $filePath = trim((string) ($uploaded['file_path'] ?? ''));
        if ($filePath === '') {
            return null;
        }

        $documentName = trim((string) ($uploaded['document_name'] ?? ''));
        if ($documentName === '') {
            $documentName = basename($filePath);
        }

        $types = is_array($uploaded['document_types'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $type): string => trim((string) $type),
                $uploaded['document_types']
            )))
            : [];

        if (self::isExcludedFinancialDocument($category, $documentName, $types)) {
            return null;
        }

        $uploadedAt = filled($uploaded['uploaded_at'] ?? null)
            ? (string) $uploaded['uploaded_at']
            : null;
        $resolvedUploadedAt = $uploadedAt ? Carbon::parse($uploadedAt) : $fallbackUploadedAt;

        return self::makeEntry(
            source: $source,
            // Cada PDF del JSON necesita un source_id único para la descarga.
            sourceId: self::documentSourceId(
                $filePath,
                $resolvedUploadedAt instanceof Carbon
                    ? $resolvedUploadedAt->format('Y-m-d H:i:s')
                    : (string) ($uploadedAt ?? ''),
                $sourceId,
            ),
            caseId: $caseId,
            caseCode: $caseCode,
            category: $category,
            documentName: $documentName,
            uploadReason: null,
            types: $types !== [] ? $types : [$reference],
            filePath: $filePath,
            uploadedAt: $resolvedUploadedAt,
            patientName: $patientName,
        );
    }

    /**
     * ID estable para entradas JSON (alineado con portal-paciente-api).
     */
    private static function documentSourceId(string $filePath, string $uploadedAt, int $fallbackId = 0): int
    {
        $key = ltrim($filePath, '/').'|'.$uploadedAt;
        $hash = 2166136261;

        $length = strlen($key);
        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($key[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }

        $id = $hash;

        return $id > 0 ? $id : ($fallbackId > 0 ? $fallbackId : 1);
    }

    /**
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private static function makeEntry(
        string $source,
        int $sourceId,
        ?int $caseId,
        string $caseCode,
        string $category,
        string $documentName,
        ?string $uploadReason,
        array $types,
        string $filePath,
        mixed $uploadedAt,
        string $patientName = '',
    ): array {
        $filePath = ltrim($filePath, '/');
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $types = array_values(array_filter($types, static fn (string $type): bool => $type !== ''));
        $typesLabel = $types !== [] ? implode(' · ', $types) : '—';
        $timestamp = self::resolveTimestamp($uploadedAt);
        $exists = $filePath !== '' && ClinicalDocumentStorage::exists($filePath);
        $previewUrl = null;
        if ($exists && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $previewUrl = Storage::disk('public')->exists($filePath)
                ? Storage::disk('public')->url($filePath)
                : ClinicalDocumentStorage::publicUrl($filePath);
        }

        return [
            'uid' => md5($source.'|'.$sourceId.'|'.$caseId.'|'.$filePath),
            'source' => $source,
            'source_id' => $sourceId,
            'case_id' => $caseId,
            'case_code' => $caseCode,
            'category' => $category,
            'document_name' => PatientDocumentsPresentation::formatClinicalDocumentName($documentName, $patientName),
            'upload_reason' => $uploadReason,
            'types' => $types,
            'types_label' => $typesLabel,
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'file_path' => $filePath,
            'uploaded_at' => $timestamp,
            'uploaded_at_label' => $timestamp?->translatedFormat('d M Y H:i') ?? '—',
            'uploaded_at_relative' => $timestamp?->diffForHumans() ?? '',
            'sort_timestamp' => $timestamp?->timestamp ?? 0,
            'exists' => $exists,
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true),
            'preview_url' => $previewUrl,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private static function finalizeEntries(Collection $entries): array
    {
        return $entries
            ->filter(static fn (array $entry): bool => ($entry['file_path'] ?? '') !== '')
            ->unique('uid')
            ->sortByDesc('sort_timestamp')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeUploadedDocuments(mixed $documents): array
    {
        if (! is_array($documents)) {
            if (is_string($documents) && $documents !== '') {
                $decoded = json_decode($documents, true);

                return is_array($decoded)
                    ? array_values(array_filter($decoded, static fn (mixed $item): bool => is_array($item)))
                    : [];
            }

            return [];
        }

        return array_values(array_filter($documents, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<int, string>  $types
     */
    private static function isExcludedFinancialDocument(string $category, string $documentName, array $types): bool
    {
        $blob = Str::lower(implode(' ', array_filter([
            $category,
            $documentName,
            ...$types,
        ])));

        foreach (['cotiz', 'factura', 'invoice', 'quote', 'presupuesto'] as $keyword) {
            if (str_contains($blob, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private static function resolveTimestamp(mixed $value): ?Carbon
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

    private static function formatStatus(?string $status): string
    {
        return $status
            ? str($status)->replace('_', ' ')->lower()->ucfirst()->toString()
            : __('Sin estado');
    }
}
