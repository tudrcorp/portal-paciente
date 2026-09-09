<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class TelemedicinePatient extends Authenticatable
{
    use Notifiable;

    protected $table = 'telemedicine_patients';

    /**
     * @var list<string>
     */
    protected $hidden = [
        'nro_identificacion',
        'patient_portal_password',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'nro_identificacion',
        'patient_portal_password',
        'patient_portal_authorized',
        'email',
        'phone',
        'phone_contact',
        'address',
        'birth_date',
        'sex',
        'age',
        'afilliation_corporate_id',
        'afilliation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_portal_authorized' => 'boolean',
        ];
    }

    /**
     * La tabla de producción no tiene remember_token.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    /**
     * Contraseña del portal en texto plano (Integracorp: patient_portal_password).
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['patient_portal_password'] ?? '');
    }

    public function getAuthPasswordName(): string
    {
        return 'patient_portal_password';
    }

    public function hasPortalPassword(): bool
    {
        return trim($this->getAuthPassword()) !== '';
    }

    /**
     * Acceso habilitado por Operaciones (Integracorp: patient_portal_authorized = 1).
     */
    public function isPortalAuthorized(): bool
    {
        return (bool) ($this->attributes['patient_portal_authorized'] ?? false);
    }

    /**
     * Compatibilidad con vistas que usan auth()->user()->name
     */
    public function getNameAttribute(): string
    {
        return trim((string) ($this->attributes['full_name'] ?? ''));
    }

    public function initials(): string
    {
        return Str::of($this->full_name ?? '')
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Sexo femenino (para mostrar sección ginecológica).
     */
    public function isFemale(): bool
    {
        $sex = Str::lower(trim((string) ($this->attributes['sex'] ?? '')));

        if ($sex === '') {
            return false;
        }

        return str_starts_with($sex, 'f')
            || str_contains($sex, 'femen')
            || str_contains($sex, 'mujer')
            || $sex === 'female';
    }

    /**
     * La tabla no tiene columnas 2FA; el portal no fuerza segundo factor para pacientes.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return false;
    }

    /**
     * Sin columna `email_verified_at`: tratamos el correo de la clínica como ya válido para el portal.
     */
    public function hasVerifiedEmail(): bool
    {
        return true;
    }
}
