<script setup>
import { useStore } from '../stores/cart';
import { RefreshCw, ShoppingBag, ArrowRight } from 'lucide-vue-next';

const store = useStore();

const pastOrders = [
  {
    id: 'ORD-9842',
    date: 'Oct 20, 2026',
    items: ['Chakki Atta (20 lb)', 'Desi Ghee (500 ml)', 'Glucose Biscuits'],
    total: 32.47,
    store: 'Main St. store',
  },
  {
    id: 'ORD-7721',
    date: 'Sep 28, 2026',
    items: ['Royal Basmati Rice (10 lb)', 'Fresh Malai Paneer', 'Fresh Curry Leaves'],
    total: 30.97,
    store: 'Main St. store',
  }
];

function reorderAll(orderItems) {
  store.addToCart(2); // Atta
  store.addToCart(3); // Ghee
  store.addToCart(4); // Biscuits
  store.isCartDrawerOpen = true;
}
</script>

<template>
  <div class="px-4 py-4 space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-black text-zinc-950">Past Orders & Reorder</h2>
      <span class="text-xs text-zinc-500 font-medium">2 previous orders</span>
    </div>

    <div v-for="order in pastOrders" :key="order.id" class="bg-white border border-zinc-200 rounded-xl p-4 shadow-xs">
      <div class="flex justify-between items-start pb-3 border-b border-zinc-100">
        <div>
          <div class="font-extrabold text-sm text-zinc-900">{{ order.id }}</div>
          <div class="text-xs text-zinc-500">{{ order.date }} · {{ order.store }}</div>
        </div>
        <div class="font-black text-sm text-zinc-950">${{ order.total.toFixed(2) }}</div>
      </div>

      <ul class="py-3 space-y-1 text-xs text-zinc-700 font-medium">
        <li v-for="(item, idx) in order.items" :key="idx" class="flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-[#E52E04]"></span>
          <span>{{ item }}</span>
        </li>
      </ul>

      <button 
        @click="reorderAll(order.items)"
        class="w-full py-2.5 bg-zinc-900 text-white font-bold text-xs rounded-lg hover:bg-zinc-800 flex items-center justify-center gap-2 cursor-pointer transition-colors"
      >
        <RefreshCw class="w-3.5 h-3.5" />
        <span>Reorder All Items</span>
      </button>
    </div>
  </div>
</template>
