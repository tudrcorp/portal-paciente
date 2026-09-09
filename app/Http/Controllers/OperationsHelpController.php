<?php

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Services\PortalData\HelpGateway;
use App\Support\PasswordRecoverySupport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsHelpController extends Controller
{
    public function __invoke(Request $request, HelpGateway $help): View
    {
        $user = $request->user();
        $isGuest = $user === null;
        $patientName = $user instanceof TelemedicinePatient
            ? trim((string) $user->full_name)
            : null;

        $contacts = $help->contactsForRequest($request);

        // En guest, preferimos mensaje de olvido de clave en los WhatsApp.
        if ($isGuest && $contacts === []) {
            $fallbackUrl = PasswordRecoverySupport::whatsappUrl();
            if ($fallbackUrl) {
                $contacts = [[
                    'id' => 0,
                    'name' => PasswordRecoverySupport::contactName(),
                    'initials' => 'MC',
                    'phone_display' => PasswordRecoverySupport::phoneDisplay(),
                    'whatsapp_url' => $fallbackUrl,
                ]];
            }
        } elseif ($isGuest) {
            $message = PasswordRecoverySupport::defaultMessage();
            $contacts = array_map(static function (array $contact) use ($message): array {
                // Reusa el teléfono ya validado en la URL original.
                $url = $contact['whatsapp_url'] ?? '';
                if (is_string($url) && str_contains($url, 'wa.me/')) {
                    $base = strtok($url, '?') ?: $url;
                    $contact['whatsapp_url'] = $base.'?text='.rawurlencode($message);
                }

                return $contact;
            }, $contacts);
        }

        $payload = [
            'isPatient' => $user instanceof TelemedicinePatient,
            'isGuest' => $isGuest,
            'contacts' => $contacts,
            'patientName' => $patientName,
        ];

        if ($isGuest) {
            return view('operations-help-guest', $payload);
        }

        return view('operations-help', $payload);
    }
}
