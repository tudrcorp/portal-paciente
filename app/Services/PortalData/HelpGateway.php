<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\TelemedicinePatient;
use App\Support\OperationsTeamPresentation;
use Illuminate\Http\Request;

/**
 * Contactos de operaciones (WhatsApp) desde portal_help_contacts (IntegraCorp).
 *
 * Independiente de DATA_SOURCE: la tabla se administra en IntegraCorp y debe
 * listarse igual para pacientes autenticados y para guests (recuperación de clave).
 */
final class HelpGateway
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     initials: string,
     *     phone_display: string,
     *     whatsapp_url: string,
     * }>
     */
    public function contactsForRequest(Request $request): array
    {
        $user = $request->user();
        $patientName = $user instanceof TelemedicinePatient
            ? (string) $user->full_name
            : null;

        return OperationsTeamPresentation::contactsForPatientPortal($patientName);
    }
}
