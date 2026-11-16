<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title inertia>{{ config('app.name', 'Masala Mart') }} POS</title>

        <!-- Preload Local Self-Hosted Fonts -->
        <link rel="preload" href="/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>

        <!-- In-Store POS Terminal Assets (Isolated Bundle) -->
        @routes
        @vite(['resources/js/pos.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased bg-zinc-100 text-zinc-900 selection:bg-zinc-900 selection:text-white">
        @inertia
    </body>
</html>
