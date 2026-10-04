<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  ShoppingBag, 
  Search, 
  Clock, 
  MapPin, 
  Phone, 
  Mail, 
  Package, 
  CheckCircle2, 
  RotateCcw, 
  Volume2, 
  VolumeX, 
  Sparkles, 
  ExternalLink, 
  Truck, 
  Check, 
  AlertCircle,
  Eye,
  Filter,
  Loader2
} from 'lucide-vue-next';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Separator } from '@/Components/ui/separator';
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle, 
  DialogDescription, 
  DialogFooter 
} from '@/Components/ui/dialog';

const props = defineProps({
  orders: {
    type: Object, // LengthAwarePaginator
    required: true,
  },
  stats: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ status: 'all', search: '' }),
  },
});

// Reactive order list for instantaneous Reverb WebSocket updates
const ordersList = ref([...props.orders.data]);
const liveStats = ref({ ...props.stats });
const activeTab = ref(props.filters.status || 'all');
const searchQuery = ref(props.filters.search || '');
const isSoundEnabled = ref(true);
const selectedOrderForModal = ref(null);
const isDetailsModalOpen = ref(false);
const isReverbConnected = ref(false);
const updatingOrderId = ref(null);

// DoorDash-style persistent alarm state
const unacknowledgedOrders = ref([]);
const isAlarmLooping = ref(false);
let alarmIntervalId = null;
let titleFlashIntervalId = null;
let originalTitle = typeof document !== 'undefined' ? document.title : 'Live Orders — Masala Mart Admin';
let audioCtx = null;

// Instant client-side filtering without any page reload
const filteredOrders = computed(() => {
  return ordersList.value.filter(order => {
    // Status filter
    if (activeTab.value !== 'all' && order.status !== activeTab.value) {
      return false;
    }
    // Search query
    if (searchQuery.value && searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchNum = (order.order_number || '').toLowerCase().includes(q);
      const matchName = (order.customer_name || '').toLowerCase().includes(q);
      const matchPhone = (order.customer_phone || '').toLowerCase().includes(q);
      return matchNum || matchName || matchPhone;
    }
    return true;
  });
});

// Load audio preference from localStorage
onMounted(() => {
  const savedSound = localStorage.getItem('masala_order_sound');
  if (savedSound !== null) {
    isSoundEnabled.value = savedSound === 'true';
  }

  // Connect to Laravel Reverb via Echo
  if (typeof window !== 'undefined' && window.Echo) {
    try {
      window.Echo.connector.pusher.connection.bind('connected', () => {
        isReverbConnected.value = true;
      });
      window.Echo.connector.pusher.connection.bind('disconnected', () => {
        isReverbConnected.value = false;
      });
      if (window.Echo.connector.pusher.connection.state === 'connected') {
        isReverbConnected.value = true;
      }

      // Listen on private channel 'admin.orders' for new orders & status changes
      window.Echo.private('admin.orders')
        .listen('.order.placed', (payload) => {
          handleIncomingOrder(payload.order);
        })
        .listen('.order.status.updated', (payload) => {
          handleStatusUpdated(payload);
        });
    } catch (e) {
      console.warn('Reverb Echo listener setup error:', e);
    }
  }
});

onUnmounted(() => {
  stopDoorDashAlarm();
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leave('admin.orders');
  }
});

// AudioContext singleton for seamless playback without browser blocking
function getAudioContext() {
  if (typeof window === 'undefined') return null;
  const AudioCtx = window.AudioContext || window.webkitAudioContext;
  if (!AudioCtx) return null;
  if (!audioCtx) {
    audioCtx = new AudioCtx();
  }
  if (audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
}

// 3-Tone DoorDash/Toast style persistent kitchen alert (D5 -> A5 -> D6)
function playDoorDashChime() {
  if (!isSoundEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    const playTone = (freq, startTime, duration, gainVal = 0.35) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'triangle'; // warm metallic bell chime
      osc.frequency.setValueAtTime(freq, ctx.currentTime + startTime);
      gain.gain.setValueAtTime(gainVal, ctx.currentTime + startTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + startTime + duration);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(ctx.currentTime + startTime);
      osc.stop(ctx.currentTime + startTime + duration);
    };

    playTone(587.33, 0.0, 0.35, 0.3); // Note 1
    playTone(880.00, 0.12, 0.45, 0.35); // Note 2
    playTone(1174.66, 0.28, 0.9, 0.4); // Note 3
  } catch (err) {
    console.warn('DoorDash audio chime error:', err);
  }
}

