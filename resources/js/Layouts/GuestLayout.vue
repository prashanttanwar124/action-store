<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles, ShieldCheck, Clock, Store } from 'lucide-vue-next';
</script>

<template>
  <div class="min-h-screen bg-[#fbf9f5] flex flex-col justify-between selection:bg-[#a47a3c] selection:text-white antialiased text-[#1d1d1f]">
    
    <!-- Top Bar Navigation -->
    <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-6 pb-2 flex items-center justify-between">
      <Link 
        href="/" 
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] bg-white border border-[#e0d9cc] px-3.5 py-1.5 rounded-full transition-colors shadow-2xs group"
      >
        <ArrowLeft class="w-3.5 h-3.5 text-[#86868b] group-hover:-translate-x-0.5 transition-transform" />
        <span>Return to Storefront</span>
      </Link>

      <div class="hidden sm:flex items-center gap-3 text-xs text-[#6e6e73]">
        <div class="flex items-center gap-1 text-[11px] font-medium">
          <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Pickup Ready in 1 hr</span>
        </div>
        <span class="text-[#dfd6c8]">·</span>
        <div class="flex items-center gap-1 text-[11px] font-medium">
          <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Earn 1 pt per $1</span>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col justify-center items-center px-4 sm:px-6 py-6 sm:py-10">
      
      <!-- Logo & Branding -->
      <div class="text-center mb-6 space-y-1.5">
        <Link href="/" class="inline-flex items-center gap-2 group">
          <span class="text-3xl sm:text-4xl font-serif font-medium text-[#1d1d1f] tracking-tight group-hover:text-[#a47a3c] transition-colors">
            Masala Mart
          </span>
          <span class="font-devanagari text-xs text-[#7a5620] font-medium bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
            किराना
          </span>
        </Link>
        <p class="text-xs text-[#6e6e73]">
          Authentic Indian Groceries · Click-and-Collect Express
        </p>
      </div>

      <!-- Card Container -->
      <div class="w-full max-w-md bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-sm">
        <slot />
      </div>

      <!-- Trust Badges -->
      <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-[11px] text-[#86868b]">
        <div class="flex items-center gap-1.5">
          <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
          <span>100% Secure Checkout</span>
        </div>
        <span>·</span>
        <div class="flex items-center gap-1.5">
          <Store class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Main St. Store Pickup</span>
        </div>
        <span>·</span>
        <div class="flex items-center gap-1.5">
          <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Masala Rewards</span>
        </div>
      </div>

    </main>

    <!-- Simple Footer -->
    <footer class="w-full py-4 text-center text-xs text-[#86868b] border-t border-[#e0d9cc]/60 bg-[#f4efe6]/50">
      <div class="max-w-5xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>© 2026 Masala Mart. All rights reserved.</span>
        <div class="flex items-center gap-3 text-[11px]">
          <Link href="/" class="hover:text-[#1d1d1f]">Home</Link>
          <span>·</span>
          <Link href="/cart" class="hover:text-[#1d1d1f]">Cart</Link>
          <span>·</span>
          <Link href="/account" class="hover:text-[#1d1d1f]">Customer Account</Link>
        </div>
      </div>
    </footer>

  </div>
</template>
