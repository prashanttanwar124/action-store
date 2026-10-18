<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  ArrowLeft, 
  Mail, 
  Calendar, 
  ShoppingBag, 
  Clock, 
  CheckCircle2, 
  Receipt,
  UserCheck
} from 'lucide-vue-next';

const props = defineProps({
  customer: {
    type: Object,
    required: true,
  },
  orders: {
    type: Array,
    required: true,
  },
});

const getInitials = (name) => {
  if (!name) return 'CU';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return parts[0].slice(0, 2).toUpperCase();
};

const getStatusClasses = (status) => {
  switch (status) {
    case 'confirmed':
      return 'bg-amber-50 text-amber-800 border-amber-300';
    case 'packing':
      return 'bg-sky-50 text-sky-800 border-sky-300';
    case 'ready_for_pickup':
    case 'ready':
      return 'bg-purple-50 text-purple-800 border-purple-300';
    case 'completed':
      return 'bg-emerald-50 text-emerald-800 border-emerald-300';
    case 'cancelled':
      return 'bg-rose-50 text-rose-800 border-rose-300';
    default:
      return 'bg-zinc-50 text-zinc-700 border-zinc-300';
  }
};
</script>

<template>
  <AdminLayout :title="customer.name + ' — Customer Details'">
    <div class="space-y-6">
      
      <!-- Back Link -->
      <div>
        <Link 
          :href="route('admin.customers.index')"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#52525b] hover:text-[#09090b] transition-colors"
        >
          <ArrowLeft class="w-3.5 h-3.5" />
          <span>Back to Customers Directory</span>
        </Link>
      </div>

      <!-- Customer Overview Header Card -->
      <div class="bg-white border border-[#e4e4e7] rounded-lg p-6 sm:p-8 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-md bg-[#18181b] text-white flex items-center justify-center font-bold text-xl shadow-xs shrink-0">
            {{ getInitials(customer.name) }}
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl sm:text-3xl font-semibold text-[#09090b] tracking-tight">
                {{ customer.name }}
              </h1>
              <span 
                v-if="customer.is_verified" 
                class="inline-flex items-center gap-1 text-[11px] text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md font-medium"
              >
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                <span>Verified Account</span>
              </span>
            </div>
            <p class="text-xs text-[#52525b] font-mono mt-1 flex items-center gap-1.5">
              <Mail class="w-3.5 h-3.5 text-[#71717a]" />
              <span>{{ customer.email }}</span>
            </p>
            <p class="text-xs text-[#71717a] mt-1 flex items-center gap-1.5">
              <Calendar class="w-3.5 h-3.5 text-[#18181b]" />
              <span>Customer joined {{ customer.created_at }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-4 shrink-0">
          <div class="bg-[#f4f4f5] border border-[#e4e4e7] rounded-lg px-5 py-3 text-center">
            <span class="text-[11px] uppercase font-semibold text-[#27272a] block">Lifetime Spend</span>
            <span class="font-semibold text-2xl text-[#09090b] mt-0.5 block">
              ${{ Number(customer.total_spent).toFixed(2) }}
            </span>
          </div>
          <div class="bg-[#f4f4f5] border border-[#e4e4e7] rounded-lg px-5 py-3 text-center">
            <span class="text-[11px] uppercase font-semibold text-[#27272a] block">Orders</span>
            <span class="font-semibold text-2xl text-[#09090b] mt-0.5 block">
              {{ customer.orders_count }}
            </span>
          </div>
        </div>
      </div>

      <!-- Orders List -->
      <div class="bg-white border border-[#e4e4e7] rounded-lg p-6 sm:p-7 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-[#e4e4e7]/60 pb-4">
          <h2 class="font-semibold text-xl text-[#09090b]">All Orders by {{ customer.name }}</h2>
          <span class="text-xs text-[#71717a]">{{ orders.length }} {{ orders.length === 1 ? 'record' : 'records' }}</span>
        </div>

        <div v-if="orders.length === 0" class="py-12 text-center text-xs text-[#71717a]">
          <ShoppingBag class="w-8 h-8 text-[#18181b] mx-auto mb-2 opacity-50" />
          <p class="font-medium text-[#09090b] text-sm">No orders recorded</p>
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="order in orders" 
            :key="order.id"
            class="bg-[#fafafa] border border-[#e4e4e7] rounded-lg p-5 space-y-3"
          >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e4e4e7]/60 pb-3">
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-base text-[#09090b]">{{ order.order_number }}</span>
                <span 
                  class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-md border"
                  :class="getStatusClasses(order.status)"
                >
                  {{ order.status }}
                </span>
              </div>
              <div class="text-sm font-semibold text-[#09090b]">
                Total: ${{ Number(order.total).toFixed(2) }}
              </div>
            </div>

            <div class="text-xs text-[#52525b] flex flex-wrap items-center gap-4">
              <span>Pickup: <strong class="text-[#09090b]">{{ order.pickup_slot }}</strong></span>
              <span>Location: <strong class="text-[#09090b]">{{ order.pickup_location }}</strong></span>
              <span>Placed: {{ order.created_at }}</span>
            </div>

            <!-- Items -->
            <div v-if="order.items && order.items.length > 0" class="pt-2 border-t border-[#e4e4e7]/40 space-y-1.5">
              <div class="text-[10px] uppercase font-semibold text-[#71717a] tracking-wider">
                Order Items:
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div 
                  v-for="item in order.items" 
                  :key="item.id"
                  class="flex items-center justify-between text-xs p-2 rounded-md bg-white border border-[#e4e4e7]/60"
                >
                  <div class="flex items-center gap-2 truncate">
                    <img 
                      v-if="item.image" 
                      :src="item.image" 
                      :alt="item.name" 
                      class="w-7 h-7 rounded object-cover border border-[#e4e4e7]" 
                    />
                    <span class="font-medium text-[#09090b] truncate">{{ item.name }}</span>
                    <span v-if="item.size" class="text-[10px] text-[#71717a]">({{ item.size }})</span>
                  </div>
                  <span class="font-mono text-xs font-semibold text-[#09090b] shrink-0 ml-2">
                    {{ item.quantity }} × ${{ Number(item.unit_price).toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
