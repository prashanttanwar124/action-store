<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Local Self-Hosted Design System Fonts -->
        <link rel="preload" href="/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/caprasimo.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/figtree.woff2" as="font" type="font/woff2" crossorigin>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
