<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientDocumentRequest;
use App\Services\PortalData\DocumentsGateway;
use Illuminate\Http\RedirectResponse;

class StorePatientDocumentController extends Controller
{
    public function __invoke(StorePatientDocumentRequest $request, DocumentsGateway $documents): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('document_file');

        $documents->storeUpload(
            $user,
            $file,
            trim((string) $request->input('document_name')),
            trim((string) $request->input('upload_reason')),
            filled($request->input('telemedicine_case_id'))
                ? (int) $request->input('telemedicine_case_id')
                : null,
        );

        $successMessage = filled($request->input('telemedicine_case_id'))
            ? __('Documento cargado correctamente. Ya puedes verlo en el caso seleccionado.')
            : __('Documento cargado correctamente. Lo encontrarás en la sección de documentos generales.');

        return redirect()
            ->route('cases.index')
            ->with('portal.upload_success', $successMessage);
    }
}
