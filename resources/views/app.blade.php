<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- La app quedó fijada en modo claro (ver resources/js/hooks/use-appearance.tsx). --}}
        <style>
            html {
                background-color: #ffffff;
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=3" sizes="any">
        <link rel="icon" type="image/png" href="/favicon.png?v=3">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=3">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
