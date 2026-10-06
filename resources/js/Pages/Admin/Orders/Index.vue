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
  Loader2,
  Printer,
  Columns3,
  LayoutGrid,
  ArrowRight,
  Ban,
  Calendar,
  DollarSign
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
const fulfillmentFilter = ref('all'); // 'all' | 'Store Pickup' | 'Home Delivery'
const viewMode = ref('board'); // 'board' | 'grid'
const searchQuery = ref(props.filters.search || '');
const isSoundEnabled = ref(true);
const selectedOrderForModal = ref(null);
const isDetailsModalOpen = ref(false);
const isPrintModalOpen = ref(false);
const orderToPrint = ref(null);
const isCancelModalOpen = ref(false);
const orderToCancel = ref(null);
const isCancelling = ref(false);
const isReverbConnected = ref(false);
const updatingOrderId = ref(null);

// Real-time kitchen chime alert state
const unacknowledgedOrders = ref([]);
const isAlarmLooping = ref(false);
let alarmIntervalId = null;
let titleFlashIntervalId = null;
let originalTitle = typeof document !== 'undefined' ? document.title : 'Live Orders — Masala Mart Admin';
let audioCtx = null;

// Filtered orders pipeline
const filteredOrders = computed(() => {
  return ordersList.value.filter(order => {
    // Status filter (when in grid view or tab-specific)
    if (activeTab.value !== 'all' && order.status !== activeTab.value) {
      return false;
    }
    // Fulfillment type filter
    if (fulfillmentFilter.value !== 'all' && order.fulfillment_type !== fulfillmentFilter.value) {
      return false;
    }
    // Search query
    if (searchQuery.value && searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchNum = (order.order_number || '').toLowerCase().includes(q);
      const matchName = (order.customer_name || '').toLowerCase().includes(q);
      const matchPhone = (order.customer_phone || '').toLowerCase().includes(q);
      const matchAddress = (order.delivery_address || '').toLowerCase().includes(q);
      return matchNum || matchName || matchPhone || matchAddress;
    }
    return true;
  });
});

// Pipeline columns for Board (Kanban KDS) view
const boardColumns = computed(() => {
  const baseList = ordersList.value.filter(order => {
    if (fulfillmentFilter.value !== 'all' && order.fulfillment_type !== fulfillmentFilter.value) {
      return false;
    }
    if (searchQuery.value && searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchNum = (order.order_number || '').toLowerCase().includes(q);
      const matchName = (order.customer_name || '').toLowerCase().includes(q);
      const matchPhone = (order.customer_phone || '').toLowerCase().includes(q);
      const matchAddress = (order.delivery_address || '').toLowerCase().includes(q);
      return matchNum || matchName || matchPhone || matchAddress;
    }
    return true;
  });

  return [
    {
      id: 'confirmed',
      title: 'New Orders',
      subtitle: 'Awaiting Packing',
      icon: Sparkles,
      color: 'amber',
      orders: baseList.filter(o => o.status === 'confirmed'),
    },
    {
      id: 'packing',
      title: 'In Packing',
      subtitle: 'Gathering & Packaging',
      icon: Package,
      color: 'blue',
      orders: baseList.filter(o => o.status === 'packing'),
    },
    {
      id: 'ready_for_pickup',
      title: 'Ready for Dispatch',
      subtitle: 'Curbside / Driver Pickup',
      icon: Truck,
      color: 'emerald',
      orders: baseList.filter(o => o.status === 'ready_for_pickup'),
    },
    {
      id: 'completed',
      title: 'Completed',
      subtitle: 'Fulfilled Today',
      icon: CheckCircle2,
      color: 'stone',
      orders: baseList.filter(o => o.status === 'completed'),
    },
  ];
});

// Load audio preference from localStorage
onMounted(() => {
  const savedSound = localStorage.getItem('masala_order_sound');
  if (savedSound !== null) {
    isSoundEnabled.value = savedSound === 'true';
  }
  const savedView = localStorage.getItem('masala_kds_view');
  if (savedView) {
    viewMode.value = savedView;
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
  stopOrderAlarm();
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leave('admin.orders');
  }
});

// AudioContext singleton for seamless kitchen chime playback
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

// Warm harmonic kitchen bell chime (E5 -> B5 -> E6)
function playOrderChime() {
  if (!isSoundEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    const playHarmonicTone = (freq, startTime, duration, gainVal = 0.28) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, ctx.currentTime + startTime);
      gain.gain.setValueAtTime(gainVal, ctx.currentTime + startTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + startTime + duration);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(ctx.currentTime + startTime);
      osc.stop(ctx.currentTime + startTime + duration);
    };

    // Soft pleasant kitchen chime chords
    playHarmonicTone(659.25, 0.00, 0.50, 0.25); // E5
    playHarmonicTone(987.77, 0.14, 0.60, 0.28); // B5
    playHarmonicTone(1318.51, 0.30, 0.95, 0.30); // E6
  } catch (err) {
    console.warn('Audio chime error:', err);
  }
}

// Start repeating chime until staff acknowledges order
function startOrderAlarm() {
  if (!isSoundEnabled.value) return;
  if (isAlarmLooping.value) return;

  isAlarmLooping.value = true;
  playOrderChime();

  if (!alarmIntervalId) {
    alarmIntervalId = setInterval(() => {
      if (unacknowledgedOrders.value.length > 0 && isSoundEnabled.value) {
        playOrderChime();
      } else {
        stopOrderAlarm();
      }
    }, 4500);
  }

  // Flash browser tab title to alert staff in other tabs
  if (!titleFlashIntervalId && typeof document !== 'undefined') {
    originalTitle = document.title;
    let toggle = false;
    titleFlashIntervalId = setInterval(() => {
      if (unacknowledgedOrders.value.length > 0) {
        const topOrder = unacknowledgedOrders.value[0];
        document.title = toggle ? `(${unacknowledgedOrders.value.length}) New Order ${topOrder.order_number || ''}` : `Live Order Received — Masala Mart`;
        toggle = !toggle;
      } else {
        clearInterval(titleFlashIntervalId);
        titleFlashIntervalId = null;
        document.title = originalTitle;
      }
    }, 1300);
  }
}

