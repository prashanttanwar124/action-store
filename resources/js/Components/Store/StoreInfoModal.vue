<script setup>
import { 
  X, 
  MapPin, 
  ExternalLink, 
  Clock, 
  Store, 
  Car, 
  Phone, 
  Mail 
} from 'lucide-vue-next';

defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  storeInfo: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        @click.self="$emit('close')"
      >
        <div 
          class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-stone-200 overflow-hidden transform transition-all duration-200 max-h-[92vh] flex flex-col"
        >
          <!-- Modal Header -->
          <div class="px-6 py-5 bg-[#f7f4ee] border-b border-[#e5decb] flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span 
                  class="w-2 h-2 rounded-full animate-pulse"
                  :class="storeInfo.is_pickup_active !== false ? 'bg-emerald-500' : 'bg-amber-500'"
                ></span>
                <span class="text-[10px] font-bold text-[#8a6b32] uppercase tracking-wider">
                  {{ storeInfo.is_pickup_active !== false ? 'Open for Store Pickup Today' : 'Pickup Currently Paused' }}
                </span>
              </div>
              <h3 class="text-xl font-serif font-bold text-[#1d1d1f] mt-1">
                {{ storeInfo.name }}
              </h3>
              <p class="text-xs text-[#6e6e73] mt-0.5">
                {{ storeInfo.tagline }}
              </p>
            </div>
            <button 
              type="button" 
              @click="$emit('close')"
              class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-stone-500 hover:text-stone-900 border border-stone-200 flex items-center justify-center transition-colors cursor-pointer shadow-xs"
              aria-label="Close"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Modal Content (Scrollable) -->
          <div class="p-6 space-y-4 overflow-y-auto text-xs">
            
            <!-- Address & Google Maps Directions -->
            <div class="p-4 rounded-2xl bg-[#faf7f2] border border-[#e8e1d3] space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-white border border-[#dfd6c8] flex items-center justify-center text-[#8a6b32] shrink-0 shadow-xs">
                  <MapPin class="w-4 h-4 stroke-[2.2]" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-stone-900 text-sm">Store Address</div>
                  <div class="text-stone-700 text-xs mt-0.5 font-medium leading-relaxed">
                    {{ storeInfo.address }}<br />
                    {{ storeInfo.city }}, {{ storeInfo.state }} {{ storeInfo.zip }}
                  </div>
                </div>
              </div>

              <a 
                v-if="storeInfo.maps_url"
                :href="storeInfo.maps_url"
                target="_blank"
                rel="noopener noreferrer"
                class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-white hover:bg-stone-50 text-stone-900 font-semibold rounded-xl border border-[#dfd6c8] shadow-xs transition-colors cursor-pointer"
              >
                <ExternalLink class="w-3.5 h-3.5 text-[#8a6b32]" />
                <span>Get Directions in Google Maps</span>
              </a>
            </div>

            <!-- Pickup Prep Time & Store Hours -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="p-3.5 rounded-2xl bg-white border border-stone-200 space-y-1">
                <div class="flex items-center gap-1.5 text-stone-500 text-[11px] font-semibold uppercase tracking-wider">
                  <Clock class="w-3.5 h-3.5 text-[#8a6b32]" />
                  <span>Order Prep Time</span>
                </div>
                <div class="text-sm font-bold text-stone-900 flex items-center gap-1.5">
                  <span>{{ storeInfo.effective_pickup_time || storeInfo.pickup_time || 'Ready in 15 mins' }}</span>
                  <span v-if="storeInfo.is_busy" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300">Rush</span>
                </div>
                <p class="text-[11px] text-stone-500 font-normal leading-tight">
                  Order online and pick up at the express counter.
                </p>
              </div>

              <div class="p-3.5 rounded-2xl bg-white border border-stone-200 space-y-1">
                <div class="flex items-center gap-1.5 text-stone-500 text-[11px] font-semibold uppercase tracking-wider">
                  <Store class="w-3.5 h-3.5 text-[#8a6b32]" />
                  <span>Store Hours</span>
                </div>
                <div class="text-sm font-bold text-stone-900">
                  {{ storeInfo.opening_hours }}
                </div>
                <p class="text-[11px] text-stone-500 font-normal leading-tight">
                  Open 7 days a week for in-store shopping & pickup.
                </p>
              </div>
            </div>

            <!-- Curbside & Bay Instructions -->
            <div v-if="storeInfo.curbside_instructions" class="p-4 rounded-2xl bg-[#fdfbf7] border border-[#e8e1d3] space-y-2">
              <div class="flex items-center gap-2 text-stone-900 font-bold text-xs">
                <Car class="w-4 h-4 text-[#8a6b32]" />
                <span>Curbside & Parking Bay Instructions</span>
              </div>
              <p class="text-stone-600 text-xs leading-relaxed font-normal">
                {{ storeInfo.curbside_instructions }}
              </p>
            </div>

            <!-- Contact & Phone -->
            <div class="p-4 rounded-2xl bg-white border border-stone-200 space-y-2.5">
              <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider">
                Store Contact & Inquiries
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <a 
                  v-if="storeInfo.phone"
                  :href="'tel:' + storeInfo.phone" 
                  class="flex items-center gap-2 p-2.5 rounded-xl bg-stone-50 hover:bg-stone-100 text-stone-800 transition-colors"
                >
                  <Phone class="w-3.5 h-3.5 text-[#8a6b32] shrink-0" />
                  <span class="font-semibold">{{ storeInfo.phone }}</span>
                </a>
                <a 
                  v-if="storeInfo.email"
                  :href="'mailto:' + storeInfo.email" 
                  class="flex items-center gap-2 p-2.5 rounded-xl bg-stone-50 hover:bg-stone-100 text-stone-800 transition-colors truncate"
                >
                  <Mail class="w-3.5 h-3.5 text-[#8a6b32] shrink-0" />
                  <span class="font-semibold truncate">{{ storeInfo.email }}</span>
                </a>
              </div>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-stone-50 border-t border-stone-200 flex items-center justify-between">
            <span class="text-[11px] text-stone-500">
              Single store location · 100% Free Store Pickup
            </span>
            <button 
              type="button" 
              @click="$emit('close')"
              class="px-5 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl transition-all shadow-xs cursor-pointer"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
