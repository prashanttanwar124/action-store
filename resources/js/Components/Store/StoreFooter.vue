<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { useStore } from '../../stores/cart';
import { Sparkles, ChevronRight } from 'lucide-vue-next';

defineProps({
  storeInfo: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['openStoreInfo']);

const store = useStore();
const page = usePage();
</script>

<template>
  <footer class="bg-[#f3efe7] border-t border-[#e0d9cc] mt-16 pt-12 pb-10 hidden sm:block">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-[#e0d9cc]/60">
        
        <!-- Column 1: Store Bio -->
        <div class="space-y-3">
          <div class="text-2xl font-serif font-medium tracking-tight text-[#1d1d1f] flex items-center gap-1.5">
            <span>Masala Mart</span>
            <span class="font-devanagari text-xs text-[#7a5620] font-medium bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
              {{ storeInfo.hindi_tagline || 'किराना' }}
            </span>
          </div>
          <p class="text-xs text-[#6e6e73] font-normal leading-relaxed">
            Clean, premium click-and-collect Indian groceries. Fresh stone-ground atta, farm-batched paneer, pure desi ghee, and festival sweets ready in 60 minutes.
          </p>
          <div class="text-xs text-[#7a5620] font-semibold flex items-center gap-1">
            <span>✓ 100% freshness guarantee on every order</span>
          </div>
        </div>

        <!-- Column 2: Hours & Pickup -->
        <div class="space-y-2 text-xs">
          <div class="font-semibold uppercase text-[11px] text-[#7a5620] tracking-wider">
            Store Pickup Location
          </div>
          <div class="text-[#1d1d1f] font-bold">{{ storeInfo.name }}</div>
          <div class="text-[#6e6e73] font-normal">
            {{ storeInfo.address }}<template v-if="storeInfo.city">, {{ storeInfo.city }} {{ storeInfo.state }}</template>
          </div>
          <div class="text-[#6e6e73] font-normal">{{ storeInfo.opening_hours }}</div>
          <button 
            type="button" 
            @click="$emit('openStoreInfo')"
            class="text-[#7a5620] font-semibold pt-1 hover:underline text-left cursor-pointer flex items-center gap-1"
          >
            <span>View full pickup guide & directions</span>
            <ChevronRight class="w-3 h-3" />
          </button>
        </div>

        <!-- Column 3: Quick Links -->
        <div class="space-y-2 text-xs">
          <div class="font-semibold uppercase text-[11px] text-[#86868b] tracking-wider">
            Customer Services
          </div>
          <ul class="space-y-1.5 text-[#6e6e73] font-normal">
            <li><Link href="/cart" class="hover:text-[#1d1d1f]">Shopping Cart & Pickup Status</Link></li>
            <li><Link href="/checkout" class="hover:text-[#1d1d1f]">Express Store Pickup</Link></li>
            <li><Link href="/account" class="hover:text-[#1d1d1f]">Subscriptions & Auto-Reorder</Link></li>
            <li><Link href="/reorder" class="hover:text-[#1d1d1f]">Past Orders & Instant Reorder</Link></li>
            <li v-if="page.props.auth?.user">
              <Link href="/logout" method="post" as="button" class="hover:text-rose-600 cursor-pointer">
                Sign Out ({{ page.props.auth.user.name.split(' ')[0] }})
              </Link>
            </li>
            <li v-else>
              <Link href="/login" class="hover:text-[#1d1d1f]">Customer Sign In / Register</Link>
            </li>
          </ul>
        </div>

        <!-- Column 4: Rewards & Pickup -->
        <div class="space-y-3 text-xs bg-[#f5eee2] p-4 rounded-2xl border border-[#e0d9cc]">
          <div class="font-semibold uppercase text-[11px] text-[#7a5620] flex items-center gap-1.5">
            <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
            <span>Masala Rewards</span>
          </div>
          <p class="text-[11px] text-[#6e6e73] font-normal leading-snug">
            Earn 1 point for every $1 spent. Curbside pickup ready in {{ storeInfo.pickup_time || '1 hr' }} at {{ storeInfo.name }}.
          </p>
          <div class="flex items-center justify-between text-xs text-[#1d1d1f] border-t border-[#e0d9cc] pt-2 font-bold">
            <span>Your Balance:</span>
            <span class="text-[#7a5620] font-serif font-medium">{{ store.masalaPoints }} pts</span>
          </div>
        </div>

      </div>

      <div class="pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-[#86868b] gap-3 font-normal">
        <div>© 2026 {{ storeInfo.name || 'Masala Mart' }}. All rights reserved. Modern Classic V3.</div>
        <div class="flex gap-4">
          <a href="#" class="hover:text-stone-600">Privacy Policy</a>
          <span>·</span>
          <a href="#" class="hover:text-stone-600">Terms of Service</a>
          <span>·</span>
          <a href="#" class="hover:text-stone-600">Click & Collect Terms</a>
        </div>
      </div>
    </div>
  </footer>
</template>