// Start repeating DoorDash alarm until staff clicks to acknowledge order
function startDoorDashAlarm() {
  if (!isSoundEnabled.value) return;
  if (isAlarmLooping.value) return;

  isAlarmLooping.value = true;
  playDoorDashChime();

  // Loop alarm every 3.5 seconds
  if (!alarmIntervalId) {
    alarmIntervalId = setInterval(() => {
      if (unacknowledgedOrders.value.length > 0 && isSoundEnabled.value) {
        playDoorDashChime();
      } else {
        stopDoorDashAlarm();
      }
    }, 3500);
  }

  // Flash browser tab title to alert staff even when looking at another tab
  if (!titleFlashIntervalId && typeof document !== 'undefined') {
    originalTitle = document.title;
    let toggle = false;
    titleFlashIntervalId = setInterval(() => {
      if (unacknowledgedOrders.value.length > 0) {
        const topOrder = unacknowledgedOrders.value[0];
        document.title = toggle ? `🔔 (1) NEW ORDER ${topOrder.order_number || ''}!` : `🚨 NEW ORDER WAITING — Masala Mart`;
        toggle = !toggle;
      } else {
        clearInterval(titleFlashIntervalId);
        titleFlashIntervalId = null;
        document.title = originalTitle;
      }
    }, 1200);
  }
}

// Stop DoorDash alarm completely
function stopDoorDashAlarm() {
  if (alarmIntervalId) {
    clearInterval(alarmIntervalId);
    alarmIntervalId = null;
  }
  if (titleFlashIntervalId) {
    clearInterval(titleFlashIntervalId);
    titleFlashIntervalId = null;
    if (typeof document !== 'undefined') {
      document.title = originalTitle;
    }
  }
  isAlarmLooping.value = false;
  unacknowledgedOrders.value = [];
}

// Acknowledge a specific order (silences alarm if none left)
function acknowledgeOrder(order) {
  if (!order) return;
  order.isUnacknowledged = false;
  order.isNewArrival = false;
  unacknowledgedOrders.value = unacknowledgedOrders.value.filter(o => o.id !== order.id);
  if (unacknowledgedOrders.value.length === 0) {
    stopDoorDashAlarm();
  }
}

function toggleSound() {
  isSoundEnabled.value = !isSoundEnabled.value;
  localStorage.setItem('masala_order_sound', String(isSoundEnabled.value));
  if (!isSoundEnabled.value) {
    stopDoorDashAlarm();
  } else {
    playDoorDashChime();
  }
}

// Handle new incoming order from Laravel Reverb in real-time
function handleIncomingOrder(newOrder) {
  // Mark as unacknowledged so alarm rings until staff acts
  newOrder.isNewArrival = true;
  newOrder.isUnacknowledged = true;

  unacknowledgedOrders.value.unshift(newOrder);
  ordersList.value.unshift(newOrder);

  // Update live statistics
  liveStats.value.total_today = (liveStats.value.total_today || 0) + 1;
  liveStats.value.confirmed = (liveStats.value.confirmed || 0) + 1;
  liveStats.value.revenue_today = (liveStats.value.revenue_today || 0) + Number(newOrder.total);

  // Start DoorDash-style persistent looping alarm
  startDoorDashAlarm();
}

