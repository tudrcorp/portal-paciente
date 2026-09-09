<?php

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiSession;
use App\Services\PortalData\AppointmentsGateway;
use App\Support\PortalDataSource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AppointmentsGateway $appointments): View
    {
        $user = $request->user();
        $patientName = $this->resolvePatientDisplayName($user);

        $agenda = [];

        if ($user instanceof TelemedicinePatient) {
            $agenda = $appointments->forDashboard($user);
        }

        return view('dashboard', [
            'patientName' => $patientName,
            'appointments' => $agenda,
            'appointmentDates' => collect($agenda)
                ->pluck('date')
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    private function resolvePatientDisplayName(mixed $user): string
    {
        $candidates = [];

        if ($user instanceof TelemedicinePatient) {
            $attrs = $user->getAttributes();
            $candidates[] = $attrs['full_name'] ?? null;
            $candidates[] = $user->full_name ?? null;
            $candidates[] = $user->name ?? null;
        } elseif (is_object($user)) {
            $candidates[] = $user->name ?? null;
            $candidates[] = $user->full_name ?? null;
        }

        if (PortalDataSource::usesApi()) {
            $sessionPatient = PortalApiSession::patient();
            if (is_array($sessionPatient)) {
                $candidates[] = $sessionPatient['full_name'] ?? null;
                $candidates[] = $sessionPatient['name'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '') {
                return Str::title(Str::lower($name));
            }
        }

        return '';
    }
}
