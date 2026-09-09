<?php

namespace App\Http\Controllers;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineMedicalReport;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedication;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CaseListController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if (! $user instanceof TelemedicinePatient) {
            return view('cases-list', [
                'isPatient' => false,
                'cases' => collect(),
            ]);
        }

        $cases = TelemedicineCase::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($cases->isEmpty()) {
            return view('cases-list', [
                'isPatient' => true,
                'cases' => collect(),
            ]);
        }

        $caseIds = $cases->pluck('id')->all();

        $consultations = TelemedicineConsultationPatient::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $labs = TelemedicinePatientLab::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $studies = TelemedicinePatientStudy::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $medications = TelemedicinePatientMedication::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $specialties = TelemedicinePatientSpecialty::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $reports = TelemedicineMedicalReport::query()
            ->where('telemedicine_patient_id', $user->getKey())
            ->whereIn('telemedicine_case_id', $caseIds, 'and', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $serviceMap = DB::table('telemedicine_service_lists')
            ->pluck('name', 'id');

        $priorityMap = DB::table('telemedicine_priorities')
            ->pluck('name', 'id');

        $consultationsByCase = $consultations->groupBy('telemedicine_case_id');
        $resources = [
            'labs' => $labs,
            'studies' => $studies,
            'medications' => $medications,
            'specialties' => $specialties,
            'reports' => $reports,
        ];

        $resourcesByCase = collect($resources)->map(fn (Collection $items) => $items->groupBy('telemedicine_case_id'));
        $resourcesByConsultation = collect($resources)->map(fn (Collection $items) => $items->groupBy('telemedicine_consultation_patient_id'));

        $resolveService = function (?int $id) use ($serviceMap): ?string {
            if (! $id) {
                return null;
            }

            return $serviceMap[$id] ?? __('Servicio').' #'.$id;
        };

        $formatStatus = function (?string $status): string {
            return $status ? str($status)->replace('_', ' ')->lower()->ucfirst()->toString() : __('Sin estado');
        };

        $casesPayload = $cases->map(function (TelemedicineCase $case) use (
            $consultationsByCase,
            $resourcesByCase,
            $resourcesByConsultation,
            $resolveService,
            $priorityMap,
            $formatStatus
        ) {
            $caseConsultations = ($consultationsByCase->get($case->id) ?? collect())
                ->sortByDesc(fn (TelemedicineConsultationPatient $consultation) => $consultation->created_at ?? $consultation->updated_at)
                ->values();

            $mainServices = $caseConsultations
                ->map(fn (TelemedicineConsultationPatient $consultation) => $resolveService($consultation->telemedicine_service_list_id))
                ->filter()
                ->unique()
                ->values();

            $derivedServices = $caseConsultations
                ->map(fn (TelemedicineConsultationPatient $consultation) => $resolveService($consultation->telemedicine_service_list_drift_id))
                ->filter()
                ->unique()
                ->values();

            $summary = [
                'consultations' => $caseConsultations->count(),
                'medications' => ($resourcesByCase['medications'][$case->id] ?? collect())->count(),
                'labs' => ($resourcesByCase['labs'][$case->id] ?? collect())->count(),
                'studies' => ($resourcesByCase['studies'][$case->id] ?? collect())->count(),
                'specialties' => ($resourcesByCase['specialties'][$case->id] ?? collect())->count(),
                'reports' => ($resourcesByCase['reports'][$case->id] ?? collect())->count(),
            ];

            $consultationsPayload = $caseConsultations->map(function (TelemedicineConsultationPatient $consultation) use (
                $resourcesByConsultation,
                $resolveService,
                $priorityMap,
                $formatStatus
            ) {
                $medications = ($resourcesByConsultation['medications'][$consultation->id] ?? collect())->values();
                $labs = ($resourcesByConsultation['labs'][$consultation->id] ?? collect())->values();
                $studies = ($resourcesByConsultation['studies'][$consultation->id] ?? collect())->values();
                $specialties = ($resourcesByConsultation['specialties'][$consultation->id] ?? collect())->values();
                $reports = ($resourcesByConsultation['reports'][$consultation->id] ?? collect())->values();

                return [
                    'id' => $consultation->id,
                    'date' => $consultation->created_at ?? $consultation->updated_at,
                    'status' => $formatStatus($consultation->status),
                    'priority' => $priorityMap[$consultation->telemedicine_priority_id] ?? null,
                    'main_service' => $resolveService($consultation->telemedicine_service_list_id),
                    'derived_service' => $resolveService($consultation->telemedicine_service_list_drift_id),
                    'reason_consultation' => $consultation->reason_consultation,
                    'diagnostic_impression' => $consultation->diagnostic_impression,
                    'observations' => $consultation->observations,
                    'vitals' => [
                        'PA' => $consultation->pa,
                        'FC' => $consultation->fc,
                        'FR' => $consultation->fr,
                        'Temp' => $consultation->temp,
                        'Sat' => $consultation->saturacion,
                        'Peso' => $consultation->peso,
                        'Estatura' => $consultation->estatura,
                        'IMC' => $consultation->imc,
                    ],
                    'medications' => $medications,
                    'labs' => $labs,
                    'studies' => $studies,
                    'specialties' => $specialties,
                    'reports' => $reports,
                ];
            });

            $caseReportsWithoutConsultation = ($resourcesByCase['reports'][$case->id] ?? collect())
                ->filter(fn (TelemedicineMedicalReport $report) => empty($report->telemedicine_consultation_patient_id))
                ->values();

            return [
                'id' => $case->id,
                'code' => $case->code ?: 'CASO-'.$case->id,
                'status' => $formatStatus($case->status),
                'reason' => $case->reason,
                'priority' => $priorityMap[$case->telemedicine_priority_id] ?? null,
                'main_services' => $mainServices,
                'derived_services' => $derivedServices,
                'managed_by' => $case->managed_by,
                'assigned_by' => $case->assigned_by,
                'created_at' => $case->created_at,
                'updated_at' => $case->updated_at,
                'consultations' => $consultationsPayload,
                'summary' => $summary,
                'reports_without_consultation' => $caseReportsWithoutConsultation,
            ];
        });

        return view('cases-list', [
            'isPatient' => true,
            'cases' => $casesPayload,
            'totalSummary' => [
                'cases' => $casesPayload->count(),
                'consultations' => $casesPayload->sum('summary.consultations'),
                'medications' => $casesPayload->sum('summary.medications'),
                'labs' => $casesPayload->sum('summary.labs'),
                'studies' => $casesPayload->sum('summary.studies'),
                'reports' => $casesPayload->sum('summary.reports'),
            ],
        ]);
    }
}
