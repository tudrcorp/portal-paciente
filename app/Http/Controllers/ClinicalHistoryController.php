<?php

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Services\PortalData\ClinicalHistoryGateway;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalHistoryController extends Controller
{
    public function __invoke(Request $request, ClinicalHistoryGateway $history): View
    {
        return view('clinical-history', $history->forRequest($request));
    }

    public function download(Request $request, ClinicalHistoryGateway $history): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        return $history->downloadPdf($user);
    }
}
