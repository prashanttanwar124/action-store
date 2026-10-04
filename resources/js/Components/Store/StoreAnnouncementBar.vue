<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useStore } from '../../stores/cart';
import { Clock, Store, Sparkles } from 'lucide-vue-next';

const props = defineProps({
  storeInfo: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['openStoreInfo']);

const store = useStore();
</script>

<template>
  <div v-if="storeInfo?.announcement" class="bg-[#1a1a1a] text-white text-[11px] py-1.5 px-4 hidden sm:block">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
      <div class="flex items-center gap-2">
        <span class="bg-[#f5eee2] text-[#7a5620] border border-[#e0d9cc]/50 px-2 py-0.5 rounded-full font-semibold text-[10px] tracking-wide">
          {{ storeInfo.hindi_tagline || 'किराना' }} Notice
        </span>
        <span class="text-stone-300 font-normal">{{ storeInfo.announcement }}</span>
      </div>
      <div class="flex items-center gap-4 text-stone-400 font-normal">
        <button 
          type="button" 
          @click="$emit('openStoreInfo')"
          class="flex items-center gap-1.5 hover:text-stone-200 transition-colors cursor-pointer"
          title="Click to view pickup hours and location"
        >
          <Clock class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Pickup: {{ storeInfo.effective_pickup_time || storeInfo.pickup_time || 'Ready in 15 mins' }}</span>
        </button>
        <span class="text-stone-700">|</span>
        <button 
          type="button" 
          @click="$emit('openStoreInfo')"
          class="flex items-center gap-1.5 hover:text-stone-200 transition-colors cursor-pointer"
          title="Click to view address and directions"
        >
          <Store class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>Store Info & Directions</span>
        </button>
        <span class="text-stone-700">|</span>
        <Link href="/account" class="text-stone-200 hover:text-white font-semibold flex items-center gap-1">
          <Sparkles class="w-3 h-3 text-[#a47a3c]" />
          <span>{{ store.masalaPoints.toLocaleString() }} pts</span>
        </Link>
      </div>
    </div>
  </div>
</template>
