<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Local Self-Hosted Modernist Fonts -->
        <link rel="preload" href="/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/jetbrains-mono.woff2" as="font" type="font/woff2" crossorigin>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
