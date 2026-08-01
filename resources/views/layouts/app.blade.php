<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $branding->siteName()) — {{ $branding->siteName() }}</title>

    {{-- Neutral default chrome for the branding screen. The host application
         points config('branding.layout') at its own layout ("layouts.app") so
         the screen renders in the host's chrome; this fallback exists mainly for
         the package's isolated test suite. --}}
    <link rel="stylesheet" href="{{ \ConferenceTools\Branding\Support\Asset::url('vendor/branding/css/iccm.css') }}">
    <link rel="stylesheet" href="{{ \ConferenceTools\Branding\Support\Asset::url('vendor/branding/css/iccm-utilities.css') }}">

    {{-- The brand colors are per-install values read from the branding
         database, so they cannot live in a static stylesheet; every rule in the
         sheets above resolves them through these custom properties. --}}
    <style nonce="{{ $cspNonce ?? '' }}">
        :root {
            --color-primary: {{ $branding->color('primary') }};
            --color-secondary: {{ $branding->color('secondary') }};
            --color-bg: {{ $branding->color('background') }};
            --color-text: {{ $branding->color('text') }};
        }
    </style>

    @stack('head')
</head>

<body>
    <main>
        @yield('content')
    </main>
</body>

</html>
