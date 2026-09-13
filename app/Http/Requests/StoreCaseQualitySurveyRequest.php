<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCaseQualitySurveyRequest extends FormRequest
{
    public const SATISFACTION = [
        'Totalmente satisfecho',
        'Satisfecho',
        'Neutro',
        'Insatisfecho',
        'Totalmente insatisfecho',
    ];

    public const CONTACT_CHANNELS = [
        'Correo Electrónico',
        'Mensaje de WhatsApp',
        'Llamada Telefónica',
        'Other',
    ];

    public const SERVICES = [
        'TELEMEDICINA',
        'ATENCIÓN MÉDICA DOMICILIARIA',
        'ESTUDIOS DE IMAGEN',
        'LABORATORIOS',
        'CONSULTA ESPECIALIZADA',
        'ENTREGA DE MEDICAMENTOS',
        'FISIOTERAPIAS',
        'ATENCIÓN DE EMERGENCIA',
        'HOME CARE',
        'Other',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date'],
            'patient_identity_card' => ['required', 'string', 'min:6', 'max:30'],
            'patient_full_name' => ['required', 'string', 'min:3', 'max:160'],
            'contact_channel' => ['required', 'string', Rule::in(self::CONTACT_CHANNELS)],
            'contact_channel_other' => ['nullable', 'string', 'max:120', 'required_if:contact_channel,Other'],
            'service_received' => ['required', 'string', Rule::in(self::SERVICES)],
            'service_received_other' => ['nullable', 'string', 'max:120', 'required_if:service_received,Other'],
            'rating_attention_channels' => ['required', 'string', Rule::in(self::SATISFACTION)],
            'rating_response_time' => ['required', 'string', Rule::in(self::SATISFACTION)],
            'rating_medical_service' => ['required', 'string', Rule::in(self::SATISFACTION)],
            'rating_overall_satisfaction' => ['required', 'string', Rule::in(self::SATISFACTION)],
            'suggestions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function answersPayload(): array
    {
        $validated = $this->validated();

        return [
            'service_date' => $validated['service_date'],
            'patient_identity_card' => trim((string) $validated['patient_identity_card']),
            'patient_full_name' => trim((string) $validated['patient_full_name']),
            'contact_channel' => $validated['contact_channel'],
            'contact_channel_other' => $validated['contact_channel'] === 'Other'
                ? trim((string) ($validated['contact_channel_other'] ?? ''))
                : null,
            'service_received' => $validated['service_received'],
            'service_received_other' => $validated['service_received'] === 'Other'
                ? trim((string) ($validated['service_received_other'] ?? ''))
                : null,
            'rating_attention_channels' => $validated['rating_attention_channels'],
            'rating_response_time' => $validated['rating_response_time'],
            'rating_medical_service' => $validated['rating_medical_service'],
            'rating_overall_satisfaction' => $validated['rating_overall_satisfaction'],
            'suggestions' => filled($validated['suggestions'] ?? null)
                ? trim((string) $validated['suggestions'])
                : null,
        ];
    }
}
