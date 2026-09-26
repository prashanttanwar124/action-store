<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Masala Mart') }} Admin</title>

        <!-- Preload Local Self-Hosted Fonts -->
        <link rel="preload" href="/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/newsreader.woff2" as="font" type="font/woff2" crossorigin>

        <!-- Admin Backoffice Assets (Completely Isolated from Storefront) -->
        @routes
        @vite(['resources/css/admin.css', 'resources/js/admin.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-[#fbf9f5] text-[#1d1d1f]">
        @inertia
    </body>
</html>
