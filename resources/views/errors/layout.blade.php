<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') — Masala Mart</title>

    <link rel="preload" href="/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/newsreader.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css'])

    <style>
        body {
            background-color: #fbf9f5;
            color: #1d1d1f;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .serif-font {
            font-family: 'Newsreader', Georgia, serif;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-[#1a1a1a] selection:text-white">
    <!-- Top Header -->
    <header class="bg-white/95 border-b border-[#e0d9cc] sticky top-0 z-40 backdrop-blur-md">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 group text-decoration-none">
                <span class="w-8 h-8 rounded-full bg-[#1a1a1a] text-white flex items-center justify-center font-serif font-bold text-sm tracking-tighter">
                    M
                </span>
                <span class="serif-font font-semibold text-lg text-[#1d1d1f] tracking-tight">
                    Masala Mart
                </span>
            </a>

            <div class="flex items-center gap-3">
                <a href="/cart" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc] hover:bg-[#ede2d1] transition-colors">
                    Store Cart
                </a>
                <a href="/" class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#1a1a1a] text-white hover:bg-black transition-colors">
                    Storefront
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center px-4 sm:px-6 py-12 sm:py-16">
        <div class="max-w-xl w-full text-center space-y-6">
            
            <div class="inline-flex flex-col items-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-[#f5eee2] border-2 border-[#e0d9cc] text-[#7a5620] flex items-center justify-center shadow-xs mx-auto mb-4">
                    @yield('icon')
                </div>

                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-[#7a5620] bg-[#f5eee2] px-3.5 py-1 rounded-full border border-[#e0d9cc]">
                    @yield('badge', 'ERROR')
                </span>
            </div>

            <div class="space-y-2">
                <div class="text-6xl sm:text-7xl lg:text-8xl serif-font font-bold text-[#1a1a1a] tracking-tight leading-none">
                    @yield('code', '404')
                </div>
                <h1 class="text-xl sm:text-2xl lg:text-3xl serif-font font-medium text-[#1d1d1f] tracking-tight max-w-lg mx-auto leading-snug">
                    @yield('heading', 'Something went wrong')
                </h1>
                <p class="text-xs sm:text-sm text-[#6e6e73] font-normal max-w-md mx-auto leading-relaxed pt-1">
                    @yield('message', 'We could not complete your request.')
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-3">
                <a href="/" class="w-full sm:w-auto px-7 py-3.5 bg-[#1a1a1a] hover:bg-black text-white text-xs sm:text-sm font-semibold rounded-full shadow-md transition-all flex items-center justify-center gap-2">
                    <span>← Return to Storefront</span>
                </a>
                <a href="/cart" class="w-full sm:w-auto px-6 py-3.5 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] text-xs sm:text-sm font-semibold rounded-full border border-[#e0d9cc] transition-colors flex items-center justify-center gap-2 shadow-xs">
                    <span>Check Store Pickup</span>
                </a>
            </div>

            <!-- Quick Links -->
            <div class="bg-white rounded-3xl border border-[#e0d9cc] p-5 sm:p-6 text-left shadow-xs space-y-3.5 mt-8">
                <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2.5">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#7a5620]">
                        Looking for these popular aisles?
                    </span>
                    <span class="text-[10px] text-[#86868b]">Click & Collect</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <a href="/products/atta" class="flex items-center justify-between p-3 rounded-2xl bg-[#fbf9f5] hover:bg-[#f5eee2] border border-[#e0d9cc] transition-colors text-xs text-[#1d1d1f]">
                        <div>
                            <div class="font-semibold">Chakki Atta & Flour</div>
                            <div class="text-[10px] text-[#86868b]">Staple · 20 lb</div>
                        </div>
                        <span>→</span>
                    </a>
                    <a href="/products/desi-ghee" class="flex items-center justify-between p-3 rounded-2xl bg-[#fbf9f5] hover:bg-[#f5eee2] border border-[#e0d9cc] transition-colors text-xs text-[#1d1d1f]">
                        <div>
                            <div class="font-semibold">Desi Ghee & Dairy</div>
                            <div class="text-[10px] text-[#86868b]">Pure Cow Ghee</div>
                        </div>
                        <span>→</span>
                    </a>
                    <a href="/recipe-kits/paneer-curry" class="flex items-center justify-between p-3 rounded-2xl bg-[#fbf9f5] hover:bg-[#f5eee2] border border-[#e0d9cc] transition-colors text-xs text-[#1d1d1f]">
                        <div>
                            <div class="font-semibold">Chef Recipe Kits</div>
                            <div class="text-[10px] text-[#86868b]">Dinner in 20m</div>
                        </div>
                        <span>→</span>
                    </a>
                    <a href="/products/sweets-box" class="flex items-center justify-between p-3 rounded-2xl bg-[#fbf9f5] hover:bg-[#f5eee2] border border-[#e0d9cc] transition-colors text-xs text-[#1d1d1f]">
                        <div>
                            <div class="font-semibold">Diwali Sweets & Mithai</div>
                            <div class="text-[10px] text-[#86868b]">Luxury Gift Box</div>
                        </div>
                        <span>→</span>
                    </a>
                </div>
            </div>

            <div class="text-center pt-2 text-[11px] text-[#86868b]">
                Masala Mart · 214 Main St. · Open daily 9:00 AM – 9:00 PM for store pickup.
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-[#e0d9cc] py-4 text-center text-xs text-[#86868b]">
        © 2026 Masala Mart Inc. All rights reserved. Express Click & Collect.
    </footer>
</body>
</html>
