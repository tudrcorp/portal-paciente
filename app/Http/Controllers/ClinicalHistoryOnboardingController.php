<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientClinicalHistoryRequest;
use App\Models\TelemedicinePatient;
use App\Services\PortalData\ClinicalHistoryGateway;
use App\Support\ClinicalHistoryPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalHistoryOnboardingController extends Controller
{
    public function create(Request $request, ClinicalHistoryGateway $history): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        if ($history->hasHistory($user)) {
            return redirect()->route('dashboard');
        }

        return view('clinical-history-onboarding', [
            'patient' => $user,
            'isFemale' => $user->isFemale(),
            'pathologicalFlags' => ClinicalHistoryPresentation::pathologicalFlags(),
            'familyFlags' => ClinicalHistoryPresentation::appDeclaredFlags(),
            'habitFlags' => ClinicalHistoryPresentation::habitFlags(),
        ]);
    }

    public function store(
        StorePatientClinicalHistoryRequest $request,
        ClinicalHistoryGateway $history,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        if ($history->hasHistory($user)) {
            return redirect()->route('dashboard');
        }

        $history->createFromPatientInput($user, $request->validated());

        return redirect()
            ->route('dashboard')
            ->with('portal.history_success', __('Tu historia clínica quedó registrada. Ya puedes usar el portal.'));
    }
}
