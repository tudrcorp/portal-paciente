<?php

namespace App\Http\Controllers;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDocument;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientUploadedDocument;
use App\Services\PortalData\DocumentsGateway;
use App\Support\ClinicalDocumentStorage;
use App\Support\PatientClinicalDocumentsCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientDocumentsController extends Controller
{
    public function __invoke(Request $request, DocumentsGateway $documents): View
    {
        return $this->index($request, $documents);
    }

    public function index(Request $request, DocumentsGateway $documents): View
    {
        $user = $request->user();

        if (! $user instanceof TelemedicinePatient) {
            return view('patient-documents', [
                'isPatient' => false,
                'cases' => collect(),
                'generalDocuments' => [],
                'patientCases' => collect(),
                'filterOptions' => [
                    'months' => [],
                    'doctors' => [],
                    'services' => [],
                    'has_general_documents' => false,
                ],
                'summary' => [
                    'cases' => 0,
                    'documents' => 0,
                    'patient_uploads' => 0,
                ],
            ]);
        }

        $payload = $documents->indexPayload($user);

        return view('patient-documents', [
            'isPatient' => $payload['isPatient'],
            'cases' => $payload['cases'],
            'generalDocuments' => $payload['generalDocuments'],
            'patientCases' => $payload['patientCases'],
            'filterOptions' => $payload['filterOptions'],
            'summary' => $payload['summary'],
        ]);
    }

    public function download(Request $request, string $source, int $id, DocumentsGateway $documents): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        return $documents->download($user, $source, $id);
    }

    /**
     * Descarga desde storage local (modo DATA_SOURCE=database).
     */
    public function downloadFromDatabase(
        TelemedicinePatient $patient,
        string $source,
        int $id,
    ): StreamedResponse|BinaryFileResponse {
        [$relativePath, $downloadName] = match ($source) {
            'patient_upload' => $this->resolvePatientUpload($patient, $id),
            'telemedicine_document' => $this->resolveTelemedicineDocument($patient, $id),
            default => $this->resolveCatalogDocument($patient, $source, $id),
        };

        return ClinicalDocumentStorage::download($relativePath, $downloadName);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePatientUpload(TelemedicinePatient $patient, int $id): array
    {
        $document = TelemedicinePatientUploadedDocument::query()
            ->where('id', $id)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->firstOrFail();

        $extension = pathinfo((string) $document->stored_filename, PATHINFO_EXTENSION);
        $downloadName = str($document->document_name)->slug('_').($extension ? '.'.$extension : '');

        return [$document->storageRelativePath(), $downloadName];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTelemedicineDocument(TelemedicinePatient $patient, int $id): array
    {
        $document = TelemedicineDocument::query()
            ->where('id', $id)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->firstOrFail();

        $relativePath = 'telemedicina-doc/'.ltrim((string) $document->name, '/');

        return [$relativePath, basename((string) $document->name)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCatalogDocument(TelemedicinePatient $patient, string $source, int $id): array
    {
        $cases = TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->get();

        foreach ($cases as $case) {
            foreach (PatientClinicalDocumentsCatalog::documentsForCase($case, $patient) as $document) {
                if (($document['source'] ?? '') === $source && (int) ($document['source_id'] ?? 0) === $id) {
                    return [
                        (string) $document['file_path'],
                        (string) $document['document_name'],
                    ];
                }
            }
        }

        foreach (PatientClinicalDocumentsCatalog::generalPatientDocuments($patient) as $document) {
            if (($document['source'] ?? '') === $source && (int) ($document['source_id'] ?? 0) === $id) {
                return [
                    (string) $document['file_path'],
                    (string) $document['document_name'],
                ];
            }
        }

        abort(404);
    }
}
