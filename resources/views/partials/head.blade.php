@php
    /**
     * Color con el que iOS Safari / Chrome tiñen la barra de estado y la barra
     * inferior. Cada layout puede sobreescribirlo para que el chrome del
     * navegador se funda con el fondo de la pantalla.
     */
    $themeColorLight = $themeColorLight ?? ($themeColor ?? '#04335a');
    $themeColorDark = $themeColorDark ?? ($themeColor ?? '#031e36');
    $statusBarStyle = $statusBarStyle ?? 'default';
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content" />

<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>{{ $title ?? config('app.name') }}</title>

<meta name="theme-color" content="{{ $themeColorLight }}" media="(prefers-color-scheme: light)" />
<meta name="theme-color" content="{{ $themeColorDark }}" media="(prefers-color-scheme: dark)" />
<meta name="theme-color" content="{{ $themeColorLight }}" />

<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="{{ $statusBarStyle }}" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
