<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientReminderRequest;
use App\Http\Requests\UpdatePatientReminderRequest;
use App\Models\TelemedicinePatient;
use App\Services\PortalData\RemindersGateway;
use App\Support\PatientNotificationsPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientNotificationController extends Controller
{
    public function index(Request $request, RemindersGateway $reminders): View
    {
        $user = $request->user();

        if (! $user instanceof TelemedicinePatient) {
            return view('patient-notifications', [
                'isPatient' => false,
                'data' => null,
                'dayLabels' => PatientNotificationsPresentation::dayLabels(),
                'activeTab' => 'chronic',
            ]);
        }

        $tab = (string) $request->query('tab', 'chronic');
        if (! in_array($tab, ['chronic', 'specific', 'appointments', 'history'], true)) {
            $tab = 'chronic';
        }

        return view('patient-notifications', [
            'isPatient' => true,
            'data' => $reminders->indexData($user),
            'dayLabels' => PatientNotificationsPresentation::dayLabels(),
            'activeTab' => $tab,
        ]);
    }

    public function store(StorePatientReminderRequest $request, RemindersGateway $reminders): RedirectResponse
    {
        /** @var TelemedicinePatient $patient */
        $patient = $request->user();

        $reminders->store($patient, $request->reminderPayload());

        return redirect()
            ->route('notifications.index', ['tab' => $this->tabForType($request->string('type')->toString())])
            ->with('portal.notification_success', __('Recordatorio creado correctamente.'));
    }

    public function update(
        UpdatePatientReminderRequest $request,
        int $reminder,
        RemindersGateway $reminders,
    ): RedirectResponse {
        /** @var TelemedicinePatient $patient */
        $patient = $request->user();

        $tab = $reminders->update($patient, $reminder, $request->reminderPayload());

        return redirect()
            ->route('notifications.index', ['tab' => $tab])
            ->with('portal.notification_success', __('Recordatorio actualizado.'));
    }

    public function destroy(Request $request, int $reminder, RemindersGateway $reminders): RedirectResponse
    {
        /** @var TelemedicinePatient $patient */
        $patient = $request->user();
        abort_unless($patient instanceof TelemedicinePatient, 403);

        $tab = $reminders->destroy($patient, $reminder);

        return redirect()
            ->route('notifications.index', ['tab' => $tab])
            ->with('portal.notification_success', __('Recordatorio eliminado.'));
    }

    public function toggle(Request $request, int $reminder, RemindersGateway $reminders): RedirectResponse
    {
        /** @var TelemedicinePatient $patient */
        $patient = $request->user();
        abort_unless($patient instanceof TelemedicinePatient, 403);

        $result = $reminders->toggle($patient, $reminder);

        return redirect()
            ->route('notifications.index', ['tab' => $result['tab']])
            ->with('portal.notification_success', $result['message']);
    }

    private function tabForType(string $type): string
    {
        return match ($type) {
            'specific_treatment' => 'specific',
            'appointment' => 'appointments',
            default => 'chronic',
        };
    }
}
