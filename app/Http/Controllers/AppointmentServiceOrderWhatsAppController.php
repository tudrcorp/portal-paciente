<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendAppointmentServiceOrderWhatsAppRequest;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiException;
use App\Services\PortalData\AppointmentsGateway;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AppointmentServiceOrderWhatsAppController extends Controller
{
    public function __invoke(
        SendAppointmentServiceOrderWhatsAppRequest $request,
        int $appointment,
        AppointmentsGateway $appointments,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof TelemedicinePatient, 403);

        try {
            $result = $appointments->sendServiceOrderWhatsApp(
                $user,
                $appointment,
                $request->resolvedPhone()
            );
        } catch (PortalApiException $exception) {
            return back()->withErrors([
                'phone' => $exception->getMessage() ?: __('No se pudo enviar la orden por WhatsApp.'),
            ]);
        } catch (HttpException $exception) {
            return back()->withErrors([
                'phone' => $exception->getMessage() ?: __('No se pudo enviar la orden por WhatsApp.'),
            ]);
        }

        return back()->with(
            'portal.appointment_whatsapp_success',
            __('Orden de servicio enviada por WhatsApp a :phone.', [
                'phone' => $result['phone_display'] ?? $result['phone'] ?? '',
            ])
        );
    }
}
