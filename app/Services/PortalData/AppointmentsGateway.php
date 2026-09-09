<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\OperationMedicalAppointment;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Support\CorporateWhatsApp;
use App\Support\PortalDataSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Citas médicas operativas (operation_medical_appointments).
 * Bifurca DB / API y normaliza al shape del dashboard.
 */
final class AppointmentsGateway
{
    /**
     * @var list<string>
     */
    private const TONES = ['sky', 'teal', 'amber', 'navy'];

    /**
     * @var array<string, string>
     */
    private const SERVICE_TYPE_LABELS = [
        'LABORATORIOS' => 'Laboratorios',
        'IMAGENOLOGIA' => 'Imagenología',
        'ESPECIALISTA' => 'Especialista',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'SCHEDULED' => 'Agendada',
        'RESCHEDULED' => 'Reprogramada',
    ];

    public function __construct(private readonly PortalApiClient $api) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forDashboard(TelemedicinePatient $patient): array
    {
        if (PortalDataSource::usesApi()) {
            return $this->fromApi($patient);
        }

        return $this->fromDatabase($patient);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromApi(TelemedicinePatient $patient): array
    {
        try {
            $data = $this->api->appointments();
        } catch (\Throwable) {
            return [];
        }

        $rows = collect($data['appointments'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->filter(fn (array $row): bool => filled($row['appointment_at'] ?? null))
            ->values();

        return $rows->map(function (array $row, int $index) use ($patient, $data): array {
            $at = Carbon::parse((string) $row['appointment_at'])->timezone(config('app.timezone'));
            $status = isset($row['status']) ? (string) $row['status'] : null;
            $supplier = (string) ($row['supplier_name'] ?? $row['location'] ?? '');
            $title = (string) ($row['title'] ?? __('Cita médica'));

            $phoneRaw = (string) ($row['supplier_phone'] ?? '');
            $phoneDisplay = (string) ($row['supplier_phone_display'] ?? '');
            $whatsappUrl = (string) ($row['supplier_whatsapp_url'] ?? '');

            if ($whatsappUrl === '' && $phoneRaw !== '') {
                $whatsappUrl = (string) (CorporateWhatsApp::buildWaMeUrl(
                    $phoneRaw,
                    $this->whatsappMessage($patient, $title, $at, $supplier)
                ) ?? '');
            }

            if ($phoneDisplay === '' && $phoneRaw !== '') {
                $phoneDisplay = CorporateWhatsApp::formatDisplayPhone($phoneRaw);
            }

            $patientPhoneDisplay = (string) ($data['patient_phone_display'] ?? '');
            if ($patientPhoneDisplay === '') {
                $patientPhoneDisplay = CorporateWhatsApp::formatDisplayPhone(
                    (string) (($patient->phone ?? null) ?: ($patient->phone_contact ?? null))
                );
            }

            return $this->mapDashboardItem(
                id: 'ops-'.($row['id'] ?? $index),
                appointmentId: (int) ($row['id'] ?? 0),
                title: $title,
                supplier: $supplier,
                caseCode: (string) ($row['case_code'] ?? ''),
                serviceTypeLabel: (string) ($row['service_type_label'] ?? ''),
                at: $at,
                tone: self::TONES[$index % count(self::TONES)],
                status: $status,
                statusLabel: (string) ($row['status_label'] ?? $this->statusLabel($status) ?? ''),
                phoneDisplay: $phoneDisplay,
                whatsappUrl: $whatsappUrl,
                canSendServiceOrder: (bool) ($row['can_send_service_order'] ?? $row['has_service_order_pdf'] ?? false),
                patientPhoneDisplay: $patientPhoneDisplay,
            );
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromDatabase(TelemedicinePatient $patient): array
    {
        try {
            if (! Schema::hasTable('operation_medical_appointments')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        try {
            $rows = DB::table('operation_medical_appointments as oma')
                ->leftJoin('operation_service_orders as oso', 'oso.id', '=', 'oma.operation_service_order_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'oma.supplier_id')
                ->leftJoin('telemedicine_cases as tc', 'tc.id', '=', 'oma.telemedicine_case_id')
                ->where('oma.telemedicine_patient_id', $patient->getKey())
                ->whereIn('oma.status', OperationMedicalAppointment::ACTIVE_STATUSES)
                ->whereNotNull('oma.appointment_at')
                ->orderBy('oma.appointment_at')
                ->get([
                    'oma.id',
                    'oma.appointment_at',
                    'oma.status',
                    'oma.supplier_external',
                    'oma.supplier_notify_phone',
                    'oma.operation_service_order_id',
                    'oso.id as order_id',
                    'oso.service_type',
                    'oso.description as order_description',
                    'oso.supplier_external as order_supplier_external',
                    'oso.service_order_pdf_path',
                    's.name as supplier_name',
                    's.razon_social as supplier_razon_social',
                    's.personal_phone as supplier_personal_phone',
                    's.local_phone as supplier_local_phone',
                    'tc.code as case_code',
                ]);
        } catch (\Throwable) {
            return [];
        }

        return collect($rows)->values()->map(function (object $row, int $index) use ($patient): array {
            $at = Carbon::parse((string) $row->appointment_at)->timezone(config('app.timezone'));
            $serviceTypeLabel = $this->serviceTypeLabel($row->service_type !== null ? (string) $row->service_type : null);
            $description = trim((string) ($row->order_description ?? ''));
            $title = $serviceTypeLabel
                ?? ($description !== '' ? $description : (string) __('Cita médica'));

            $supplier = collect([
                $row->supplier_name,
                $row->supplier_razon_social,
                $row->supplier_external,
                $row->order_supplier_external,
            ])
                ->map(fn (mixed $value): string => trim((string) $value))
                ->first(fn (string $value): bool => $value !== '') ?? '';

            $phoneRaw = collect([
                $row->supplier_notify_phone,
                $row->supplier_personal_phone,
                $row->supplier_local_phone,
            ])
                ->map(fn (mixed $value): string => trim((string) $value))
                ->first(fn (string $value): bool => CorporateWhatsApp::normalizePhoneForWhatsApp($value) !== null) ?? '';

            $phoneDisplay = $phoneRaw !== '' ? CorporateWhatsApp::formatDisplayPhone($phoneRaw) : '';
            $whatsappUrl = $phoneRaw !== ''
                ? (string) (CorporateWhatsApp::buildWaMeUrl(
                    $phoneRaw,
                    $this->whatsappMessage($patient, $title, $at, $supplier)
                ) ?? '')
                : '';

            $status = (string) $row->status;
            $canSend = filled($row->order_id ?? null) || filled($row->operation_service_order_id ?? null);
            $patientPhoneDisplay = CorporateWhatsApp::formatDisplayPhone(
                (string) (($patient->phone ?? null) ?: ($patient->phone_contact ?? null))
            );

            return $this->mapDashboardItem(
                id: 'ops-'.$row->id,
                appointmentId: (int) $row->id,
                title: $title,
                supplier: $supplier,
                caseCode: (string) ($row->case_code ?? ''),
                serviceTypeLabel: (string) ($serviceTypeLabel ?? ''),
                at: $at,
                tone: self::TONES[$index % count(self::TONES)],
                status: $status,
                statusLabel: (string) ($this->statusLabel($status) ?? ''),
                phoneDisplay: $phoneDisplay,
                whatsappUrl: $whatsappUrl,
                canSendServiceOrder: $canSend,
                patientPhoneDisplay: $patientPhoneDisplay,
            );
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDashboardItem(
        int|string $id,
        int $appointmentId,
        string $title,
        string $supplier,
        string $caseCode,
        string $serviceTypeLabel,
        Carbon $at,
        string $tone,
        ?string $status,
        string $statusLabel,
        string $phoneDisplay,
        string $whatsappUrl,
        bool $canSendServiceOrder,
        string $patientPhoneDisplay,
    ): array {
        $normalizedTitle = trim($title);
        $normalizedSupplier = trim($supplier);
        $normalizedCase = $caseCode !== '' ? mb_strtoupper(trim($caseCode)) : '';

        $subtitleParts = collect([$normalizedSupplier, $normalizedCase !== '' ? $normalizedCase : null])
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter();

        if ($serviceTypeLabel !== '' && strcasecmp($serviceTypeLabel, $normalizedTitle) !== 0) {
            $subtitleParts->push($serviceTypeLabel);
        }

        $subtitle = $subtitleParts->unique()->implode(' · ');

        if ($subtitle === '') {
            $subtitle = (string) __('Cita médica programada por Operaciones');
        }

        return [
            'id' => $id,
            'appointment_id' => $appointmentId,
            'title' => $normalizedTitle !== '' ? $normalizedTitle : (string) __('Cita médica'),
            'subtitle' => $subtitle,
            'supplier' => $normalizedSupplier,
            'case_code' => $normalizedCase,
            'date' => $at->toDateString(),
            'time' => $at->format('g:i A'),
            'hour' => $at->format('g:i'),
            'meridiem' => $at->format('A'),
            'start' => $at->format('H:i'),
            'end' => $at->format('H:i'),
            'tone' => $tone,
            'source' => 'operations',
            'status' => $status,
            'status_label' => $statusLabel,
            'supplier_phone_display' => $phoneDisplay,
            'supplier_whatsapp_url' => $whatsappUrl,
            'can_send_service_order' => $canSendServiceOrder && $appointmentId > 0,
            'service_order_whatsapp_url' => ($canSendServiceOrder && $appointmentId > 0)
                ? route('appointments.service-order.whatsapp', ['appointment' => $appointmentId])
                : null,
            'patient_phone_display' => $patientPhoneDisplay,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendServiceOrderWhatsApp(
        TelemedicinePatient $patient,
        int $appointmentId,
        ?string $phone = null,
    ): array {
        if (PortalDataSource::usesApi()) {
            try {
                return $this->api->sendAppointmentServiceOrderWhatsApp($appointmentId, $phone);
            } catch (\App\Services\PortalApi\PortalApiException $exception) {
                abort($exception->status >= 400 ? $exception->status : 502, $exception->getMessage());
            }
        }

        abort(503, __('El envío por WhatsApp de la orden está disponible cuando el portal opera con el API.'));
    }

    private function whatsappMessage(
        TelemedicinePatient $patient,
        string $title,
        Carbon $at,
        string $supplier,
    ): string {
        $name = trim((string) ($patient->full_name ?? $patient->name ?? ''));
        $when = $at->format('d/m/Y H:i');

        $parts = [
            $name !== ''
                ? "Hola, soy {$name}, paciente del portal."
                : 'Hola, soy paciente del portal.',
            "Consulto sobre mi cita de {$title} agendada para el {$when}.",
        ];

        if (trim($supplier) !== '') {
            $parts[] = 'Proveedor: '.trim($supplier).'.';
        }

        return implode(' ', $parts);
    }

    private function serviceTypeLabel(?string $serviceType): ?string
    {
        $key = mb_strtoupper(trim((string) $serviceType));
        if ($key === '') {
            return null;
        }

        return self::SERVICE_TYPE_LABELS[$key] ?? $serviceType;
    }

    private function statusLabel(?string $status): ?string
    {
        $key = mb_strtoupper(trim((string) $status));
        if ($key === '') {
            return null;
        }

        return self::STATUS_LABELS[$key] ?? null;
    }
}
