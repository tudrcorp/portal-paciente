<?php

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Services\PortalData\ProfileGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MyProfileController extends Controller
{
    public function __invoke(Request $request, ProfileGateway $profiles): View
    {
        return view('my-profile', $profiles->forRequest($request));
    }

    /**
     * Datos de perfil desde MySQL (modo DATA_SOURCE=database).
     * Lo usa ProfileGateway para no duplicar la lógica de afiliaciones.
     *
     * @return array{
     *     isPatient: bool,
     *     patient: array<int, array<string, mixed>>,
     *     affiliation: ?array<string, mixed>,
     *     overview: array<string, mixed>
     * }
     */
    public function resolveViewData(TelemedicinePatient $user): array
    {
        $affiliation = $this->resolveAffiliation($user);

        return [
            'isPatient' => true,
            'patient' => $this->buildPatientData($user),
            'affiliation' => $affiliation,
            'overview' => [
                'name' => $this->displayValue($user->full_name),
                'document' => $this->displayValue($user->nro_identificacion),
                'email' => $this->displayValue($user->email),
                'phone' => $this->displayValue($user->phone),
                'affiliationBadge' => $affiliation['type'] ?? 'externo',
                'affiliationTitle' => $affiliation['title'] ?? 'Paciente externo',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPatientData(TelemedicinePatient $user): array
    {
        return [
            $this->line('Nombre completo', $user->full_name, 'text', 'user-circle'),
            $this->line('Documento de Identificación', $user->nro_identificacion, 'text', 'finger-print'),
            $this->line('Correo electrónico', $user->email, 'email', 'envelope'),
            $this->line('Teléfono principal', $user->phone, 'phone', 'phone'),
            $this->line('Teléfono alterno', $user->phone_contact, 'phone', 'device-phone-mobile'),
            $this->line('Dirección', $user->address, 'text', 'map-pin'),
            $this->line('Fecha de nacimiento', $user->birth_date, 'text', 'cake'),
            $this->line('Sexo', $user->sex, 'text', 'users'),
            $this->line('Edad', $user->age, 'text', 'calendar-days'),
        ];
    }

    private function resolveAffiliation(TelemedicinePatient $user): ?array
    {
        $corporateId = (int) ($user->afilliation_corporate_id ?? 0);
        $individualId = (int) ($user->afilliation_id ?? 0);

        if ($corporateId > 0) {
            $corporate = $this->firstFromTables(
                ['affiliation_corporates', 'respaldo_administracion.affiliation_corporates'],
                fn ($query) => $query->where('id', $corporateId)
            );

            if ($corporate) {
                $member = $this->firstFromTables(
                    ['affiliate_corporates', 'respaldo_administracion.affiliate_corporates'],
                    function ($query) use ($corporateId, $user) {
                        $query->where('affiliation_corporate_id', $corporateId);

                        if (! empty($user->nro_identificacion)) {
                            $query->where('nro_identificacion', $user->nro_identificacion);
                        } elseif (! empty($user->email)) {
                            $query->where('email', $user->email);
                        }
                    }
                );

                return [
                    'type' => 'corporativa',
                    'title' => 'Afiliación corporativa',
                    'data' => [
                        $this->line('Código de afiliación', $corporate->code, 'text', 'identification'),
                        $this->line('Estatus', $corporate->status, 'text', 'shield-check'),
                        $this->line('Empresa', $corporate->name_corporate, 'text', 'building-office'),
                        $this->line('RIF', $corporate->rif, 'text', 'credit-card'),
                        $this->line('Tipo de afiliación', $corporate->affiliation_type, 'text', 'briefcase'),
                        $this->line('Fecha de afiliación', $corporate->date_affiliation, 'text', 'calendar-days'),
                        $this->line('Contacto de empresa', $corporate->full_name_contact, 'text', 'user-circle'),
                        $this->line('Teléfono de contacto', $corporate->phone_contact, 'phone', 'phone'),
                        $this->line('Correo de contacto', $corporate->email_contact, 'email', 'envelope'),
                    ],
                    'member' => $member ? [
                        $this->line('Afiliado corporativo', trim(($member->first_name ?? '').' '.($member->last_name ?? '')), 'text', 'user-circle'),
                        $this->line('Documento afiliado', $member->nro_identificacion, 'text', 'finger-print'),
                        $this->line('Cargo', $member->position_company, 'text', 'briefcase'),
                        $this->line('Plan', $member->plan_id, 'text', 'identification'),
                        $this->line('Cobertura', $member->coverage_id, 'text', 'shield-check'),
                    ] : null,
                ];
            }
        }

        if ($individualId > 0) {
            $individual = $this->firstFromTables(
                ['affiliations', 'respaldo_administracion.affiliations'],
                fn ($query) => $query->where('id', $individualId)
            );

            if ($individual) {
                return [
                    'type' => 'individual',
                    'title' => 'Afiliación individual',
                    'data' => [
                        $this->line('Código de afiliación', $individual->code, 'text', 'identification'),
                        $this->line('Estatus', $individual->status, 'text', 'shield-check'),
                        $this->line('Tipo de afiliación', $individual->affiliation_type, 'text', 'briefcase'),
                        $this->line('Plan', $individual->plan_id, 'text', 'identification'),
                        $this->line('Cobertura', $individual->coverage_id, 'text', 'shield-check'),
                        $this->line('Frecuencia de pago', $individual->payment_frequency, 'text', 'credit-card'),
                        $this->line('Fecha de activación', $individual->activated_at, 'text', 'calendar-days'),
                        $this->line('Fecha efectiva', $individual->effective_date, 'text', 'calendar-days'),
                        $this->line('Titular pagador', $individual->full_name_payer, 'text', 'user-circle'),
                        $this->line('Correo pagador', $individual->email_payer, 'email', 'envelope'),
                    ],
                    'member' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{label: string, value: string, type: string, icon: string, isMissing: bool}
     */
    private function line(string $label, mixed $value, string $type = 'text', string $icon = 'minus'): array
    {
        $displayValue = $this->displayValue($value);

        return [
            'label' => $label,
            'value' => $displayValue,
            'type' => $type,
            'icon' => $icon,
            'isMissing' => $displayValue === 'No disponible',
        ];
    }

    private function displayValue(mixed $value): string
    {
        $string = trim((string) $value);

        return $string === '' ? 'No disponible' : $string;
    }

    private function firstFromTables(array $tables, callable $scope): ?object
    {
        foreach ($tables as $table) {
            try {
                $query = DB::table($table);
                $scope($query);

                $record = $query->first();
                if ($record) {
                    return $record;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
