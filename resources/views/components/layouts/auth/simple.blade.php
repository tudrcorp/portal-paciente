@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth portal-auth-html">
    <head>
        @include('partials.head')
        <link rel="preload" as="image" href="{{ asset('images/porta-paciente-desktop.jpg') }}">
    </head>
    <body class="portal-liquid-root portal-auth-shell antialiased text-portal-ink dark:text-zinc-100">
        @php
            $authHero = asset('images/porta-paciente-desktop.jpg');
        @endphp

        {{-- Fondo a pantalla completa (incluye quirks de viewport en iOS Safari) --}}
        <div class="portal-auth-backdrop pointer-events-none" aria-hidden="true">
            <img
                src="{{ $authHero }}"
                alt=""
                width="1080"
                height="1080"
                class="portal-auth-backdrop__image"
                fetchpriority="high"
                decoding="async"
                sizes="100vw"
            />
            {{-- Capas suaves: dejan ver la foto a través del cristal --}}
            <div class="portal-auth-backdrop__veil portal-auth-backdrop__veil--gradient"></div>
            <div class="portal-auth-backdrop__veil portal-auth-backdrop__veil--tint"></div>
        </div>

        {{-- Formulario centrado sobre el fondo --}}
        <div class="portal-auth-content">
            <div class="w-full max-w-md">
                <x-glass-panel variant="ambient" class="portal-auth-card rounded-3xl" :padding="false">
                    <div class="p-6 sm:p-8 md:p-8">
                    <a
                        href="{{ route('home') }}"
                        class="mb-6 flex flex-col items-center gap-2 font-medium outline-offset-4 ring-offset-portal-surface focus-visible:ring-2 focus-visible:ring-portal-primary/80 dark:ring-offset-portal-navy"
                        wire:navigate
                    >
                        <span class="mb-1 flex shrink-0 justify-center">
                            <img
                                src="{{ asset('images/logoNewTDG.png') }}"
                                alt="{{ config('app.name', 'Laravel') }}"
                                width="152"
                                height="48"
                                class="mx-auto block h-8 w-auto max-w-36 object-contain object-center sm:h-9 sm:max-w-40 dark:hidden"
                            />
                            <img
                                src="{{ asset('images/logoWhiteTDG.png') }}"
                                alt="{{ config('app.name', 'Laravel') }}"
                                width="152"
                                height="48"
                                class="mx-auto hidden h-8 w-auto max-w-36 object-contain object-center drop-shadow-[0_2px_8px_rgba(0,0,0,0.4)] sm:h-9 sm:max-w-40 dark:block"
                            />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    <div class="auth-portal-slot flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                    </div>
                </x-glass-panel>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
