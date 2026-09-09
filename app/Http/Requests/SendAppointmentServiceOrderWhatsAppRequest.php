<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CorporateWhatsApp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SendAppointmentServiceOrderWhatsAppRequest extends FormRequest
{
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
            'phone_mode' => ['required', 'in:profile,custom'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = (string) $this->input('phone_mode', 'profile');

            if ($mode === 'custom') {
                if (CorporateWhatsApp::normalizePhoneForWhatsApp((string) $this->input('phone')) === null) {
                    $validator->errors()->add('phone', __('Indica un teléfono válido para WhatsApp.'));
                }

                return;
            }

            $user = $this->user();
            $profilePhone = CorporateWhatsApp::normalizePhoneForWhatsApp(
                is_object($user)
                    ? (string) (($user->phone ?? null) ?: ($user->phone_contact ?? null))
                    : null
            );

            if ($profilePhone === null) {
                $validator->errors()->add(
                    'phone_mode',
                    __('No tienes un teléfono válido en tu perfil. Elige “Otro número”.')
                );
            }
        });
    }

    public function resolvedPhone(): ?string
    {
        if ((string) $this->input('phone_mode') === 'custom') {
            return CorporateWhatsApp::normalizePhoneForWhatsApp((string) $this->input('phone'));
        }

        return null;
    }
}
