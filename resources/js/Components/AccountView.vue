<script setup>
import { useStore } from '../stores/cart';
import { User, MapPin, CreditCard, Bell, Shield, LogOut } from 'lucide-vue-next';

const store = useStore();
</script>

<template>
  <div class="px-4 py-4 space-y-4">
    <!-- User Profile Box -->
    <div class="bg-white border border-zinc-200 rounded-xl p-4 flex items-center gap-3 shadow-xs">
      <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-[#E52E04] to-orange-500 text-white font-black text-xl flex items-center justify-center shadow-md">
        PT
      </div>
      <div>
        <h2 class="text-lg font-black text-zinc-950">Prashant Tanwar</h2>
        <p class="text-xs text-zinc-500">prashant@example.com · +1 (555) 234-5678</p>
        <span class="inline-block mt-1 bg-orange-100 text-[#E52E04] text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
          Masala Gold Rewards Member
        </span>
      </div>
    </div>

    <!-- Account Links -->
    <div class="bg-white border border-zinc-200 rounded-xl divide-y divide-zinc-100 overflow-hidden shadow-xs text-sm">
      <div class="p-3.5 flex items-center justify-between hover:bg-zinc-50 cursor-pointer">
        <div class="flex items-center gap-3 text-zinc-800 font-semibold">
          <MapPin class="w-4 h-4 text-[#E52E04]" />
          <span>Saved Addresses (Home, Office)</span>
        </div>
        <span class="text-xs text-zinc-400">2 addresses</span>
      </div>

      <div class="p-3.5 flex items-center justify-between hover:bg-zinc-50 cursor-pointer">
        <div class="flex items-center gap-3 text-zinc-800 font-semibold">
          <CreditCard class="w-4 h-4 text-[#E52E04]" />
          <span>Payment Methods</span>
        </div>
        <span class="text-xs text-zinc-400">Apple Pay ••• 4921</span>
      </div>

      <div class="p-3.5 flex items-center justify-between hover:bg-zinc-50 cursor-pointer">
        <div class="flex items-center gap-3 text-zinc-800 font-semibold">
          <Bell class="w-4 h-4 text-[#E52E04]" />
          <span>Order Notifications & SMS</span>
        </div>
        <span class="text-xs text-emerald-600 font-bold">Enabled</span>
      </div>

      <div class="p-3.5 flex items-center justify-between hover:bg-zinc-50 cursor-pointer">
        <div class="flex items-center gap-3 text-zinc-800 font-semibold">
          <Shield class="w-4 h-4 text-[#E52E04]" />
          <span>Privacy & Security</span>
        </div>
      </div>
    </div>
  </div>
</template>
