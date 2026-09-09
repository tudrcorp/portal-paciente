<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Http\Controllers\MyProfileController;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Support\PortalDataSource;
use Illuminate\Http\Request;

/**
 * Perfil del paciente desde DB o API, con la misma forma que espera la vista.
 */
final class ProfileGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    /**
     * @return array{
     *     isPatient: bool,
     *     patient: mixed,
     *     affiliation: mixed,
     *     overview?: array<string, mixed>
     * }
     */
    public function forRequest(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof TelemedicinePatient) {
            return [
                'isPatient' => false,
                'patient' => null,
                'affiliation' => null,
            ];
        }

        if (PortalDataSource::usesApi()) {
            $data = $this->api->profile();

            return [
                'isPatient' => (bool) ($data['isPatient'] ?? true),
                'patient' => $data['patient'] ?? null,
                'affiliation' => $data['affiliation'] ?? null,
                'overview' => $data['overview'] ?? [
                    'name' => $user->full_name,
                    'document' => $user->nro_identificacion,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'affiliationBadge' => 'externo',
                    'affiliationTitle' => 'Paciente externo',
                ],
            ];
        }

        // Reutilizamos la lógica existente del controlador (DB directa).
        return app(MyProfileController::class)->resolveViewData($user);
    }
}
