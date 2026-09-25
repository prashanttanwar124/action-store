<script setup>
import { MapPin, Phone, ShieldCheck, Truck, RefreshCw, Award } from 'lucide-vue-next';
</script>

<template>
  <footer class="bg-zinc-950 text-white border-t-2 border-zinc-900 mt-16 pt-12 pb-8 rounded-none">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Top Value Props Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 pb-10 border-b border-zinc-800">
        <div class="flex items-center gap-3.5 p-4 bg-zinc-900 border border-zinc-800 rounded-none">
          <div class="w-10 h-10 bg-[#E52E04] text-white flex items-center justify-center shrink-0 border border-white/20">
            <Truck class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div>
            <h4 class="font-black text-sm text-white uppercase tracking-tight">Express 1-Hour Pickup</h4>
            <p class="text-xs text-zinc-400">Ready at your nearest local store</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5 p-4 bg-zinc-900 border border-zinc-800 rounded-none">
          <div class="w-10 h-10 bg-[#E52E04] text-white flex items-center justify-center shrink-0 border border-white/20">
            <Award class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div>
            <h4 class="font-black text-sm text-white uppercase tracking-tight">100% Authentic Quality</h4>
            <p class="text-xs text-zinc-400">Directly sourced premium brands</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5 p-4 bg-zinc-900 border border-zinc-800 rounded-none">
          <div class="w-10 h-10 bg-[#E52E04] text-white flex items-center justify-center shrink-0 border border-white/20">
            <ShieldCheck class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div>
            <h4 class="font-black text-sm text-white uppercase tracking-tight">Freshness Guaranteed</h4>
            <p class="text-xs text-zinc-400">100% replacement or refund</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5 p-4 bg-zinc-900 border border-zinc-800 rounded-none">
          <div class="w-10 h-10 bg-[#E52E04] text-white flex items-center justify-center shrink-0 border border-white/20">
            <RefreshCw class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div>
            <h4 class="font-black text-sm text-white uppercase tracking-tight">Easy 1-Click Reorder</h4>
            <p class="text-xs text-zinc-400">Saved carts & frequent staples</p>
          </div>
        </div>
      </div>

      <!-- Main Footer Columns -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-8 py-10">
        <!-- Col 1: Brand Info -->
        <div class="col-span-2 space-y-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-[#E52E04] flex items-center justify-center text-white font-black rounded-none">
              M
            </div>
            <span class="text-2xl font-black tracking-tight text-white uppercase">Masala Mart</span>
          </div>
          <p class="text-xs text-zinc-400 leading-relaxed max-w-sm">
            Your neighborhood authentic Indian grocery destination. Shop fresh staples, daily dairy, festival sweets, organic spices, and household essentials online with fast pickup or doorstep delivery.
          </p>

          <div class="flex items-center gap-3 text-xs text-zinc-400 pt-2 font-mono">
            <span class="flex items-center gap-1"><MapPin class="w-3.5 h-3.5 text-[#E52E04]" /> Main St. store, NY</span>
            <span>•</span>
            <span class="flex items-center gap-1"><Phone class="w-3.5 h-3.5 text-[#E52E04]" /> 1-800-MASALA</span>
          </div>
        </div>

        <!-- Col 2: Categories -->
        <div>
          <h4 class="text-xs font-black uppercase tracking-wider text-zinc-300 mb-3">Categories</h4>
          <ul class="space-y-2 text-xs text-zinc-400 font-medium">
            <li><a href="#" class="hover:text-white transition-colors">Diwali & Sweets</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Atta, Flours & Rice</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Desi Ghee & Paneer</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Whole Organic Spices</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Chai & Biscuits</a></li>
          </ul>
        </div>

        <!-- Col 3: Customer Care -->
        <div>
          <h4 class="text-xs font-black uppercase tracking-wider text-zinc-300 mb-3">Customer Care</h4>
          <ul class="space-y-2 text-xs text-zinc-400 font-medium">
            <li><a href="#" class="hover:text-white transition-colors">Store Locator</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Track Orders</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Return Policy</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Help Center / FAQ</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Contact Support</a></li>
          </ul>
        </div>

        <!-- Col 4: Newsletter -->
        <div>
          <h4 class="text-xs font-black uppercase tracking-wider text-zinc-300 mb-3">Weekly Offers</h4>
          <p class="text-xs text-zinc-400 mb-3">Subscribe for festival deals & discount codes.</p>
          <div class="flex gap-2">
            <input 
              type="email" 
              placeholder="Enter your email" 
              class="w-full px-3 py-2 bg-zinc-900 border border-zinc-800 rounded-none text-xs text-white placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-[#E52E04]"
            />
            <button class="px-3 py-2 bg-[#E52E04] text-white font-black text-xs rounded-none hover:bg-[#CC2500] shrink-0 uppercase tracking-wider">
              Join
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Credits -->
      <div class="pt-8 border-t border-zinc-800 flex flex-col md:flex-row items-center justify-between text-xs text-zinc-500 gap-4">
        <div>© 2026 Masala Mart Inc. All rights reserved. Built with Laravel + Vue 3 + Tailwind CSS.</div>
        <div class="flex gap-4">
          <a href="#" class="hover:text-zinc-400">Privacy Policy</a>
          <a href="#" class="hover:text-zinc-400">Terms of Service</a>
          <a href="#" class="hover:text-zinc-400">Accessibility</a>
        </div>
      </div>
    </div>
  </footer>
</template>
