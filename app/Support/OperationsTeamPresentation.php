<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class OperationsTeamPresentation
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
    public static function contactsForPatientPortal(?string $patientName = null, ?string $message = null): array
    {
        $defaultMessage = $message !== null && trim($message) !== ''
            ? trim($message)
            : self::defaultWhatsAppMessage($patientName);

        try {
            $rows = DB::table('portal_help_contacts')
                ->where('status', 'ACTIVO')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'phone',
                ]);
        } catch (\Throwable) {
            return [];
        }

        $contacts = [];

        foreach ($rows as $row) {
            $whatsappUrl = CorporateWhatsApp::buildWaMeUrl(
                (string) $row->phone,
                $defaultMessage
            );

            if ($whatsappUrl === null) {
                continue;
            }

            $name = self::formatContactName((string) $row->name);

            $contacts[] = [
                'id' => (int) $row->id,
                'name' => $name,
                'initials' => self::initialsFromName($name),
                'phone_display' => CorporateWhatsApp::formatDisplayPhone((string) $row->phone),
                'whatsapp_url' => $whatsappUrl,
            ];
        }

        return self::uniqueContactsByPhone($contacts);
    }

    private static function defaultWhatsAppMessage(?string $patientName): string
    {
        $patientName = trim((string) $patientName);

        if ($patientName === '') {
            return 'Hola, soy paciente del portal. Necesito ayuda con mi atención. ¿Me pueden orientar, por favor?';
        }

        return "Hola, soy {$patientName}, paciente del portal. Necesito ayuda con mi atención. ¿Me pueden orientar, por favor?";
    }

    private static function formatContactName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }

    private static function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * @param  list<array{
     *     id: int,
     *     name: string,
     *     initials: string,
     *     phone_display: string,
     *     whatsapp_url: string,
     * }>  $contacts
     * @return list<array{
     *     id: int,
     *     name: string,
     *     initials: string,
     *     phone_display: string,
     *     whatsapp_url: string,
     * }>
     */
    private static function uniqueContactsByPhone(array $contacts): array
    {
        $seenPhones = [];

        return array_values(array_filter($contacts, static function (array $contact) use (&$seenPhones): bool {
            $phoneKey = $contact['phone_display'];

            if ($phoneKey === '' || isset($seenPhones[$phoneKey])) {
                return false;
            }

            $seenPhones[$phoneKey] = true;

            return true;
        }));
    }
}