// Local stats updater for seamless instant transition without page reload
function updateLocalStats(fromStatus, toStatus) {
  if (fromStatus === toStatus) return;

  if (fromStatus === 'confirmed') {
    liveStats.value.confirmed = Math.max(0, (liveStats.value.confirmed || 0) - 1);
  } else if (fromStatus === 'packing') {
    liveStats.value.packing = Math.max(0, (liveStats.value.packing || 0) - 1);
  } else if (fromStatus === 'ready_for_pickup') {
    liveStats.value.ready_for_pickup = Math.max(0, (liveStats.value.ready_for_pickup || 0) - 1);
  }

  if (toStatus === 'confirmed') {
    liveStats.value.confirmed = (liveStats.value.confirmed || 0) + 1;
  } else if (toStatus === 'packing') {
    liveStats.value.packing = (liveStats.value.packing || 0) + 1;
  } else if (toStatus === 'ready_for_pickup') {
    liveStats.value.ready_for_pickup = (liveStats.value.ready_for_pickup || 0) + 1;
  } else if (toStatus === 'completed') {
    liveStats.value.completed_today = (liveStats.value.completed_today || 0) + 1;
  }
}

// Handle status update broadcast from Laravel Reverb (zero reload!)
function handleStatusUpdated(payload) {
  const target = ordersList.value.find(o => o.id === payload.order_id);
  if (target && target.status !== payload.status) {
    const prev = target.status;
    target.status = payload.status;
    updateLocalStats(prev, payload.status);
  }
}

// Filter tab selection (pure instant client-side switch)
function onTabSelect(tab) {
  activeTab.value = tab;
}

function clearSearch() {
  searchQuery.value = '';
}

// 1-Click Status Progression via Axios (Pure SPA, zero page reloads!)
async function updateOrderStatus(order, nextStatus) {
  if (updatingOrderId.value === order.id) return;
  acknowledgeOrder(order);
  const previousStatus = order.status;

  // Immediate optimistic update
  order.status = nextStatus;
  updateLocalStats(previousStatus, nextStatus);
  updatingOrderId.value = order.id;

  try {
    await axios.patch(`/admin/orders/${order.id}/status`, {
      status: nextStatus,
    });
  } catch (error) {
    // Revert optimistic update on error
    order.status = previousStatus;
    updateLocalStats(nextStatus, previousStatus);
    console.error('Failed to update order status via axios:', error);
  } finally {
    updatingOrderId.value = null;
  }
}

// Open order detailed drawer/modal
function openOrderDetails(order) {
  acknowledgeOrder(order);
  selectedOrderForModal.value = order;
  isDetailsModalOpen.value = true;
}

// Status badge styling helper
function getStatusBadge(status) {
  switch (status) {
    case 'confirmed':
      return { label: 'Received · New', class: 'bg-amber-100 text-amber-800 border-amber-300' };
    case 'packing':
      return { label: 'In Packing', class: 'bg-blue-100 text-blue-800 border-blue-300' };
    case 'ready_for_pickup':
      return { label: 'Ready at Bay 3', class: 'bg-emerald-100 text-emerald-800 border-emerald-300 animate-pulse' };
    case 'completed':
      return { label: 'Completed', class: 'bg-stone-100 text-stone-700 border-stone-300' };
    case 'cancelled':
      return { label: 'Cancelled', class: 'bg-rose-100 text-rose-800 border-rose-300' };
    default:
      return { label: status, class: 'bg-stone-100 text-stone-700 border-stone-300' };
  }
}
</script>

