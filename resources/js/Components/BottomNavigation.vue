<script setup>
import { useStore } from '../stores/cart';
import { Home, Search, RefreshCw, User } from 'lucide-vue-next';

const store = useStore();

const navItems = [
  { id: 'shop', label: 'Shop', icon: Home },
  { id: 'search', label: 'Search', icon: Search },
  { id: 'reorder', label: 'Reorder', icon: RefreshCw },
  { id: 'account', label: 'Account', icon: User },
];
</script>

<template>
  <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-zinc-200 shadow-lg">
    <div class="max-w-md mx-auto flex items-center justify-around h-[62px] px-2">
      <button
        v-for="item in navItems"
        :key="item.id"
        @click="store.activeTab = item.id"
        :class="[
          'flex flex-col items-center justify-center w-full h-full py-1 transition-all cursor-pointer select-none',
          store.activeTab === item.id ? 'text-[#E52E04]' : 'text-zinc-600 hover:text-zinc-900'
        ]"
      >
        <component 
          :is="item.icon" 
          :class="[
            'w-5 h-5 transition-transform',
            store.activeTab === item.id ? 'scale-110 stroke-[2.4]' : 'stroke-[1.8]'
          ]" 
        />
        <span 
          :class="[
            'text-[11px] mt-1 tracking-tight',
            store.activeTab === item.id ? 'font-bold' : 'font-medium'
          ]"
        >
          {{ item.label }}
        </span>
      </button>
    </div>
  </nav>
</template>
