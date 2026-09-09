<?php

use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiSession;
use App\Services\PortalData\ClinicalHistoryGateway;
use App\Services\PortalData\PortalAuthGateway;
use App\Support\PasswordRecoverySupport;
use App\Support\PortalDataSource;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|min:6|max:30')]
    public string $identityCard = '';

    #[Validate('required|string|min:4|max:100')]
    public string $password = '';

    /**
     * Handle an incoming authentication request.
     * Respeta DATA_SOURCE (database|api) vía PortalAuthGateway.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        try {
            $user = $this->validateCredentials();
        } catch (ValidationException $exception) {
            if (! array_key_exists('maintenance', $exception->errors())) {
                RateLimiter::hit($this->throttleKey());
            }

            throw $exception;
        }

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            Session::put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            $this->redirect(route('two-factor.login'), navigate: true);

            return;
        }

        // Conservamos JWT/snapshot antes de regenerar la sesión (modo API).
        $apiToken = PortalApiSession::token();
        $apiPatient = PortalApiSession::patient();

        Auth::login($user, false);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        if (PortalDataSource::usesApi() && is_string($apiToken) && is_array($apiPatient)) {
            PortalApiSession::put($apiToken, $apiPatient);
        }

        $defaultRoute = route('dashboard', absolute: false);

        if ($user instanceof TelemedicinePatient) {
            $hasHistory = app(ClinicalHistoryGateway::class)->hasHistory($user);
            $defaultRoute = $hasHistory
                ? route('dashboard', absolute: false)
                : route('history.onboarding', absolute: false);
        }

        $this->redirectIntended(default: $defaultRoute, navigate: true);
    }

    /**
     * Validate the user's credentials (DB o API según config).
     */
    protected function validateCredentials(): Authenticatable
    {
        return app(PortalAuthGateway::class)->attemptLogin($this->identityCard, $this->password);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identityCard' => __('Demasiados intentos. Intenta nuevamente en :seconds segundos.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identityCard).'|'.request()->ip());
    }

    public function with(): array
    {
        return [
            'passwordRecoveryUrl' => PasswordRecoverySupport::whatsappUrl(
                $this->identityCard !== '' ? $this->identityCard : null
            ),
            'passwordRecoveryContact' => PasswordRecoverySupport::contactName(),
            'passwordRecoveryPhone' => PasswordRecoverySupport::phoneDisplay(),
            'medichatContactUrl' => PasswordRecoverySupport::contactWhatsappUrl(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6" x-data="{ showRecoveryHelp: false, showContactUs: false }">
    <x-auth-header
        :title="__('Portal del paciente')"
        :description="__('Ingresa tu cédula y tu clave del portal para continuar.')"
    />

    <x-auth-session-status class="text-center" :status="session('status')" />

    @error('maintenance')
        <x-portal.alert variant="warning" :title="__('Portal en mantenimiento')">
            {{ $message }}
        </x-portal.alert>
    @enderror

    <form method="POST" wire:submit="login" class="flex flex-col gap-5">
        <flux:input
            wire:model="identityCard"
            :label="__('Documento de Identificación')"
            type="text"
            required
            autofocus
            autocomplete="username"
            inputmode="numeric"
            :placeholder="__('Ej.: 00112345678')"
        />

        <flux:field>
            <div class="flex items-center justify-between gap-3">
                <flux:label>{{ __('Clave del portal') }}</flux:label>
                <button
                    type="button"
                    class="shrink-0 text-sm font-semibold text-[#031e36] underline underline-offset-2 hover:text-[#04335a] dark:text-white dark:hover:text-zinc-100"
                    @click="showRecoveryHelp = ! showRecoveryHelp"
                    :aria-expanded="showRecoveryHelp.toString()"
                    aria-controls="login-recovery-help"
                    data-test="forgot-password-toggle"
                >
                    {{ __('¿Olvidaste tu clave?') }}
                </button>
            </div>
            <flux:input
                wire:model="password"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Tu clave de acceso')"
                viewable
                data-test="login-password"
            />
            <flux:error name="password" />
        </flux:field>

        <div class="flex items-center justify-end pt-1">
            <flux:button
                variant="primary"
                type="submit"
                class="portal-glass-button w-full py-2.5 text-base"
                data-test="login-button"
            >
                {{ __('Entrar al portal') }}
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col items-center gap-3 text-center">
        <button
            type="button"
            class="text-sm font-semibold text-[#031e36] underline underline-offset-2 hover:text-[#04335a] dark:text-white dark:hover:text-zinc-100"
            @click="showContactUs = ! showContactUs"
            :aria-expanded="showContactUs.toString()"
            aria-controls="login-contact-us"
            data-test="contact-us-toggle"
        >
            {{ __('¡Comunícate con nosotros!') }}
        </button>

        <div
            id="login-contact-us"
            x-cloak
            x-show="showContactUs"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="w-full rounded-2xl border border-portal-border-soft/80 bg-portal-subtle/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
        >
            <p class="text-sm font-semibold text-portal-ink dark:text-zinc-100">
                {{ $passwordRecoveryContact }}
            </p>
            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                {{ $passwordRecoveryPhone ?: '+58 424-2132112' }}
            </p>

            @if ($medichatContactUrl)
                <a
                    href="{{ $medichatContactUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                    data-test="medichat-whatsapp"
                >
                    <img src="{{ asset('icons/whatsapp.svg') }}" alt="" class="size-5" width="20" height="20" />
                    {{ __('Chatear con :name', ['name' => $passwordRecoveryContact]) }}
                </a>
            @endif
        </div>
    </div>

    <div
        id="login-recovery-help"
        x-cloak
        x-show="showRecoveryHelp"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="rounded-2xl border border-portal-border-soft/80 bg-portal-subtle/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
    >
        <p class="text-sm font-semibold text-portal-ink dark:text-zinc-100">
            {{ __('¿No puedes entrar?') }}
        </p>
        <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
            <li>{{ __('Toca “Olvidé mi clave”.') }}</li>
            <li>{{ __('Escríbele a :name por WhatsApp (:phone).', ['name' => $passwordRecoveryContact, 'phone' => $passwordRecoveryPhone ?: '+58 424-2132112']) }}</li>
            <li>{{ __('Ellos actualizarán tu clave en el sistema.') }}</li>
        </ol>

        <div class="mt-4 flex flex-col gap-2">
            @if ($passwordRecoveryUrl)
                <a
                    href="{{ $passwordRecoveryUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                    data-test="forgot-password-whatsapp"
                >
                    <img src="{{ asset('icons/whatsapp.svg') }}" alt="" class="size-5" width="20" height="20" />
                    {{ __('Olvidé mi clave — :name', ['name' => $passwordRecoveryContact]) }}
                </a>
            @endif

            <a
                href="{{ route('help') }}"
                class="text-center text-sm font-medium text-portal-primary underline-offset-2 hover:underline dark:text-portal-primary"
                wire:navigate
            >
                {{ __('Ver equipo de Operaciones') }}
            </a>
        </div>
    </div>
</div>