<template>
  <Head title="Live Orders — Masala Mart Admin" />

  <AdminLayout title="Live Orders">
    <div class="space-y-6 max-w-7xl mx-auto pb-16">

      <!-- DOORDASH-STYLE ACTIVE ORDER ALERT BANNER (Rings continuously until clicked) -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="unacknowledgedOrders.length > 0"
          class="sticky top-4 z-40 bg-gradient-to-r from-red-600 via-amber-600 to-red-600 text-white p-4 sm:p-5 rounded-3xl shadow-2xl border-2 border-red-300 flex flex-col sm:flex-row items-center justify-between gap-4 animate-pulse"
        >
          <div class="flex items-center gap-3.5 w-full sm:w-auto">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 border border-white/40 shadow-inner">
              <Volume2 class="w-6 h-6 text-white animate-bounce" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-[11px] uppercase font-extrabold tracking-widest bg-white text-red-700 px-2.5 py-0.5 rounded-full shadow-xs">
                  Live Order Alert
                </span>
                <span class="text-xs font-semibold text-white/90">
                  {{ unacknowledgedOrders.length }} New Order{{ unacknowledgedOrders.length > 1 ? 's' : '' }} Waiting!
                </span>
              </div>
              <p class="text-sm sm:text-base font-bold text-white mt-1">
                Order {{ unacknowledgedOrders[0].order_number }} · {{ unacknowledgedOrders[0].customer_name }} · ${{ Number(unacknowledgedOrders[0].total).toFixed(2) }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2.5 w-full sm:w-auto shrink-0">
            <Button 
              type="button"
              @click="openOrderDetails(unacknowledgedOrders[0])"
              class="flex-1 sm:flex-initial h-11 px-5 rounded-full bg-white text-red-700 hover:bg-stone-100 font-bold text-xs shadow-lg gap-2 cursor-pointer active:scale-95"
            >
              <Eye class="w-4 h-4" />
              <span>View & Accept Order</span>
            </Button>
            
            <Button 
              type="button"
              variant="outline"
              @click="stopDoorDashAlarm"
              class="h-11 px-4 rounded-full bg-black/30 hover:bg-black/50 text-white border-white/30 text-xs font-semibold shadow-xs gap-1.5 cursor-pointer active:scale-95"
              title="Silence alarm"
            >
              <VolumeX class="w-4 h-4" />
              <span>Silence</span>
            </Button>
          </div>
        </div>
      </transition>

      <!-- TOP BAR: HEADER, LIVE STATUS & AUDIO CHIME TOGGLE -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-[#e0d9cc] shadow-xs">
        <div>
          <div class="flex items-center gap-2.5">
            <h1 class="text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Live Orders
            </h1>
            
            <!-- Real-time live status pill -->
            <Badge 
              variant="outline" 
              class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold"
              :class="isReverbConnected ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-stone-50 text-stone-600 border-stone-300'"
            >
              <span class="w-2 h-2 rounded-full" :class="isReverbConnected ? 'bg-emerald-500 animate-pulse' : 'bg-stone-400'"></span>
              <span>{{ isReverbConnected ? 'Live Auto-Update' : 'Online' }}</span>
            </Badge>
          </div>
          <p class="text-xs text-[#6e6e73] mt-1">
            Real-time fulfillment pipeline. New customer orders appear automatically with audio alerts.
          </p>
        </div>

        <!-- Controls: Audio Toggle, Test Chime & Quick Search -->
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
          <Button 
            type="button" 
            variant="outline" 
            size="sm"
            @click="playDoorDashChime"
            class="h-9 px-3 rounded-full text-xs font-semibold text-stone-600 bg-stone-50 hover:bg-stone-100 border-[#dfd6c8] gap-1.5 shadow-2xs cursor-pointer"
            title="Preview kitchen chime"
          >
            <Sparkles class="w-3.5 h-3.5 text-amber-500" />
            <span>Test Chime</span>
          </Button>

          <Button 
            type="button" 
            variant="outline" 
            size="sm"
            @click="toggleSound"
            :class="isSoundEnabled ? 'text-amber-800 bg-amber-50/60 border-amber-300' : 'text-stone-500 bg-stone-50 border-stone-300'"
            class="h-9 px-3 rounded-full text-xs font-semibold gap-1.5 shadow-2xs cursor-pointer"
            title="Toggle audio chime on new orders"
          >
            <Volume2 v-if="isSoundEnabled" class="w-4 h-4 text-amber-600" />
            <VolumeX v-else class="w-4 h-4 text-stone-400" />
            <span>{{ isSoundEnabled ? 'Chime On' : 'Chime Muted' }}</span>
          </Button>

          <!-- Search Bar -->
          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 text-[#86868b] absolute left-3 top-1/2 -translate-y-1/2" />
            <Input 
              v-model="searchQuery" 
              @input="onSearchInput"
              type="text" 
              placeholder="Search #MM, Name, Phone..." 
              class="h-9 pl-8 pr-7 text-xs bg-[#faf8f5] border-[#dfd6c8] rounded-full focus-visible:bg-white"
            />
            <button 
              v-if="searchQuery" 
              type="button" 
              @click="clearSearch"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 p-0.5"
            >
              <RotateCcw class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>

      <!-- METRIC CARDS (shadcn-vue Card) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 sm:gap-4">
        
        <!-- Total Today -->
        <Card class="bg-white border-[#e0d9cc] rounded-2xl shadow-2xs">
          <CardContent class="p-4">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider flex items-center justify-between">
              <span>Today's Total</span>
              <ShoppingBag class="w-3.5 h-3.5 text-stone-400" />
            </div>
            <div class="text-2xl font-serif font-medium text-[#1d1d1f] mt-1.5">
              {{ liveStats.total_today }}
            </div>
          </CardContent>
        </Card>

        <!-- New / Confirmed -->
        <Card class="bg-amber-50/50 border-amber-200/80 rounded-2xl shadow-2xs">
          <CardContent class="p-4">
            <div class="text-[11px] font-semibold text-amber-800 uppercase tracking-wider flex items-center justify-between">
              <span>New / Confirmed</span>
              <Sparkles class="w-3.5 h-3.5 text-amber-600" />
            </div>
            <div class="text-2xl font-serif font-medium text-amber-900 mt-1.5">
              {{ liveStats.confirmed }}
            </div>
          </CardContent>
        </Card>

        <!-- In Packing -->
        <Card class="bg-blue-50/50 border-blue-200/80 rounded-2xl shadow-2xs">
          <CardContent class="p-4">
            <div class="text-[11px] font-semibold text-blue-800 uppercase tracking-wider flex items-center justify-between">
              <span>In Packing</span>
              <Package class="w-3.5 h-3.5 text-blue-600" />
            </div>
            <div class="text-2xl font-serif font-medium text-blue-900 mt-1.5">
              {{ liveStats.packing }}
            </div>
          </CardContent>
        </Card>

        <!-- Ready for Curbside -->
        <Card class="bg-emerald-50/50 border-emerald-200/80 rounded-2xl shadow-2xs">
          <CardContent class="p-4">
            <div class="text-[11px] font-semibold text-emerald-800 uppercase tracking-wider flex items-center justify-between">
              <span>Ready at Bay</span>
              <Truck class="w-3.5 h-3.5 text-emerald-600" />
            </div>
            <div class="text-2xl font-serif font-medium text-emerald-900 mt-1.5">
              {{ liveStats.ready_for_pickup }}
            </div>
          </CardContent>
        </Card>

        <!-- Today's Revenue -->
        <Card class="bg-white border-[#e0d9cc] rounded-2xl shadow-2xs col-span-2 sm:col-span-1">
          <CardContent class="p-4">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider flex items-center justify-between">
              <span>Today's Sales</span>
              <span class="text-stone-400 font-mono text-[10px]">USD</span>
            </div>
            <div class="text-2xl font-serif font-medium text-[#1d1d1f] mt-1.5">
              ${{ Number(liveStats.revenue_today || 0).toFixed(2) }}
            </div>
          </CardContent>
        </Card>

      </div>

      <!-- STATUS TABS (shadcn-vue Tabs) -->
      <div class="flex items-center justify-between gap-3 overflow-x-auto no-scrollbar">
        <Tabs :model-value="activeTab" @update:model-value="onTabSelect" class="w-full">
          <TabsList class="p-1 rounded-full bg-[#f3efe7] border border-[#e0d9cc] flex-wrap justify-start">
            <TabsTrigger value="all" class="text-xs font-semibold px-4 py-1.5 rounded-full">
              All Orders ({{ ordersList.length }})
            </TabsTrigger>
            <TabsTrigger value="confirmed" class="text-xs font-semibold px-4 py-1.5 rounded-full">
              New ({{ liveStats.confirmed }})
            </TabsTrigger>
            <TabsTrigger value="packing" class="text-xs font-semibold px-4 py-1.5 rounded-full">
              Packing ({{ liveStats.packing }})
            </TabsTrigger>
            <TabsTrigger value="ready_for_pickup" class="text-xs font-semibold px-4 py-1.5 rounded-full">
              Ready at Curbside ({{ liveStats.ready_for_pickup }})
            </TabsTrigger>
            <TabsTrigger value="completed" class="text-xs font-semibold px-4 py-1.5 rounded-full">
              Completed ({{ liveStats.completed_today }})
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <!-- LIVE ORDERS GRID (KDS Cards) -->
      <div v-if="filteredOrders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <Card 
          v-for="order in filteredOrders" 
          :key="order.id"
          class="bg-white border-[#e0d9cc] rounded-3xl overflow-hidden shadow-2xs hover:border-[#a47a3c]/60 transition-all flex flex-col justify-between"
          :class="order.isUnacknowledged ? 'ring-4 ring-red-500/90 shadow-2xl scale-[1.01] bg-red-50/10' : (order.isNewArrival ? 'ring-2 ring-emerald-500 shadow-lg scale-[1.01]' : '')"
        >
          <!-- Card Header: Order #, Slot & Status Badge -->
          <div class="p-4 bg-[#fbf9f5] border-b border-[#e0d9cc]/70">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-mono text-sm font-bold text-[#1d1d1f]">
                    {{ order.order_number }}
                  </span>
                  <Badge 
                    v-if="order.isUnacknowledged"
                    variant="destructive"
                    class="bg-red-600 hover:bg-red-700 text-white animate-pulse text-[10px] font-extrabold px-2 py-0.5 rounded-full cursor-pointer shadow-xs"
                    @click.stop="openOrderDetails(order)"
                  >
                    🚨 NEW · TAP TO VIEW
                  </Badge>
                  <Badge 
                    v-else
                    variant="outline" 
                    :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', getStatusBadge(order.status).class]"
                  >
                    {{ getStatusBadge(order.status).label }}
                  </Badge>
                </div>
                <div class="text-[11px] text-[#6e6e73] flex items-center gap-1.5 mt-1">
                  <Clock class="w-3 h-3 text-[#a47a3c]" />
                  <span class="font-medium text-[#1d1d1f]">{{ order.pickup_slot }}</span>
                  <span class="text-stone-300">·</span>
                  <span>{{ order.created_at_human }}</span>
                </div>
              </div>

              <!-- Total price -->
              <div class="text-right">
                <div class="font-serif font-medium text-lg text-[#1d1d1f]">
                  ${{ Number(order.total).toFixed(2) }}
                </div>
                <div class="text-[10px] text-[#86868b] uppercase tracking-wider">
                  {{ order.payment_method }}
                </div>
              </div>
            </div>

            <!-- Customer Details Mini-bar -->
            <div class="mt-3 pt-2.5 border-t border-[#e0d9cc]/60 flex items-center justify-between text-xs text-[#1d1d1f]">
              <span class="font-semibold truncate max-w-[140px]">{{ order.customer_name }}</span>
              <a 
                v-if="order.customer_phone" 
                :href="'tel:' + order.customer_phone" 
                class="text-[#a47a3c] hover:underline flex items-center gap-1 text-[11px] font-medium"
              >
                <Phone class="w-3 h-3" />
                <span>{{ order.customer_phone }}</span>
              </a>
            </div>
          </div>

          <!-- Card Body: Itemized Thumbnails & Summary -->
          <CardContent class="p-4 flex-1">
            <div class="space-y-2.5">
              <div class="text-[11px] font-bold uppercase tracking-wider text-[#86868b] flex items-center justify-between">
                <span>Items ({{ order.items_count }})</span>
                <span class="font-normal font-sans text-stone-500">{{ order.fulfillment_type }}</span>
              </div>

              <!-- List of first 3 items -->
              <div class="space-y-2">
                <div 
                  v-for="item in order.items.slice(0, 3)" 
                  :key="item.id"
                  class="flex items-center justify-between gap-2.5 text-xs"
                >
                  <div class="flex items-center gap-2 truncate">
                    <span class="w-5 h-5 rounded-md bg-[#f3efe7] border border-[#e0d9cc] text-[10px] font-bold text-[#1d1d1f] flex items-center justify-center shrink-0">
                      {{ item.quantity }}
                    </span>
                    <span class="font-medium text-[#1d1d1f] truncate">{{ item.name }}</span>
                  </div>
                  <span class="text-stone-500 font-mono text-[11px] shrink-0">
                    ${{ Number(item.total_price).toFixed(2) }}
                  </span>
                </div>

                <div v-if="order.items.length > 3" class="text-[11px] text-[#a47a3c] font-medium pt-0.5">
                  + {{ order.items.length - 3 }} more item(s)...
                </div>
              </div>

              <!-- Customer Notes if any -->
              <div v-if="order.notes" class="mt-2.5 p-2 rounded-xl bg-amber-50/60 border border-amber-200/60 text-[11px] text-amber-900">
                <span class="font-semibold">Note:</span> {{ order.notes }}
              </div>
            </div>
          </CardContent>

          <!-- Card Footer: Quick Action Progression Button -->
          <div class="p-3 bg-[#faf8f5] border-t border-[#e0d9cc]/60 flex items-center gap-2">
            
            <!-- Details Button -->
            <Button 
              type="button" 
              variant="outline" 
              size="sm"
              @click="openOrderDetails(order)"
              class="h-9 px-3 rounded-full text-xs font-semibold text-[#1d1d1f] border-[#dfd6c8] bg-white hover:bg-[#f3efe7] shadow-2xs gap-1"
            >
              <Eye class="w-3.5 h-3.5" />
              <span>Details</span>
            </Button>

            <!-- 1-Click Status Progression Pipeline -->
            <div class="flex-1">
              <Button 
                v-if="order.status === 'confirmed'"
                type="button"
                size="sm"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order, 'packing')"
                class="w-full h-9 rounded-full bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <Loader2 v-if="updatingOrderId === order.id" class="w-3.5 h-3.5 animate-spin" />
                <Package v-else class="w-3.5 h-3.5" />
                <span>Start Packing ➔</span>
              </Button>

              <Button 
                v-else-if="order.status === 'packing'"
                type="button"
                size="sm"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order, 'ready_for_pickup')"
                class="w-full h-9 rounded-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <Loader2 v-if="updatingOrderId === order.id" class="w-3.5 h-3.5 animate-spin" />
                <Truck v-else class="w-3.5 h-3.5" />
                <span>Ready for Bay 3 ➔</span>
              </Button>

              <Button 
                v-else-if="order.status === 'ready_for_pickup'"
                type="button"
                size="sm"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order, 'completed')"
                class="w-full h-9 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <Loader2 v-if="updatingOrderId === order.id" class="w-3.5 h-3.5 animate-spin" />
                <CheckCircle2 v-else class="w-3.5 h-3.5" />
                <span>Mark Picked Up ✓</span>
              </Button>

              <div 
                v-else-if="order.status === 'completed'" 
                class="text-center text-xs font-semibold text-emerald-700 py-1 flex items-center justify-center gap-1"
              >
                <Check class="w-3.5 h-3.5 stroke-[2.5]" />
                <span>Completed</span>
              </div>

              <div 
                v-else 
                class="text-center text-xs text-stone-500 py-1"
              >
                {{ order.status }}
              </div>
            </div>

          </div>

        </Card>

      </div>

      <!-- EMPTY STATE -->
      <div v-else class="p-12 bg-white rounded-3xl border border-[#e0d9cc] text-center space-y-3 shadow-xs">
        <div class="w-12 h-12 rounded-full bg-[#f3efe7] text-[#a47a3c] flex items-center justify-center mx-auto">
          <ShoppingBag class="w-6 h-6" />
        </div>
        <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">
          No orders found
        </h3>
        <p class="text-xs text-[#6e6e73] max-w-sm mx-auto">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'New orders will appear here automatically in real-time as customers place them.' }}
        </p>
      </div>

      <!-- ORDER DETAILS DIALOG MODAL (shadcn-vue Dialog) -->
      <Dialog :open="isDetailsModalOpen" @update:open="isDetailsModalOpen = $event">
        <DialogContent class="max-w-xl max-h-[90vh] overflow-y-auto">
          <DialogHeader v-if="selectedOrderForModal">
            <div class="flex items-center gap-2">
              <DialogTitle>Order {{ selectedOrderForModal.order_number }}</DialogTitle>
              <Badge 
                variant="outline" 
                :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', getStatusBadge(selectedOrderForModal.status).class]"
              >
                {{ getStatusBadge(selectedOrderForModal.status).label }}
              </Badge>
            </div>
            <DialogDescription>
              Fulfillment: {{ selectedOrderForModal.fulfillment_type }} · {{ selectedOrderForModal.pickup_slot }}
            </DialogDescription>
          </DialogHeader>

          <div v-if="selectedOrderForModal" class="space-y-4 py-2 text-xs">
            
            <!-- Customer info box -->
            <div class="bg-[#fbf9f5] border border-[#e0d9cc] rounded-2xl p-3.5 space-y-1.5">
              <div class="font-semibold text-sm text-[#1d1d1f]">{{ selectedOrderForModal.customer_name }}</div>
              <div class="text-[#6e6e73] flex flex-wrap gap-x-4 gap-y-1">
                <span>Email: {{ selectedOrderForModal.customer_email }}</span>
                <span>Phone: {{ selectedOrderForModal.customer_phone || 'N/A' }}</span>
                <span>Pickup: {{ selectedOrderForModal.pickup_location }}</span>
              </div>
            </div>

            <!-- Items table -->
            <div>
              <div class="font-bold text-xs uppercase tracking-wider text-[#86868b] mb-2">Itemized List</div>
              <div class="border border-[#e0d9cc] rounded-2xl divide-y divide-[#e0d9cc] overflow-hidden">
                <div 
                  v-for="item in selectedOrderForModal.items" 
                  :key="item.id"
                  class="p-3 flex items-center justify-between bg-white text-xs"
                >
                  <div class="flex items-center gap-3">
                    <span class="font-bold w-6 h-6 rounded-md bg-[#f3efe7] text-[#1d1d1f] flex items-center justify-center text-xs">
                      {{ item.quantity }}x
                    </span>
                    <div>
                      <div class="font-semibold text-[#1d1d1f]">{{ item.name }}</div>
                      <div class="text-[11px] text-[#6e6e73]">{{ item.size || 'Standard pack' }}</div>
                    </div>
                  </div>
                  <div class="font-mono font-medium text-right text-[#1d1d1f]">
                    ${{ Number(item.total_price).toFixed(2) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Financials -->
            <div class="pt-2 space-y-1.5 text-xs text-[#6e6e73]">
              <div class="flex justify-between">
                <span>Subtotal:</span>
                <span class="font-mono text-[#1d1d1f]">${{ Number(selectedOrderForModal.subtotal).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Fulfillment (Curbside):</span>
                <span class="font-mono text-emerald-700 font-semibold">FREE</span>
              </div>
              <Separator class="my-1.5" />
              <div class="flex justify-between text-sm font-bold text-[#1d1d1f]">
                <span>Total:</span>
                <span class="font-serif text-base">${{ Number(selectedOrderForModal.total).toFixed(2) }}</span>
              </div>
            </div>

          </div>

          <DialogFooter v-if="selectedOrderForModal">
            <Button 
              type="button" 
              variant="outline" 
              @click="isDetailsModalOpen = false"
              class="rounded-full text-xs"
            >
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AdminLayout>
</template>