// Stop alarm completely
function stopOrderAlarm() {
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

// Acknowledge a specific order
function acknowledgeOrder(order) {
  if (!order) return;
  order.isUnacknowledged = false;
  order.isNewArrival = false;
  unacknowledgedOrders.value = unacknowledgedOrders.value.filter(o => o.id !== order.id);
  if (unacknowledgedOrders.value.length === 0) {
    stopOrderAlarm();
  }
}

function toggleSound() {
  isSoundEnabled.value = !isSoundEnabled.value;
  localStorage.setItem('masala_order_sound', String(isSoundEnabled.value));
  if (!isSoundEnabled.value) {
    stopOrderAlarm();
  } else {
    playOrderChime();
  }
}

function setViewMode(mode) {
  viewMode.value = mode;
  localStorage.setItem('masala_kds_view', mode);
}

// Handle new incoming order from Laravel Reverb in real-time
function handleIncomingOrder(newOrder) {
  newOrder.isNewArrival = true;
  newOrder.isUnacknowledged = true;

  unacknowledgedOrders.value.unshift(newOrder);
  ordersList.value.unshift(newOrder);

  // Update live statistics
  liveStats.value.total_today = (liveStats.value.total_today || 0) + 1;
  liveStats.value.confirmed = (liveStats.value.confirmed || 0) + 1;
  liveStats.value.revenue_today = (liveStats.value.revenue_today || 0) + Number(newOrder.total);

  startOrderAlarm();
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

  order.status = nextStatus;
  updateLocalStats(previousStatus, nextStatus);
  updatingOrderId.value = order.id;

  try {
    await axios.patch(`/admin/orders/${order.id}/status`, {
      status: nextStatus,
    });
  } catch (error) {
    order.status = previousStatus;
    updateLocalStats(nextStatus, previousStatus);
    console.error('Failed to update order status:', error);
    const serverMessage = error.response?.data?.errors?.status?.[0] || error.response?.data?.message;
    window.alert(serverMessage || 'Could not update the order status. Please try again.');
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

// Open print ticket modal
function printOrderTicket(order) {
  acknowledgeOrder(order);
  orderToPrint.value = order;
  isPrintModalOpen.value = true;
}

function triggerPrint() {
  window.print();
}

// Open Cancel & Refund Dialog
function confirmCancelOrder(order) {
  acknowledgeOrder(order);
  orderToCancel.value = order;
  isCancelModalOpen.value = true;
}

async function executeCancelOrder() {
  if (!orderToCancel.value || isCancelling.value) return;
  isCancelling.value = true;
  const order = orderToCancel.value;

  try {
    await updateOrderStatus(order, 'cancelled');
    isCancelModalOpen.value = false;
    orderToCancel.value = null;
  } finally {
    isCancelling.value = false;
  }
}

// Next status calculation helper based on fulfillment type
function getNextStatusInfo(order) {
  const isDelivery = order.fulfillment_type === 'Home Delivery';

  if (order.status === 'confirmed') {
    return {
      nextStatus: 'packing',
      label: 'Start Packing',
      badgeClass: 'bg-[#1a1a1a] hover:bg-black text-white',
      icon: Package,
    };
  }

  if (order.status === 'packing') {
    return {
      nextStatus: 'ready_for_pickup',
      label: isDelivery ? 'Ready for Driver' : 'Ready for Pickup',
      badgeClass: 'bg-amber-600 hover:bg-amber-700 text-white',
      icon: Truck,
    };
  }

  if (order.status === 'ready_for_pickup') {
    return {
      nextStatus: 'completed',
      label: isDelivery ? 'Mark Delivered' : 'Handed to Customer',
      badgeClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
      icon: CheckCircle2,
    };
  }

  return null;
}

// Status badge styling helper
function getStatusBadge(status, fulfillmentType = '') {
  const isDelivery = fulfillmentType === 'Home Delivery';

  switch (status) {
    case 'confirmed':
      return { label: 'New Order', class: 'bg-amber-50 text-amber-800 border-amber-300' };
    case 'packing':
      return { label: 'In Packing', class: 'bg-blue-50 text-blue-800 border-blue-300' };
    case 'ready_for_pickup':
      return { 
        label: isDelivery ? 'Ready for Driver' : 'Ready at Counter', 
        class: 'bg-emerald-50 text-emerald-800 border-emerald-300 font-semibold' 
      };
    case 'completed':
      return { label: 'Fulfilled', class: 'bg-stone-100 text-stone-700 border-stone-200' };
    case 'cancelled':
      return { label: 'Cancelled & Refunded', class: 'bg-rose-50 text-rose-800 border-rose-300' };
    default:
      return { label: status, class: 'bg-stone-50 text-stone-700 border-stone-200' };
  }
}
</script>

<template>
  <Head title="Kitchen Display & Live Orders — Masala Mart Admin" />

  <AdminLayout title="Kitchen Display" :full-width="true">
    <div class="space-y-4 sm:space-y-5 w-full pb-16">

      <!-- ELEGANT LIVE ORDER NOTIFICATION BANNER -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-3 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="unacknowledgedOrders.length > 0"
          class="sticky top-4 z-40 bg-[#1a1a1a] text-white p-3.5 sm:p-4 rounded-2xl shadow-2xl border border-[#a47a3c]/50 flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4"
        >
          <div class="flex items-center gap-3.5 w-full sm:w-auto">
            <!-- Masala Mart Store Brand Crest with Live Pulse Ring -->
            <div class="relative shrink-0">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#c49856] to-[#8d662e] text-white flex items-center justify-center font-serif font-bold text-lg shadow-md border border-[#f5d5aa]/30">
                M
              </div>
              <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#e4b97a] opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-[#e4b97a] border-2 border-[#1a1a1a]"></span>
              </span>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase font-bold tracking-widest bg-[#e4b97a] text-stone-950 px-2.5 py-0.5 rounded-full font-sans inline-flex items-center gap-1">
                  <Sparkles class="w-3 h-3 text-stone-950 fill-stone-950" />
                  <span>Live Order Received</span>
                </span>
                <span class="text-xs font-semibold text-stone-300">
                  {{ unacknowledgedOrders.length }} Pending Order{{ unacknowledgedOrders.length > 1 ? 's' : '' }}
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
              class="flex-1 sm:flex-initial h-10 px-5 rounded-full bg-[#e4b97a] hover:bg-[#d6a55e] text-stone-950 font-bold text-xs shadow-md gap-2 cursor-pointer active:scale-95"
            >
              <Eye class="w-4 h-4" />
              <span>Accept & Start</span>
            </Button>
            
            <Button 
              type="button"
              variant="outline"
              @click="stopOrderAlarm"
              class="h-10 px-4 rounded-full bg-stone-800 hover:bg-stone-700 text-stone-200 border-stone-700 text-xs font-semibold shadow-xs gap-1.5 cursor-pointer active:scale-95"
              title="Silence alert chime"
            >
              <VolumeX class="w-4 h-4" />
              <span>Silence</span>
            </Button>
          </div>
        </div>
      </transition>

      <!-- TOP BAR: HEADER, LIVE STATUS, CONTROLS & SEARCH -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-white p-4 sm:p-4.5 rounded-2xl border border-[#e0d9cc] shadow-xs">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              Live Order Pipeline
            </h1>
            
            <!-- Real-time live status pill -->
            <Badge 
              variant="outline" 
              class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
              :class="isReverbConnected ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-stone-50 text-stone-600 border-stone-300'"
            >
              <span class="w-2 h-2 rounded-full" :class="isReverbConnected ? 'bg-emerald-500 animate-pulse' : 'bg-stone-400'"></span>
              <span>{{ isReverbConnected ? 'Real-Time Sync Active' : 'Connecting Reverb...' }}</span>
            </Badge>
          </div>
          <p class="text-xs text-[#6e6e73] mt-1">
            Store kitchen display & dispatch management. Incoming customer checkouts update in real time.
          </p>
        </div>

        <!-- Toolbar: Audio Toggle, Chime Preview, Layout Toggle, Search -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <!-- View Toggle (Board vs Grid) -->
          <div class="bg-[#f3efe7] p-1 rounded-full flex items-center border border-[#e0d9cc]">
            <button
              type="button"
              @click="setViewMode('board')"
              class="px-3 py-1 text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all cursor-pointer"
              :class="viewMode === 'board' ? 'bg-white text-stone-900 shadow-xs' : 'text-stone-600 hover:text-stone-900'"
              title="Pipeline Board View"
            >
              <Columns3 class="w-3.5 h-3.5" />
              <span>Board</span>
            </button>
            <button
              type="button"
              @click="setViewMode('grid')"
              class="px-3 py-1 text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all cursor-pointer"
              :class="viewMode === 'grid' ? 'bg-white text-stone-900 shadow-xs' : 'text-stone-600 hover:text-stone-900'"
              title="Cards Grid View"
            >
              <LayoutGrid class="w-3.5 h-3.5" />
              <span>Grid</span>
            </button>
          </div>

          <!-- Audio Controls -->
          <Button 
            type="button" 
            variant="outline" 
            size="sm"
            @click="playOrderChime"
            class="h-9 px-3 rounded-full text-xs font-semibold text-stone-700 bg-[#fbf9f5] hover:bg-stone-100 border-[#dfd6c8] gap-1.5 shadow-2xs cursor-pointer"
            title="Preview kitchen chime"
          >
            <Sparkles class="w-3.5 h-3.5 text-amber-600" />
            <span class="hidden sm:inline">Chime Test</span>
          </Button>

          <Button 
            type="button" 
            variant="outline" 
            size="sm"
            @click="toggleSound"
            :class="isSoundEnabled ? 'text-amber-900 bg-amber-50 border-amber-300' : 'text-stone-500 bg-stone-50 border-stone-300'"
            class="h-9 px-3 rounded-full text-xs font-semibold gap-1.5 shadow-2xs cursor-pointer"
            title="Toggle audio alert"
          >
            <Volume2 v-if="isSoundEnabled" class="w-4 h-4 text-amber-600" />
            <VolumeX v-else class="w-4 h-4 text-stone-400" />
            <span>{{ isSoundEnabled ? 'Sound On' : 'Muted' }}</span>
          </Button>

          <!-- Search Input -->
          <div class="relative w-full sm:w-60">
            <Search class="w-3.5 h-3.5 text-stone-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <Input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search order, customer, phone..." 
              class="h-9 pl-8 pr-7 text-xs bg-[#faf8f5] border-[#dfd6c8] rounded-full focus-visible:bg-white"
            />
            <button 
              v-if="searchQuery" 
              type="button" 
              @click="clearSearch"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 p-0.5 cursor-pointer"
            >
              <RotateCcw class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>

      <!-- METRIC CARDS (KPIs) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3">
        
        <!-- Total Today -->
        <Card class="bg-white border-[#e0d9cc] rounded-xl shadow-2xs">
          <CardContent class="p-3 sm:p-3.5">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider flex items-center justify-between">
              <span>Orders Today</span>
              <ShoppingBag class="w-3.5 h-3.5 text-stone-400" />
            </div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] mt-1">
              {{ liveStats.total_today }}
            </div>
          </CardContent>
        </Card>

        <!-- New / Confirmed -->
        <Card class="bg-amber-50/40 border-amber-200/80 rounded-xl shadow-2xs">
          <CardContent class="p-3 sm:p-3.5">
            <div class="text-[11px] font-semibold text-amber-800 uppercase tracking-wider flex items-center justify-between">
              <span>New Orders</span>
              <Sparkles class="w-3.5 h-3.5 text-amber-600" />
            </div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-amber-900 mt-1">
              {{ liveStats.confirmed }}
            </div>
          </CardContent>
        </Card>

        <!-- In Packing -->
        <Card class="bg-blue-50/40 border-blue-200/80 rounded-xl shadow-2xs">
          <CardContent class="p-3 sm:p-3.5">
            <div class="text-[11px] font-semibold text-blue-800 uppercase tracking-wider flex items-center justify-between">
              <span>In Packing</span>
              <Package class="w-3.5 h-3.5 text-blue-600" />
            </div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-blue-900 mt-1">
              {{ liveStats.packing }}
            </div>
          </CardContent>
        </Card>

        <!-- Ready for Curbside / Driver -->
        <Card class="bg-emerald-50/40 border-emerald-200/80 rounded-xl shadow-2xs">
          <CardContent class="p-3 sm:p-3.5">
            <div class="text-[11px] font-semibold text-emerald-800 uppercase tracking-wider flex items-center justify-between">
              <span>Ready for Dispatch</span>
              <Truck class="w-3.5 h-3.5 text-emerald-600" />
            </div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-emerald-900 mt-1">
              {{ liveStats.ready_for_pickup }}
            </div>
          </CardContent>
        </Card>

        <!-- Today's Revenue -->
        <Card class="bg-white border-[#e0d9cc] rounded-xl shadow-2xs col-span-2 sm:col-span-1">
          <CardContent class="p-3 sm:p-3.5">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider flex items-center justify-between">
              <span>Today's Sales</span>
              <DollarSign class="w-3.5 h-3.5 text-stone-400" />
            </div>
            <div class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] mt-1">
              ${{ Number(liveStats.revenue_today || 0).toFixed(2) }}
            </div>
          </CardContent>
        </Card>

      </div>

      <!-- FULFILLMENT & STAGE FILTERS -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 bg-white p-2.5 rounded-xl border border-[#e0d9cc]">
        <!-- Fulfillment Mode Filters -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-xs font-semibold text-stone-500 mr-1 flex items-center gap-1">
            <Filter class="w-3 h-3" />
            <span>Fulfillment:</span>
          </span>
          <button
            type="button"
            @click="fulfillmentFilter = 'all'"
            class="px-3 py-1 text-xs font-semibold rounded-full border transition-all cursor-pointer"
            :class="fulfillmentFilter === 'all' ? 'bg-[#1a1a1a] text-white border-stone-900' : 'bg-stone-50 hover:bg-stone-100 text-stone-700 border-stone-200'"
          >
            All Types ({{ ordersList.length }})
          </button>
          <button
            type="button"
            @click="fulfillmentFilter = 'Store Pickup'"
            class="px-3 py-1 text-xs font-semibold rounded-full border transition-all cursor-pointer flex items-center gap-1.5"
            :class="fulfillmentFilter === 'Store Pickup' ? 'bg-[#a47a3c] text-white border-[#8d662e]' : 'bg-stone-50 hover:bg-stone-100 text-stone-700 border-stone-200'"
          >
            <ShoppingBag class="w-3 h-3" />
            <span>Store Pickup</span>
          </button>
          <button
            type="button"
            @click="fulfillmentFilter = 'Home Delivery'"
            class="px-3 py-1 text-xs font-semibold rounded-full border transition-all cursor-pointer flex items-center gap-1.5"
            :class="fulfillmentFilter === 'Home Delivery' ? 'bg-blue-700 text-white border-blue-800' : 'bg-stone-50 hover:bg-stone-100 text-stone-700 border-stone-200'"
          >
            <Truck class="w-3 h-3" />
            <span>Home Delivery</span>
          </button>
        </div>

        <!-- Stage Filter Tabs (Active in Grid View) -->
        <div v-if="viewMode === 'grid'" class="flex items-center gap-1 overflow-x-auto">
          <Tabs :model-value="activeTab" @update:model-value="onTabSelect" class="w-auto">
            <TabsList class="p-1 rounded-full bg-[#f3efe7] border border-[#e0d9cc]">
              <TabsTrigger value="all" class="text-xs font-semibold px-3 py-1 rounded-full">All</TabsTrigger>
              <TabsTrigger value="confirmed" class="text-xs font-semibold px-3 py-1 rounded-full">New ({{ liveStats.confirmed }})</TabsTrigger>
              <TabsTrigger value="packing" class="text-xs font-semibold px-3 py-1 rounded-full">Packing ({{ liveStats.packing }})</TabsTrigger>
              <TabsTrigger value="ready_for_pickup" class="text-xs font-semibold px-3 py-1 rounded-full">Ready ({{ liveStats.ready_for_pickup }})</TabsTrigger>
              <TabsTrigger value="completed" class="text-xs font-semibold px-3 py-1 rounded-full">Fulfilled ({{ liveStats.completed_today }})</TabsTrigger>
            </TabsList>
          </Tabs>
        </div>
      </div>

      <!-- VIEW 1: KANBAN PIPELINE BOARD (KDS MODE) -->
      <div v-if="viewMode === 'board'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
        <div 
          v-for="col in boardColumns" 
          :key="col.id"
          class="bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc] p-2.5 sm:p-3 flex flex-col gap-2.5 min-h-[380px]"
        >
          <!-- Column Header -->
          <div class="p-2 sm:p-2.5 rounded-xl bg-white border border-[#e0d9cc] flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
              <div 
                class="w-7 h-7 rounded-xl flex items-center justify-center"
                :class="{
                  'bg-amber-100 text-amber-800': col.color === 'amber',
                  'bg-blue-100 text-blue-800': col.color === 'blue',
                  'bg-emerald-100 text-emerald-800': col.color === 'emerald',
                  'bg-stone-100 text-stone-700': col.color === 'stone',
                }"
              >
                <component :is="col.icon" class="w-4 h-4" />
              </div>
              <div>
                <h3 class="text-xs font-bold text-stone-900">{{ col.title }}</h3>
                <p class="text-[10px] text-stone-500">{{ col.subtitle }}</p>
              </div>
            </div>
            <span class="w-6 h-6 rounded-full bg-[#f3efe7] text-stone-800 font-mono text-xs font-bold flex items-center justify-center">
              {{ col.orders.length }}
            </span>
          </div>

          <!-- Cards in this column -->
          <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[calc(100vh-300px)] pr-0.5">
            <Card 
              v-for="order in col.orders" 
              :key="order.id"
              class="bg-white border-[#e0d9cc] rounded-xl shadow-2xs hover:shadow-md transition-all border overflow-hidden flex flex-col justify-between"
              :class="order.isUnacknowledged ? 'ring-2 ring-amber-500 shadow-lg' : ''"
            >
              <div class="p-3 space-y-2">
                <!-- Top metadata row -->
                <div class="flex items-start justify-between gap-1">
                  <div>
                    <span class="font-mono text-xs font-bold text-stone-900 block">
                      {{ order.order_number }}
                    </span>
                    <span class="text-[11px] text-stone-500 flex items-center gap-1 mt-0.5">
                      <Clock class="w-3 h-3 text-stone-400" />
                      {{ order.created_at_human }}
                    </span>
                  </div>
                  <Badge 
                    variant="outline" 
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-flex items-center gap-1 shrink-0"
                    :class="order.fulfillment_type === 'Home Delivery' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-amber-50 text-amber-900 border-amber-200'"
                  >
                    <Truck v-if="order.fulfillment_type === 'Home Delivery'" class="w-3 h-3 text-blue-600 stroke-[2.2]" />
                    <ShoppingBag v-else class="w-3 h-3 text-amber-700 stroke-[2.2]" />
                    <span>{{ order.fulfillment_type === 'Home Delivery' ? 'Delivery' : 'Pickup' }}</span>
                  </Badge>
                </div>

                <!-- Customer info & slot -->
                <div class="p-2.5 rounded-xl bg-[#faf8f5] border border-stone-200/70 text-xs space-y-1">
                  <div class="font-semibold text-stone-900 truncate">{{ order.customer_name }}</div>
                  
                  <!-- Slot / Address details -->
                  <div v-if="order.fulfillment_type === 'Home Delivery' && order.delivery_address" class="text-[11px] text-stone-600 flex items-start gap-1">
                    <MapPin class="w-3 h-3 text-blue-600 shrink-0 mt-0.5" />
                    <span class="truncate">{{ order.delivery_address }}</span>
                  </div>
                  <div v-else class="text-[11px] text-stone-600 flex items-center gap-1">
                    <Calendar class="w-3 h-3 text-amber-600 shrink-0" />
                    <span class="truncate">{{ order.pickup_slot }}</span>
                  </div>

                  <div v-if="order.customer_phone" class="text-[11px] text-stone-500 pt-0.5">
                    <a :href="'tel:' + order.customer_phone" class="text-[#a47a3c] hover:underline flex items-center gap-1">
                      <Phone class="w-3 h-3" />
                      <span>{{ order.customer_phone }}</span>
                    </a>
                  </div>
                </div>

                <!-- Items overview -->
                <div class="space-y-1 text-xs">
                  <div class="text-[10px] uppercase font-bold tracking-wider text-stone-400 flex items-center justify-between">
                    <span>Items ({{ order.items_count }})</span>
                    <span class="font-serif font-bold text-stone-900">${{ Number(order.total).toFixed(2) }}</span>
                  </div>
                  <div 
                    v-for="item in order.items.slice(0, 2)" 
                    :key="item.id"
                    class="flex items-center justify-between text-xs text-stone-800 truncate"
                  >
                    <span class="truncate">
                      <span class="font-bold text-stone-900 mr-1">{{ item.quantity }}x</span>
                      {{ item.name }}
                    </span>
                    <span class="text-stone-400 font-mono text-[11px] shrink-0">${{ Number(item.total_price).toFixed(2) }}</span>
                  </div>
                  <div v-if="order.items.length > 2" class="text-[11px] text-[#a47a3c] font-medium">
                    +{{ order.items.length - 2 }} more...
                  </div>
                </div>

                <!-- Customer Note -->
                <div v-if="order.notes" class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900 font-medium">
                  Note: {{ order.notes }}
                </div>
              </div>

              <!-- Card Action Bar -->
              <div class="p-2.5 bg-[#fbf9f5] border-t border-stone-200/80 flex items-center gap-1.5">
                <Button 
                  type="button" 
                  variant="outline" 
                  size="sm"
                  @click="openOrderDetails(order)"
                  class="h-8 px-2.5 rounded-full text-xs font-semibold bg-white border-stone-300 hover:bg-stone-50 cursor-pointer"
                  title="View full details"
                >
                  <Eye class="w-3.5 h-3.5" />
                </Button>

                <Button 
                  type="button" 
                  variant="outline" 
                  size="sm"
                  @click="printOrderTicket(order)"
                  class="h-8 px-2.5 rounded-full text-xs font-semibold bg-white border-stone-300 hover:bg-stone-50 cursor-pointer"
                  title="Print Packing Ticket"
                >
                  <Printer class="w-3.5 h-3.5" />
                </Button>

                <!-- Status Progression Button -->
                <div v-if="getNextStatusInfo(order)" class="flex-1">
                  <Button 
                    type="button"
                    size="sm"
                    :disabled="updatingOrderId === order.id"
                    @click="updateOrderStatus(order, getNextStatusInfo(order).nextStatus)"
                    :class="['w-full h-8 px-3 rounded-full text-xs font-bold shadow-xs flex items-center justify-center gap-1 cursor-pointer disabled:opacity-50', getNextStatusInfo(order).badgeClass]"
                  >
                    <Loader2 v-if="updatingOrderId === order.id" class="w-3.5 h-3.5 animate-spin" />
                    <component :is="getNextStatusInfo(order).icon" v-else class="w-3.5 h-3.5" />
                    <span class="truncate">{{ getNextStatusInfo(order).label }}</span>
                  </Button>
                </div>

                <div v-else-if="order.status === 'completed'" class="flex-1 text-center text-xs font-bold text-emerald-700 py-1 flex items-center justify-center gap-1">
                  <Check class="w-3.5 h-3.5 stroke-[2.5]" />
                  <span>Fulfilled</span>
                </div>
              </div>
            </Card>

            <!-- Column Empty State -->
            <div v-if="col.orders.length === 0" class="p-5 text-center text-stone-400 text-xs rounded-xl border border-dashed border-stone-300">
              No orders in this stage
            </div>
          </div>
        </div>
      </div>

      <!-- VIEW 2: DETAILED CARD GRID -->
      <div v-else-if="filteredOrders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Card 
          v-for="order in filteredOrders" 
          :key="order.id"
          class="bg-white border-[#e0d9cc] rounded-2xl overflow-hidden shadow-2xs hover:border-[#a47a3c]/60 transition-all flex flex-col justify-between"
          :class="order.isUnacknowledged ? 'ring-2 ring-amber-500 shadow-xl' : ''"
        >
          <!-- Header -->
          <div class="p-3.5 bg-[#fbf9f5] border-b border-[#e0d9cc]/70">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="font-mono text-sm font-bold text-[#1d1d1f]">
                    {{ order.order_number }}
                  </span>
                  <Badge 
                    variant="outline" 
                    :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', getStatusBadge(order.status, order.fulfillment_type).class]"
                  >
                    {{ getStatusBadge(order.status, order.fulfillment_type).label }}
                  </Badge>
                  <Badge 
                    variant="outline"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-flex items-center gap-1 shrink-0"
                    :class="order.fulfillment_type === 'Home Delivery' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-amber-50 text-amber-900 border-amber-200'"
                  >
                    <Truck v-if="order.fulfillment_type === 'Home Delivery'" class="w-3 h-3 text-blue-600 stroke-[2.2]" />
                    <ShoppingBag v-else class="w-3 h-3 text-amber-700 stroke-[2.2]" />
                    <span>{{ order.fulfillment_type === 'Home Delivery' ? 'Delivery' : 'Pickup' }}</span>
                  </Badge>
                </div>
                
                <div class="text-[11px] text-[#6e6e73] flex items-center gap-1.5 mt-1.5 flex-wrap">
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

            <!-- Customer & Delivery address row -->
            <div class="mt-3 pt-2.5 border-t border-[#e0d9cc]/60 flex items-center justify-between text-xs text-[#1d1d1f]">
              <span class="font-semibold truncate max-w-[150px]">{{ order.customer_name }}</span>
              <a 
                v-if="order.customer_phone" 
                :href="'tel:' + order.customer_phone" 
                class="text-[#a47a3c] hover:underline flex items-center gap-1 text-[11px] font-medium"
              >
                <Phone class="w-3 h-3" />
                <span>{{ order.customer_phone }}</span>
              </a>
            </div>

            <div v-if="order.fulfillment_type === 'Home Delivery' && order.delivery_address" class="mt-1.5 text-[11px] text-stone-600 flex items-start gap-1">
              <MapPin class="w-3 h-3 text-blue-600 shrink-0 mt-0.5" />
              <span class="truncate">{{ order.delivery_address }}</span>
            </div>
          </div>

          <!-- Items list -->
          <CardContent class="p-4 flex-1">
            <div class="space-y-2.5">
              <div class="text-[11px] font-bold uppercase tracking-wider text-[#86868b] flex items-center justify-between">
                <span>Items ({{ order.items_count }})</span>
                <span class="font-normal font-sans text-stone-500">{{ order.fulfillment_type }}</span>
              </div>

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

              <div v-if="order.notes" class="mt-2.5 p-2 rounded-xl bg-amber-50/70 border border-amber-200 text-[11px] text-amber-900 font-medium">
                Note: {{ order.notes }}
              </div>
            </div>
          </CardContent>

          <!-- Footer Actions -->
          <div class="p-3 bg-[#faf8f5] border-t border-[#e0d9cc]/60 flex items-center gap-2">
            <Button 
              type="button" 
              variant="outline" 
              size="sm"
              @click="openOrderDetails(order)"
              class="h-9 px-3 rounded-full text-xs font-semibold text-[#1d1d1f] border-[#dfd6c8] bg-white hover:bg-[#f3efe7] shadow-2xs gap-1 cursor-pointer"
            >
              <Eye class="w-3.5 h-3.5" />
              <span>Details</span>
            </Button>

            <Button 
              type="button" 
              variant="outline" 
              size="sm"
              @click="printOrderTicket(order)"
              class="h-9 px-2.5 rounded-full text-xs font-semibold text-stone-700 border-stone-300 bg-white hover:bg-stone-50 cursor-pointer"
              title="Print Packing Slip"
            >
              <Printer class="w-3.5 h-3.5" />
            </Button>

            <!-- 1-Click Status Progression Pipeline -->
            <div v-if="getNextStatusInfo(order)" class="flex-1">
              <Button 
                type="button"
                size="sm"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order, getNextStatusInfo(order).nextStatus)"
                :class="['w-full h-9 rounded-full text-xs font-bold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50', getNextStatusInfo(order).badgeClass]"
              >
                <Loader2 v-if="updatingOrderId === order.id" class="w-3.5 h-3.5 animate-spin" />
                <component :is="getNextStatusInfo(order).icon" v-else class="w-3.5 h-3.5" />
                <span>{{ getNextStatusInfo(order).label }}</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </Button>
            </div>

            <div v-else-if="order.status === 'completed'" class="flex-1 text-center text-xs font-bold text-emerald-700 py-1 flex items-center justify-center gap-1">
              <Check class="w-3.5 h-3.5 stroke-[2.5]" />
              <span>Fulfilled</span>
            </div>

            <div v-else-if="order.status === 'cancelled'" class="flex-1 text-center text-xs font-medium text-rose-700 py-1">
              Cancelled
            </div>
          </div>
        </Card>
      </div>

      <!-- EMPTY STATE -->
      <div v-else class="p-16 bg-white rounded-3xl border border-[#e0d9cc] text-center space-y-3 shadow-xs">
        <div class="w-14 h-14 rounded-full bg-[#f3efe7] text-[#a47a3c] flex items-center justify-center mx-auto shadow-inner">
          <ShoppingBag class="w-7 h-7" />
        </div>
        <h3 class="text-lg font-serif font-medium text-[#1d1d1f]">
          No live orders matching criteria
        </h3>
        <p class="text-xs text-[#6e6e73] max-w-sm mx-auto">
          {{ searchQuery ? 'Try adjusting your search query or fulfillment filter.' : 'Live orders will appear here automatically with instant audio alerts as customers checkout.' }}
        </p>
      </div>

      <!-- ORDER DETAILS DIALOG MODAL -->
      <Dialog :open="isDetailsModalOpen" @update:open="isDetailsModalOpen = $event">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl">
          <DialogHeader v-if="selectedOrderForModal">
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <div class="flex items-center gap-2.5">
                <DialogTitle class="text-xl font-mono font-bold">{{ selectedOrderForModal.order_number }}</DialogTitle>
                <Badge 
                  variant="outline" 
                  :class="['text-xs font-bold px-2.5 py-0.5 rounded-full border', getStatusBadge(selectedOrderForModal.status, selectedOrderForModal.fulfillment_type).class]"
                >
                  {{ getStatusBadge(selectedOrderForModal.status, selectedOrderForModal.fulfillment_type).label }}
                </Badge>
              </div>

              <div class="flex items-center gap-2">
                <Button 
                  type="button" 
                  variant="outline" 
                  size="sm"
                  @click="printOrderTicket(selectedOrderForModal)"
                  class="rounded-full text-xs font-semibold gap-1.5"
                >
                  <Printer class="w-3.5 h-3.5" />
                  <span>Print Slip</span>
                </Button>
                
                <Button 
                  v-if="selectedOrderForModal.status !== 'cancelled' && selectedOrderForModal.status !== 'completed'"
                  type="button" 
                  variant="outline" 
                  size="sm"
                  @click="confirmCancelOrder(selectedOrderForModal)"
                  class="rounded-full text-xs font-semibold text-rose-700 border-rose-300 hover:bg-rose-50 gap-1.5"
                >
                  <Ban class="w-3.5 h-3.5" />
                  <span>Cancel & Refund</span>
                </Button>
              </div>
            </div>

            <DialogDescription class="text-xs text-stone-500 pt-1">
              Placed {{ selectedOrderForModal.created_at_human }} · Payment via {{ selectedOrderForModal.payment_method }}
            </DialogDescription>
          </DialogHeader>

          <div v-if="selectedOrderForModal" class="space-y-4 py-2 text-xs">
            
            <!-- Customer & Fulfillment Card -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Customer Details -->
              <div class="bg-[#fbf9f5] border border-[#e0d9cc] rounded-2xl p-3.5 space-y-1.5">
                <div class="text-[10px] uppercase font-bold tracking-wider text-stone-400">Customer</div>
                <div class="font-bold text-sm text-[#1d1d1f]">{{ selectedOrderForModal.customer_name }}</div>
                <div class="text-stone-600 flex flex-col gap-1 text-xs">
                  <span class="flex items-center gap-1.5">
                    <Mail class="w-3 h-3 text-stone-400" />
                    {{ selectedOrderForModal.customer_email }}
                  </span>
                  <a 
                    v-if="selectedOrderForModal.customer_phone" 
                    :href="'tel:' + selectedOrderForModal.customer_phone"
                    class="flex items-center gap-1.5 text-[#a47a3c] hover:underline"
                  >
                    <Phone class="w-3 h-3" />
                    {{ selectedOrderForModal.customer_phone }}
                  </a>
                </div>
              </div>

              <!-- Fulfillment Details -->
              <div class="bg-[#fbf9f5] border border-[#e0d9cc] rounded-2xl p-3.5 space-y-1.5">
                <div class="text-[10px] uppercase font-bold tracking-wider text-stone-400">Fulfillment</div>
                <div class="font-bold text-sm text-[#1d1d1f] flex items-center gap-1.5">
                  <Truck v-if="selectedOrderForModal.fulfillment_type === 'Home Delivery'" class="w-4 h-4 text-blue-600" />
                  <ShoppingBag v-else class="w-4 h-4 text-amber-600" />
                  {{ selectedOrderForModal.fulfillment_type }}
                </div>
                
                <div v-if="selectedOrderForModal.fulfillment_type === 'Home Delivery' && selectedOrderForModal.delivery_address" class="text-stone-600 text-xs">
                  <div class="font-semibold text-stone-900">Delivery Street Address:</div>
                  <div>{{ selectedOrderForModal.delivery_address }}</div>
                </div>
                <div v-else class="text-stone-600 text-xs">
                  <div class="font-semibold text-stone-900">Pickup Window:</div>
                  <div>{{ selectedOrderForModal.pickup_slot }}</div>
                  <div class="text-[11px] text-stone-500">{{ selectedOrderForModal.pickup_location }}</div>
                </div>
              </div>
            </div>

            <!-- Customer Notes if any -->
            <div v-if="selectedOrderForModal.notes" class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900">
              <span class="font-bold">Customer Instructions:</span> {{ selectedOrderForModal.notes }}
            </div>

            <!-- Items Table -->
            <div>
              <div class="font-bold text-xs uppercase tracking-wider text-stone-500 mb-2 flex items-center justify-between">
                <span>Order Items</span>
                <span>{{ selectedOrderForModal.items_count }} items</span>
              </div>
              <div class="border border-[#e0d9cc] rounded-2xl divide-y divide-[#e0d9cc] overflow-hidden">
                <div 
                  v-for="item in selectedOrderForModal.items" 
                  :key="item.id"
                  class="p-3 flex items-center justify-between bg-white text-xs gap-3"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="font-bold w-7 h-7 rounded-lg bg-[#f3efe7] text-stone-900 flex items-center justify-center text-xs shrink-0 border border-stone-200">
                      {{ item.quantity }}x
                    </span>
                    <div class="truncate">
                      <div class="font-bold text-stone-900 truncate">{{ item.name }}</div>
                      <div class="text-[11px] text-stone-500">{{ item.size || 'Standard package' }}</div>
                    </div>
                  </div>
                  <div class="font-mono font-medium text-right text-stone-900 shrink-0">
                    ${{ Number(item.total_price).toFixed(2) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Financials Breakdown -->
            <div class="bg-[#faf8f5] p-3.5 rounded-2xl border border-stone-200 space-y-1.5 text-xs text-stone-600">
              <div class="flex justify-between">
                <span>Subtotal:</span>
                <span class="font-mono text-stone-900">${{ Number(selectedOrderForModal.subtotal).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>{{ selectedOrderForModal.fulfillment_type === 'Home Delivery' ? 'Home Delivery Fee' : 'Store Pickup (Curbside)' }}:</span>
                <span class="font-mono" :class="Number(selectedOrderForModal.delivery_fee || 0) > 0 ? 'text-stone-900' : 'text-emerald-700 font-bold'">
                  {{ Number(selectedOrderForModal.delivery_fee || 0) > 0 ? `$${Number(selectedOrderForModal.delivery_fee).toFixed(2)}` : 'FREE' }}
                </span>
              </div>
              <div v-if="Number(selectedOrderForModal.discount || 0) > 0" class="flex justify-between text-emerald-700">
                <span>Discount:</span>
                <span class="font-mono">-${{ Number(selectedOrderForModal.discount).toFixed(2) }}</span>
              </div>
              <Separator class="my-1.5" />
              <div class="flex justify-between text-sm font-bold text-stone-950">
                <span>Total Charged:</span>
                <span class="font-serif text-base text-[#a47a3c]">${{ Number(selectedOrderForModal.total).toFixed(2) }}</span>
              </div>
            </div>

          </div>

          <DialogFooter v-if="selectedOrderForModal" class="flex items-center justify-between sm:justify-between w-full pt-2">
            <Button 
              type="button" 
              variant="outline" 
              @click="isDetailsModalOpen = false"
              class="rounded-full text-xs"
            >
              Close
            </Button>

            <!-- Quick advance action from modal -->
            <div v-if="getNextStatusInfo(selectedOrderForModal)">
              <Button 
                type="button"
                :disabled="updatingOrderId === selectedOrderForModal.id"
                @click="updateOrderStatus(selectedOrderForModal, getNextStatusInfo(selectedOrderForModal).nextStatus); isDetailsModalOpen = false"
                :class="['h-9 px-5 rounded-full text-xs font-bold shadow-xs gap-1.5 cursor-pointer disabled:opacity-50', getNextStatusInfo(selectedOrderForModal).badgeClass]"
              >
                <component :is="getNextStatusInfo(selectedOrderForModal).icon" class="w-3.5 h-3.5" />
                <span>{{ getNextStatusInfo(selectedOrderForModal).label }}</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- THERMAL PACKING TICKET PRINT MODAL -->
      <Dialog :open="isPrintModalOpen" @update:open="isPrintModalOpen = $event">
        <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto rounded-3xl">
          <DialogHeader v-if="orderToPrint">
            <DialogTitle class="text-base font-bold">Kitchen Packing Slip</DialogTitle>
            <DialogDescription class="text-xs">Thermal print receipt preview for store staff</DialogDescription>
          </DialogHeader>

          <!-- Printable Ticket Body -->
          <div v-if="orderToPrint" id="printable-kitchen-ticket" class="p-4 bg-white border border-dashed border-stone-300 rounded-2xl font-mono text-xs text-stone-900 space-y-3">
            <div class="text-center pb-2 border-b border-dashed border-stone-300">
              <div class="font-bold text-base tracking-wider">MASALA MART</div>
              <div class="text-[11px] text-stone-600">Kitchen & Dispatch Ticket</div>
              <div class="text-[11px] text-stone-600 font-sans mt-0.5">{{ new Date().toLocaleString() }}</div>
            </div>

            <div class="flex justify-between items-center text-sm font-bold pb-2 border-b border-stone-200">
              <span>{{ orderToPrint.order_number }}</span>
              <span class="text-xs font-sans px-2 py-0.5 bg-stone-100 rounded">
                {{ orderToPrint.fulfillment_type }}
              </span>
            </div>

            <div class="space-y-0.5 text-xs">
              <div class="font-bold">{{ orderToPrint.customer_name }}</div>
              <div>Tel: {{ orderToPrint.customer_phone || 'N/A' }}</div>
              <div v-if="orderToPrint.fulfillment_type === 'Home Delivery'">
                <strong>Address:</strong> {{ orderToPrint.delivery_address || 'N/A' }}
              </div>
              <div v-else>
                <strong>Window:</strong> {{ orderToPrint.pickup_slot }}
              </div>
            </div>

            <div class="pt-2 border-t border-dashed border-stone-300">
              <div class="font-bold uppercase text-[10px] text-stone-500 mb-1.5">Items to Pack:</div>
              <div class="space-y-1.5 divide-y divide-stone-100">
                <div 
                  v-for="item in orderToPrint.items" 
                  :key="item.id"
                  class="pt-1.5 flex justify-between items-start"
                >
                  <div>
                    <span class="font-bold text-base mr-1.5">[{{ item.quantity }}]</span>
                    <span class="font-sans font-bold">{{ item.name }}</span>
                    <div class="text-[10px] text-stone-500 font-sans pl-6">{{ item.size }}</div>
                  </div>
                  <span class="shrink-0">${{ Number(item.total_price).toFixed(2) }}</span>
                </div>
              </div>
            </div>

            <div v-if="orderToPrint.notes" class="p-2 bg-stone-50 border border-stone-200 rounded text-[11px] font-sans">
              <strong>Staff Note:</strong> {{ orderToPrint.notes }}
            </div>

            <div class="pt-2 border-t border-dashed border-stone-300 flex justify-between items-center font-bold text-sm">
              <span>Total Amount:</span>
              <span>${{ Number(orderToPrint.total).toFixed(2) }} (PAID)</span>
            </div>
          </div>

          <DialogFooter v-if="orderToPrint" class="flex gap-2">
            <Button type="button" variant="outline" @click="isPrintModalOpen = false" class="rounded-full text-xs">
              Close
            </Button>
            <Button type="button" @click="triggerPrint" class="rounded-full text-xs font-bold bg-[#1a1a1a] text-white gap-1.5">
              <Printer class="w-4 h-4" />
              <span>Print Ticket</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- CANCEL ORDER CONFIRMATION MODAL -->
      <Dialog :open="isCancelModalOpen" @update:open="isCancelModalOpen = $event">
        <DialogContent class="max-w-md rounded-3xl">
          <DialogHeader v-if="orderToCancel">
            <DialogTitle class="text-base font-bold text-rose-800 flex items-center gap-2">
              <AlertCircle class="w-5 h-5 text-rose-600" />
              <span>Cancel Order {{ orderToCancel.order_number }}?</span>
            </DialogTitle>
            <DialogDescription class="text-xs text-stone-600 pt-1">
              Cancelling this order will immediately return its reserved items back to inventory stock and issue a full automatic refund to the customer's original payment method via Stripe.
            </DialogDescription>
          </DialogHeader>

          <div v-if="orderToCancel" class="p-3 bg-rose-50 border border-rose-200 rounded-2xl text-xs space-y-1 text-rose-950">
            <div><strong>Customer:</strong> {{ orderToCancel.customer_name }}</div>
            <div><strong>Refund Amount:</strong> ${{ Number(orderToCancel.total).toFixed(2) }}</div>
            <div><strong>Items:</strong> {{ orderToCancel.items_count }} items will be restocked.</div>
          </div>

          <DialogFooter class="flex gap-2 pt-2">
            <Button 
              type="button" 
              variant="outline" 
              :disabled="isCancelling"
              @click="isCancelModalOpen = false" 
              class="rounded-full text-xs"
            >
              Keep Order
            </Button>
            <Button 
              type="button" 
              :disabled="isCancelling"
              @click="executeCancelOrder" 
              class="rounded-full text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white gap-1.5"
            >
              <Loader2 v-if="isCancelling" class="w-3.5 h-3.5 animate-spin" />
              <Ban v-else class="w-3.5 h-3.5" />
              <span>Confirm & Refund</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AdminLayout>
</template>
