<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
  Truck,
  Check,
  AlertCircle,
  Eye,
  Loader2,
  Printer,
  ArrowRight,
  Ban,
  DollarSign,
  Copy,
  Zap,
  CreditCard,
  CheckSquare,
  Square,
  X,
  RefreshCw,
  ChevronDown,
  ChevronUp,
  LayoutGrid,
  Columns3,
  List,
  ArrowUpDown,
  ExternalLink,
  MessageSquareWarning,
  Award,
  Layers,
} from 'lucide-vue-next';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
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

// Interactive Packer Checklist state (persisted in database & synced real-time via Laravel Reverb)
const packedItemsMap = ref({});

function hydratePackedItemsFromOrders(orders) {
  if (!Array.isArray(orders)) return;
  const nextMap = { ...packedItemsMap.value };
  orders.forEach((order) => {
    (order.items || []).forEach((item) => {
      const key = `${order.id}:${item.id}`;
      if (item.is_packed) {
        nextMap[key] = true;
      } else {
        delete nextMap[key];
      }
    });
  });
  packedItemsMap.value = nextMap;
}

// Reactive order list for instantaneous Reverb WebSocket updates
const ordersList = ref([...(props.orders?.data || [])]);
hydratePackedItemsFromOrders(ordersList.value);

watch(
  () => props.orders?.data,
  (newData) => {
    if (Array.isArray(newData)) {
      ordersList.value = [...newData];
      hydratePackedItemsFromOrders(newData);
    }
  }
);

const liveStats = ref({ ...props.stats });
watch(
  () => props.stats,
  (newStats) => {
    if (newStats) {
      liveStats.value = { ...newStats };
    }
  }
);

// Filters, View Mode & Sorting State
const activeStage = ref(props.filters.status || 'all');
const showActiveQueueOnly = ref(false);
const fulfillmentFilter = ref('all'); // 'all' | 'Store Pickup' | 'Home Delivery'
const timingFilter = ref('all'); // 'all' | 'asap'
const sortBy = ref('priority'); // 'priority' | 'wait_desc' | 'newest' | 'total_desc'
const viewMode = ref('cards'); // 'cards' | 'board' | 'table'
const searchQuery = ref(props.filters.search || '');
const searchInputRef = ref(null);

const isSoundEnabled = ref(true);
const isRefreshing = ref(false);
const selectedOrderForModal = ref(null);
const isDetailsModalOpen = ref(false);
const isPrintModalOpen = ref(false);
const orderToPrint = ref(null);
const isCancelModalOpen = ref(false);
const orderToCancel = ref(null);
const isCancelling = ref(false);
const isItemRefundModalOpen = ref(false);
const orderForItemRefund = ref(null);
const itemToRefund = ref(null);
const refundQtyInput = ref(1);
const zeroProductStockInput = ref(true);
const refundReasonInput = ref('Out of stock during packing');
const isRefundingItem = ref(false);
const isReverbConnected = ref(false);
const updatingOrderId = ref(null);

// Track which cards have expanded item lists (> 4 items)
const expandedOrderCards = ref({});
function toggleExpandCard(orderId) {
  expandedOrderCards.value[orderId] = !expandedOrderCards.value[orderId];
}

// Non-blocking Toast notification state
const toastNotification = ref(null);
let toastTimeoutId = null;

function showToast(message, type = 'success') {
  if (toastTimeoutId) clearTimeout(toastTimeoutId);
  toastNotification.value = { message, type };
  toastTimeoutId = setTimeout(() => {
    toastNotification.value = null;
  }, 4000);
}

// Copy helper
async function copyText(text, label = 'Copied') {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    showToast(`${label}: ${text}`, 'info');
  } catch {
    showToast('Could not copy to clipboard', 'error');
  }
}

// View mode switcher with persistence
function setViewMode(mode) {
  viewMode.value = mode;
  try {
    localStorage.setItem('masala_kds_view_mode', mode);
  } catch {
    // ignore
  }
}

// Per-item refund & active quantity helpers
function isItemFullyRefunded(item) {
  return Number(item?.refunded_quantity || 0) >= Number(item?.quantity || 1);
}

function isItemPartiallyRefunded(item) {
  const refunded = Number(item?.refunded_quantity || 0);
  return refunded > 0 && refunded < Number(item?.quantity || 1);
}

function activeItemQuantity(item) {
  return Math.max(0, Number(item?.quantity || 1) - Number(item?.refunded_quantity || 0));
}

function packableItemsForOrder(order) {
  if (!order?.items?.length) return [];
  return order.items.filter((item) => !isItemFullyRefunded(item));
}

function effectiveOrderTotal(order) {
  return Math.max(0, Number(order?.total || 0) - Number(order?.refunded_total || 0));
}

function isOrderFullyRefunded(order) {
  return effectiveOrderTotal(order) <= 0 && Number(order?.refunded_total || 0) > 0;
}

function displayOrderTotal(order) {
  return isOrderFullyRefunded(order) ? Number(order?.total || 0) : effectiveOrderTotal(order);
}

// Item packing checklist helpers (DB-backed + real-time multi-packer sync)
function isItemPacked(orderId, itemId) {
  return Boolean(packedItemsMap.value[`${orderId}:${itemId}`]);
}

async function toggleItemPacked(orderId, itemId) {
  const targetOrder = ordersList.value.find((o) => o.id === orderId);
  const targetItem = targetOrder?.items?.find((i) => i.id === itemId);
  if (targetItem && isItemFullyRefunded(targetItem)) {
    return;
  }

  const key = `${orderId}:${itemId}`;
  const nextPacked = !packedItemsMap.value[key];

  if (nextPacked) {
    packedItemsMap.value[key] = true;
  } else {
    delete packedItemsMap.value[key];
  }
  packedItemsMap.value = { ...packedItemsMap.value };

  if (targetItem) {
    targetItem.is_packed = nextPacked;
  }

  try {
    await axios.patch(`/admin/orders/${orderId}/items/packed`, {
      item_id: itemId,
      is_packed: nextPacked,
    });
  } catch (err) {
    console.error('Failed to update item packed state:', err);
    if (nextPacked) {
      delete packedItemsMap.value[key];
    } else {
      packedItemsMap.value[key] = true;
    }
    packedItemsMap.value = { ...packedItemsMap.value };
    if (targetItem) {
      targetItem.is_packed = !nextPacked;
    }
    showToast('Could not save item packing state. Please try again.', 'error');
  }
}

async function markAllItemsPacked(order) {
  const packable = packableItemsForOrder(order);
  if (!packable.length) return;
  const allPacked = packedCountForOrder(order) === packable.length;
  const nextPacked = !allPacked;
  const previousMap = { ...packedItemsMap.value };

  packable.forEach((item) => {
    const key = `${order.id}:${item.id}`;
    item.is_packed = nextPacked;
    if (nextPacked) {
      packedItemsMap.value[key] = true;
    } else {
      delete packedItemsMap.value[key];
    }
  });
  packedItemsMap.value = { ...packedItemsMap.value };

  try {
    await axios.patch(`/admin/orders/${order.id}/items/packed`, {
      is_packed: nextPacked,
    });
  } catch (err) {
    console.error('Failed to update all items packed state:', err);
    packedItemsMap.value = previousMap;
    packable.forEach((item) => {
      item.is_packed = Boolean(previousMap[`${order.id}:${item.id}`]);
    });
    showToast('Could not save packing checklist. Please try again.', 'error');
  }
}

function packedCountForOrder(order) {
  const packable = packableItemsForOrder(order);
  if (!packable.length) return 0;
  return packable.filter((item) => isItemPacked(order.id, item.id)).length;
}

function packingProgressPercent(order) {
  const packable = packableItemsForOrder(order);
  if (!packable.length) return 0;
  return Math.round((packedCountForOrder(order) / packable.length) * 100);
}

function totalUnitsForOrder(order) {
  if (!order?.items?.length) return order?.items_count || 0;
  const activeSum = order.items.reduce((sum, item) => sum + activeItemQuantity(item), 0);
  if (activeSum > 0) return activeSum;
  return order.items.reduce((sum, item) => sum + Number(item.quantity || 1), 0);
}

// Helper: detect ASAP vs Scheduled
function isAsapOrder(order) {
  if (order.fulfillment_type === 'Home Delivery') return false;
  return String(order.pickup_slot || '').toUpperCase().includes('ASAP');
}

// Helper: elapsed minutes & urgency for active ASAP orders (no background timer loop)
function getElapsedMinutes(order) {
  if (!order?.created_at) return null;
  const createdMs = new Date(order.created_at).getTime();
  if (Number.isNaN(createdMs)) return null;
  return Math.max(0, Math.floor((Date.now() - createdMs) / 60000));
}

function getLiveRelativeTime(order) {
  if (order?.created_at_human) return order.created_at_human;
  const mins = getElapsedMinutes(order);
  if (mins === null) return 'Recently';
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) {
    const remMins = mins % 60;
    return remMins > 0 ? `${hrs}h ${remMins}m ago` : `${hrs}h ago`;
  }
  return `${Math.floor(hrs / 24)}d ago`;
}

function getOrderUrgency(order) {
  if (!order?.created_at || ['completed', 'cancelled'].includes(order.status)) {
    return null;
  }
  if (!isAsapOrder(order)) {
    return null;
  }
  const diffMins = getElapsedMinutes(order);
  if (diffMins === null || diffMins >= 60) return null;
  if (diffMins >= 25) {
    return {
      label: `${diffMins}m wait`,
      class: 'bg-rose-50 text-rose-800 border-rose-300',
      dot: 'bg-rose-500 animate-ping',
    };
  }
  if (diffMins >= 12) {
    return {
      label: `${diffMins}m wait`,
      class: 'bg-amber-50 text-amber-900 border-amber-300',
      dot: 'bg-amber-500',
    };
  }
  return {
    label: `${diffMins}m`,
    class: 'bg-emerald-50/80 text-emerald-800 border-emerald-200',
    dot: 'bg-emerald-500',
  };
}

// Stage priority map so New Orders ('confirmed') always show first
const stagePriorityRank = {
  confirmed: 1,
  packing: 2,
  ready_for_pickup: 3,
  completed: 4,
  cancelled: 5,
};

const activeStatuses = ['confirmed', 'packing', 'ready_for_pickup'];

// Real-time kitchen chime alert state
const unacknowledgedOrders = ref([]);
const isAlarmLooping = ref(false);
let alarmIntervalId = null;
let titleFlashIntervalId = null;
let originalTitle = typeof document !== 'undefined' ? document.title : 'Live Orders — Masala Mart Admin';
let audioCtx = null;

// Base filter helper (without status column override, used for Kanban board & cards)
function matchesNonStatusFilters(order) {
  if (fulfillmentFilter.value !== 'all' && order.fulfillment_type !== fulfillmentFilter.value) {
    return false;
  }
  if (timingFilter.value === 'asap' && !isAsapOrder(order)) {
    return false;
  }
  if (searchQuery.value && searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    const matchNum = (order.order_number || '').toLowerCase().includes(q);
    const matchName = (order.customer_name || '').toLowerCase().includes(q);
    const matchPhone = (order.customer_phone || '').toLowerCase().includes(q);
    const matchEmail = (order.customer_email || '').toLowerCase().includes(q);
    const matchAddress = (order.delivery_address || '').toLowerCase().includes(q);
    const matchSlot = (order.pickup_slot || '').toLowerCase().includes(q);
    const matchNotes = (order.notes || '').toLowerCase().includes(q);
    const matchItems =
      Array.isArray(order.items) && order.items.some((i) => (i.name || '').toLowerCase().includes(q));
    return (
      matchNum || matchName || matchPhone || matchEmail || matchAddress || matchSlot || matchNotes || matchItems
    );
  }
  return true;
}

// Filtered & Sorted orders pipeline for Cards & Table
const filteredOrders = computed(() => {
  const matches = ordersList.value.filter((order) => {
    if (activeStage.value !== 'all' && order.status !== activeStage.value) {
      return false;
    }
    if (showActiveQueueOnly.value && !activeStatuses.includes(order.status)) {
      return false;
    }
    return matchesNonStatusFilters(order);
  });

  return [...matches].sort((a, b) => {
    if (sortBy.value === 'wait_desc') {
      const aActive = activeStatuses.includes(a.status) ? 0 : 1;
      const bActive = activeStatuses.includes(b.status) ? 0 : 1;
      if (aActive !== bActive) return aActive - bActive;
      const timeA = a.created_at ? new Date(a.created_at).getTime() : 0;
      const timeB = b.created_at ? new Date(b.created_at).getTime() : 0;
      return timeA - timeB;
    }
    if (sortBy.value === 'newest') {
      const timeA = a.created_at ? new Date(a.created_at).getTime() : 0;
      const timeB = b.created_at ? new Date(b.created_at).getTime() : 0;
      return timeB - timeA;
    }
    if (sortBy.value === 'total_desc') {
      return Number(b.total || 0) - Number(a.total || 0);
    }

    // Default: Priority ('confirmed' -> 'packing' -> 'ready_for_pickup' -> 'completed' -> 'cancelled', then newest)
    const rankA = stagePriorityRank[a.status] ?? 99;
    const rankB = stagePriorityRank[b.status] ?? 99;
    if (rankA !== rankB) {
      return rankA - rankB;
    }
    const timeA = a.created_at ? new Date(a.created_at).getTime() : 0;
    const timeB = b.created_at ? new Date(b.created_at).getTime() : 0;
    return timeB - timeA;
  });
});

// Kanban columns computed (groups matching orders into the 4 live workflow columns)
const kanbanColumns = computed(() => {
  const baseMatches = ordersList.value.filter((o) => matchesNonStatusFilters(o));
  const columns = [
    {
      key: 'confirmed',
      title: 'New Orders',
      subtitle: 'Needs acceptance',
      headerBg: 'bg-amber-50/90 border-amber-200',
      badgeClass: 'bg-amber-500 text-zinc-950',
      dotClass: 'bg-amber-500 animate-pulse',
      icon: Sparkles,
      orders: [],
    },
    {
      key: 'packing',
      title: 'In Packing',
      subtitle: 'Being hand-picked',
      headerBg: 'bg-blue-50/80 border-blue-200',
      badgeClass: 'bg-blue-600 text-white',
      dotClass: 'bg-blue-600',
      icon: Package,
      orders: [],
    },
    {
      key: 'ready_for_pickup',
      title: 'Ready Dispatch',
      subtitle: 'Awaiting handoff / driver',
      headerBg: 'bg-emerald-50/80 border-emerald-200',
      badgeClass: 'bg-emerald-600 text-white',
      dotClass: 'bg-emerald-600',
      icon: Truck,
      orders: [],
    },
    {
      key: 'completed',
      title: 'Fulfilled',
      subtitle: 'Completed orders',
      headerBg: 'bg-zinc-100/90 border-zinc-200',
      badgeClass: 'bg-zinc-700 text-white',
      dotClass: 'bg-zinc-500',
      icon: CheckCircle2,
      orders: [],
    },
  ];

  baseMatches.forEach((order) => {
    const col = columns.find((c) => c.key === order.status);
    if (col) {
      col.orders.push(order);
    }
  });

  return columns;
});

// Counts by fulfillment type (respecting active stage filter)
const fulfillmentCounts = computed(() => {
  const stageScoped = ordersList.value.filter((o) => {
    if (activeStage.value !== 'all' && o.status !== activeStage.value) return false;
    if (showActiveQueueOnly.value && !activeStatuses.includes(o.status)) return false;
    return true;
  });
  const all = stageScoped.length;
  const pickup = stageScoped.filter((o) => o.fulfillment_type === 'Store Pickup').length;
  const delivery = stageScoped.filter((o) => o.fulfillment_type === 'Home Delivery').length;
  const asap = stageScoped.filter((o) => isAsapOrder(o)).length;
  return { all, pickup, delivery, asap };
});

const activeQueueCount = computed(() => {
  return (
    (Number(liveStats.value.confirmed) || 0) +
    (Number(liveStats.value.packing) || 0) +
    (Number(liveStats.value.ready_for_pickup) || 0)
  );
});

const totalAllOrdersCount = computed(() => {
  return liveStats.value.total_all ?? ordersList.value.length;
});

const cancelledOrdersCount = computed(() => {
  return liveStats.value.cancelled ?? ordersList.value.filter((o) => o.status === 'cancelled').length;
});

const completedOrdersCount = computed(() => {
  return liveStats.value.completed ?? ordersList.value.filter((o) => o.status === 'completed').length;
});

const hasActiveFilters = computed(() => {
  return (
    activeStage.value !== 'all' ||
    showActiveQueueOnly.value ||
    fulfillmentFilter.value !== 'all' ||
    timingFilter.value !== 'all' ||
    sortBy.value !== 'priority' ||
    Boolean(searchQuery.value && searchQuery.value.trim())
  );
});

// Keyboard shortcut handler ('/' to focus search)
function handleGlobalKeydown(e) {
  if (
    e.key === '/' &&
    !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '') &&
    !isDetailsModalOpen.value &&
    !isPrintModalOpen.value &&
    !isCancelModalOpen.value
  ) {
    e.preventDefault();
    const inputEl = searchInputRef.value?.$el || searchInputRef.value;
    if (inputEl && typeof inputEl.focus === 'function') {
      inputEl.focus();
    } else if (inputEl?.querySelector) {
      inputEl.querySelector('input')?.focus();
    }
  }
}

const handlePusherConnected = () => {
  isReverbConnected.value = true;
};

const handlePusherDisconnected = () => {
  isReverbConnected.value = false;
};

// Load preferences from localStorage & connect WebSocket
onMounted(() => {
  const savedSound = localStorage.getItem('masala_order_sound');
  if (savedSound !== null) {
    isSoundEnabled.value = savedSound === 'true';
  }
  const savedViewMode = localStorage.getItem('masala_kds_view_mode');
  if (savedViewMode && ['cards', 'board', 'table'].includes(savedViewMode)) {
    viewMode.value = savedViewMode;
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleGlobalKeydown);
  }

  // Connect to Laravel Reverb via Echo
  if (typeof window !== 'undefined' && window.Echo) {
    try {
      const pusherConnection = window.Echo.connector?.pusher?.connection;
      if (pusherConnection) {
        pusherConnection.bind('connected', handlePusherConnected);
        pusherConnection.bind('disconnected', handlePusherDisconnected);
        if (pusherConnection.state === 'connected') {
          isReverbConnected.value = true;
        }
      }

      // Listen on private channel 'admin.orders' for new orders, status changes, and multi-packer checklist updates
      window.Echo.private('admin.orders')
        .listen('.order.placed', (payload) => {
          handleIncomingOrder(payload.order);
        })
        .listen('.order.status.updated', (payload) => {
          handleStatusUpdated(payload);
        })
        .listen('.order.items.packed', (payload) => {
          handleItemsPackedUpdated(payload);
        });
    } catch (e) {
      console.warn('Reverb Echo listener setup error:', e);
    }
  }
});

onUnmounted(() => {
  stopOrderAlarm();
  if (toastTimeoutId) clearTimeout(toastTimeoutId);
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleGlobalKeydown);
    if (window.Echo) {
      try {
        const pusherConnection = window.Echo.connector?.pusher?.connection;
        if (pusherConnection) {
          pusherConnection.unbind('connected', handlePusherConnected);
          pusherConnection.unbind('disconnected', handlePusherDisconnected);
        }
      } catch (e) {
        console.warn('Reverb Echo unbind error:', e);
      }
      window.Echo.leave('admin.orders');
    }
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

    playHarmonicTone(659.25, 0.0, 0.5, 0.25); // E5
    playHarmonicTone(987.77, 0.14, 0.6, 0.28); // B5
    playHarmonicTone(1318.51, 0.3, 0.95, 0.3); // E6
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
        document.title = toggle
          ? `(${unacknowledgedOrders.value.length}) New Order ${topOrder.order_number || ''}`
          : 'Live Order Received — Masala Mart';
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
  unacknowledgedOrders.value = unacknowledgedOrders.value.filter((o) => o.id !== order.id);
  if (unacknowledgedOrders.value.length === 0) {
    stopOrderAlarm();
  }
}

function toggleSound() {
  isSoundEnabled.value = !isSoundEnabled.value;
  localStorage.setItem('masala_order_sound', String(isSoundEnabled.value));
  if (!isSoundEnabled.value) {
    stopOrderAlarm();
    showToast('Kitchen audio alerts muted', 'info');
  } else {
    playOrderChime();
    showToast('Kitchen audio alerts enabled', 'success');
  }
}

// Select stage card to filter orders (clicking active stage again resets to 'all')
function selectStage(stage) {
  showActiveQueueOnly.value = false;
  if (stage === 'all') {
    activeStage.value = 'all';
  } else {
    activeStage.value = activeStage.value === stage ? 'all' : stage;
  }
}

function toggleActiveQueueOnly() {
  showActiveQueueOnly.value = !showActiveQueueOnly.value;
  if (showActiveQueueOnly.value && ['completed', 'cancelled'].includes(activeStage.value)) {
    activeStage.value = 'all';
  }
}

// Handle new incoming order from Laravel Reverb in real-time
function handleIncomingOrder(newOrder) {
  newOrder.isNewArrival = true;
  newOrder.isUnacknowledged = true;

  unacknowledgedOrders.value.unshift(newOrder);
  ordersList.value.unshift(newOrder);

  // Update live statistics
  liveStats.value.total_all = (liveStats.value.total_all || ordersList.value.length - 1) + 1;
  liveStats.value.total_today = (liveStats.value.total_today || 0) + 1;
  liveStats.value.confirmed = (liveStats.value.confirmed || 0) + 1;
  liveStats.value.revenue_today = (liveStats.value.revenue_today || 0) + Number(newOrder.total);

  startOrderAlarm();
}

// Local stats updater for seamless instant transition without page reload
function updateLocalStats(fromStatus, toStatus, orderTotal = 0) {
  if (fromStatus === toStatus) return;

  if (fromStatus === 'confirmed') {
    liveStats.value.confirmed = Math.max(0, (liveStats.value.confirmed || 0) - 1);
  } else if (fromStatus === 'packing') {
    liveStats.value.packing = Math.max(0, (liveStats.value.packing || 0) - 1);
  } else if (fromStatus === 'ready_for_pickup') {
    liveStats.value.ready_for_pickup = Math.max(0, (liveStats.value.ready_for_pickup || 0) - 1);
  } else if (fromStatus === 'completed') {
    liveStats.value.completed = Math.max(0, (liveStats.value.completed || 0) - 1);
    liveStats.value.completed_today = Math.max(0, (liveStats.value.completed_today || 0) - 1);
  }

  if (toStatus === 'confirmed') {
    liveStats.value.confirmed = (liveStats.value.confirmed || 0) + 1;
  } else if (toStatus === 'packing') {
    liveStats.value.packing = (liveStats.value.packing || 0) + 1;
  } else if (toStatus === 'ready_for_pickup') {
    liveStats.value.ready_for_pickup = (liveStats.value.ready_for_pickup || 0) + 1;
  } else if (toStatus === 'completed') {
    liveStats.value.completed = (liveStats.value.completed || 0) + 1;
    liveStats.value.completed_today = (liveStats.value.completed_today || 0) + 1;
  } else if (toStatus === 'cancelled') {
    liveStats.value.cancelled = (liveStats.value.cancelled || 0) + 1;
    liveStats.value.cancelled_today = (liveStats.value.cancelled_today || 0) + 1;
    liveStats.value.revenue_today = Math.max(0, Number(liveStats.value.revenue_today || 0) - Number(orderTotal || 0));
  }
}

// Handle status update broadcast from Laravel Reverb (zero reload!)
function handleStatusUpdated(payload) {
  const target = ordersList.value.find((o) => o.id === payload.order_id);
  if (target && target.status !== payload.status) {
    const prev = target.status;
    target.status = payload.status;
    updateLocalStats(prev, payload.status, target.total);
  }
}

// Handle real-time packing checklist & item refund update from another staff member via Laravel Reverb
function handleItemsPackedUpdated(payload) {
  if (!payload?.order_id) return;
  const orderId = payload.order_id;
  const packedIds = new Set((payload.packed_item_ids || []).map((id) => Number(id)));
  const payloadItemsById = new Map((payload.items || []).map((i) => [Number(i.id), i]));

  const targetOrder = ordersList.value.find((o) => o.id === orderId);
  const nextMap = { ...packedItemsMap.value };

  if (targetOrder) {
    if (typeof payload.refunded_total !== 'undefined') {
      targetOrder.refunded_total = Number(payload.refunded_total || 0);
    }
    if (typeof payload.notes !== 'undefined') {
      targetOrder.notes = payload.notes;
    }
    if (payload.status && targetOrder.status !== payload.status) {
      const prev = targetOrder.status;
      targetOrder.status = payload.status;
      updateLocalStats(prev, payload.status, effectiveOrderTotal(targetOrder));
    }
  }

  if (targetOrder?.items?.length) {
    targetOrder.items.forEach((item) => {
      const incomingItem = payloadItemsById.get(Number(item.id));
      if (incomingItem) {
        item.refunded_quantity = Number(incomingItem.refunded_quantity || 0);
        item.refunded_amount = Number(incomingItem.refunded_amount || 0);
        item.refund_reason = incomingItem.refund_reason || null;
      }
      const isPacked = packedIds.has(Number(item.id));
      item.is_packed = isPacked;
      const key = `${orderId}:${item.id}`;
      if (isPacked) {
        nextMap[key] = true;
      } else {
        delete nextMap[key];
      }
    });
  } else {
    Object.keys(nextMap).forEach((key) => {
      if (key.startsWith(`${orderId}:`)) {
        delete nextMap[key];
      }
    });
    packedIds.forEach((itemId) => {
      nextMap[`${orderId}:${itemId}`] = true;
    });
  }

  packedItemsMap.value = nextMap;
}

function resetAllFilters() {
  searchQuery.value = '';
  fulfillmentFilter.value = 'all';
  timingFilter.value = 'all';
  activeStage.value = 'all';
  showActiveQueueOnly.value = false;
  sortBy.value = 'priority';
}

function refreshOrders() {
  isRefreshing.value = true;
  router.reload({
    only: ['orders', 'stats'],
    preserveScroll: true,
    onFinish: () => {
      isRefreshing.value = false;
      showToast('Refreshed live order pipeline', 'info');
    },
  });
}

// 1-Click Status Progression via Axios (Pure SPA, zero page reloads!)
async function updateOrderStatus(order, nextStatus) {
  if (!order || order.status === nextStatus || updatingOrderId.value === order.id) return false;
  acknowledgeOrder(order);
  const previousStatus = order.status;

  order.status = nextStatus;
  updateLocalStats(previousStatus, nextStatus, effectiveOrderTotal(order));
  updatingOrderId.value = order.id;

  try {
    await axios.patch(`/admin/orders/${order.id}/status`, {
      status: nextStatus,
    });
    const statusLabel = getStatusBadge(nextStatus, order.fulfillment_type).label;
    showToast(
      nextStatus === 'cancelled'
        ? `Order ${order.order_number} cancelled, items restocked${order.stripe_payment_id ? ' & Stripe refund issued' : ''}.`
        : `Order ${order.order_number} moved to ${statusLabel}`,
      nextStatus === 'cancelled' ? 'info' : 'success'
    );
    return true;
  } catch (error) {
    order.status = previousStatus;
    updateLocalStats(nextStatus, previousStatus, effectiveOrderTotal(order));
    console.error('Failed to update order status:', error);
    const serverMessage = error.response?.data?.errors?.status?.[0] || error.response?.data?.message;
    showToast(serverMessage || 'Could not update the order status. Please try again.', 'error');
    return false;
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
    const ok = await updateOrderStatus(order, 'cancelled');
    if (ok) {
      order.refunded_total = Number(order.total || 0);
      isCancelModalOpen.value = false;
      if (selectedOrderForModal.value?.id === order.id) {
        isDetailsModalOpen.value = false;
      }
      orderToCancel.value = null;
    }
  } finally {
    isCancelling.value = false;
  }
}

// Per-Item Out-of-Stock & Partial Refund Modal Actions
const maxRefundableItemQty = computed(() => {
  if (!itemToRefund.value) return 1;
  return Math.max(1, activeItemQuantity(itemToRefund.value));
});

const estimatedItemRefundAmount = computed(() => {
  if (!itemToRefund.value || !orderForItemRefund.value) return 0;
  const remainingOrderBal = effectiveOrderTotal(orderForItemRefund.value);
  const raw = Number(itemToRefund.value.unit_price || 0) * Number(refundQtyInput.value || 1);
  return Math.min(remainingOrderBal, raw);
});

function openItemRefundModal(order, item) {
  if (!order || !item || isItemFullyRefunded(item)) return;
  acknowledgeOrder(order);
  orderForItemRefund.value = order;
  itemToRefund.value = item;
  refundQtyInput.value = activeItemQuantity(item);
  zeroProductStockInput.value = true;
  refundReasonInput.value = 'Out of stock during packing';
  isItemRefundModalOpen.value = true;
}

async function executeItemRefund() {
  if (!orderForItemRefund.value || !itemToRefund.value || isRefundingItem.value) return;
  isRefundingItem.value = true;

  const order = orderForItemRefund.value;
  const item = itemToRefund.value;
  const prevRefundedTotal = Number(order.refunded_total || 0);
  const prevStatus = order.status;

  try {
    const { data } = await axios.post(`/admin/orders/${order.id}/items/${item.id}/refund`, {
      quantity: Number(refundQtyInput.value || 1),
      zero_product_stock: Boolean(zeroProductStockInput.value),
      reason: refundReasonInput.value || 'Out of stock during packing',
    });

    if (data?.order) {
      const refundDiff = Math.max(0, Number(data.order.refunded_total || 0) - prevRefundedTotal);
      const listOrder = ordersList.value.find((o) => o.id === order.id) || order;

      const applyOrderPatch = (target) => {
        if (!target) return;
        target.refunded_total = Number(data.order.refunded_total || 0);
        target.notes = data.order.notes;
        if (Array.isArray(data.order.items)) {
          target.items = data.order.items;
        }
        if (data.order.status) {
          target.status = data.order.status;
        }
      };

      applyOrderPatch(listOrder);
      if (selectedOrderForModal.value?.id === order.id) {
        applyOrderPatch(selectedOrderForModal.value);
      }
      hydratePackedItemsFromOrders([listOrder]);

      if (data.order.status && data.order.status !== prevStatus) {
        updateLocalStats(prevStatus, data.order.status, 0);
      }

      if (refundDiff > 0) {
        liveStats.value.revenue_today = Math.max(0, Number(liveStats.value.revenue_today || 0) - refundDiff);
      }
    }

    isItemRefundModalOpen.value = false;
    itemToRefund.value = null;
    orderForItemRefund.value = null;
    showToast(data?.message || 'Item refunded and marked out of stock.', 'success');
  } catch (error) {
    console.error('Failed to refund order item:', error);
    const serverMessage =
      error.response?.data?.errors?.item?.[0] ||
      error.response?.data?.errors?.quantity?.[0] ||
      error.response?.data?.message ||
      'Could not process partial refund. Please try again.';
    showToast(serverMessage, 'error');
  } finally {
    isRefundingItem.value = false;
  }
}

// Workflow steps helper for card & modal stepper
const workflowSteps = [
  { key: 'confirmed', shortLabel: 'New', fullLabel: '1. Confirmed', stepIndex: 1 },
  { key: 'packing', shortLabel: 'Packing', fullLabel: '2. In Packing', stepIndex: 2 },
  { key: 'ready_for_pickup', shortLabel: 'Ready', fullLabel: '3. Ready', stepIndex: 3 },
  { key: 'completed', shortLabel: 'Done', fullLabel: '4. Fulfilled', stepIndex: 4 },
];

function getStepIndex(status) {
  return workflowSteps.find((s) => s.key === status)?.stepIndex || 0;
}

// Next status calculation helper based on fulfillment type
function getNextStatusInfo(order) {
  const isDelivery = order.fulfillment_type === 'Home Delivery';

  if (order.status === 'confirmed') {
    return {
      nextStatus: 'packing',
      label: 'Accept & Start Packing',
      shortLabel: 'Accept Order',
      badgeClass: 'bg-[#18181b] hover:bg-black text-white',
      icon: Package,
    };
  }

  if (order.status === 'packing') {
    const allChecked = packingProgressPercent(order) === 100;
    return {
      nextStatus: 'ready_for_pickup',
      label: isDelivery ? 'Mark Ready for Driver' : 'Mark Ready for Pickup',
      shortLabel: isDelivery ? 'Ready for Driver' : 'Ready for Pickup',
      badgeClass: allChecked
        ? 'bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/30'
        : 'bg-[#18181b] hover:bg-[#27272a] text-white',
      icon: Truck,
    };
  }

  if (order.status === 'ready_for_pickup') {
    return {
      nextStatus: 'completed',
      label: isDelivery ? 'Complete Delivery' : 'Handed to Customer',
      shortLabel: isDelivery ? 'Complete Delivery' : 'Complete Pickup',
      badgeClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
      icon: CheckCircle2,
    };
  }

  return null;
}

// Status badge & accent bar styling helper
function getStatusBadge(status, fulfillmentType = '') {
  const isDelivery = fulfillmentType === 'Home Delivery';

  switch (status) {
    case 'confirmed':
      return {
        label: 'New Order',
        class: 'bg-amber-100 text-amber-950 border-amber-300 font-bold',
        topBar: 'bg-amber-500',
        dot: 'bg-amber-500 animate-pulse',
      };
    case 'packing':
      return {
        label: 'In Packing',
        class: 'bg-blue-50 text-blue-900 border-blue-300 font-bold',
        topBar: 'bg-blue-600',
        dot: 'bg-blue-600',
      };
    case 'ready_for_pickup':
      return {
        label: isDelivery ? 'Ready for Driver' : 'Ready at Counter',
        class: 'bg-emerald-50 text-emerald-900 border-emerald-300 font-bold',
        topBar: 'bg-emerald-600',
        dot: 'bg-emerald-600',
      };
    case 'completed':
      return {
        label: 'Fulfilled',
        class: 'bg-zinc-100 text-zinc-700 border-zinc-200 font-semibold',
        topBar: 'bg-zinc-400',
        dot: 'bg-zinc-500',
      };
    case 'cancelled':
      return {
        label: 'Cancelled & Refunded',
        class: 'bg-rose-50 text-rose-800 border-rose-300 font-semibold',
        topBar: 'bg-rose-500',
        dot: 'bg-rose-500',
      };
    default:
      return {
        label: status,
        class: 'bg-zinc-50 text-zinc-700 border-zinc-200',
        topBar: 'bg-zinc-300',
        dot: 'bg-zinc-400',
      };
  }
}
</script>

<template>
  <Head title="Live Orders & Fulfillment — Masala Mart Admin" />

  <AdminLayout title="Live Orders" :full-width="true">
    <div class="space-y-4 sm:space-y-5 w-full pb-16">

      <!-- FLOATING NON-BLOCKING TOAST NOTIFICATION -->
      <transition
        enter-active-class="transform ease-out duration-200 transition"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="toastNotification"
          class="fixed bottom-5 right-5 z-50 max-w-md px-4 py-2.5 rounded-lg shadow-xl border flex items-center gap-3 text-xs font-semibold"
          :class="{
            'bg-zinc-900 text-white border-zinc-700': toastNotification.type === 'success' || toastNotification.type === 'info',
            'bg-rose-950 text-rose-100 border-rose-700': toastNotification.type === 'error',
          }"
        >
          <CheckCircle2 v-if="toastNotification.type === 'success'" class="w-4 h-4 text-emerald-400 shrink-0" />
          <AlertCircle v-else-if="toastNotification.type === 'error'" class="w-4 h-4 text-rose-400 shrink-0" />
          <Sparkles v-else class="w-4 h-4 text-zinc-300 shrink-0" />
          <span class="leading-snug">{{ toastNotification.message }}</span>
          <button
            type="button"
            @click="toastNotification = null"
            class="ml-1 text-zinc-400 hover:text-white cursor-pointer"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>
      </transition>

      <!-- LIVE ORDER NOTIFICATION BANNER -->
      <transition
        enter-active-class="transform ease-out duration-200 transition"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="unacknowledgedOrders.length > 0"
          class="sticky top-16 z-40 bg-zinc-900 text-white p-3.5 rounded-lg shadow-xl border border-zinc-700 flex flex-col sm:flex-row items-center justify-between gap-3"
        >
          <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="relative shrink-0">
              <div class="w-9 h-9 rounded-md bg-amber-500/20 text-amber-400 border border-amber-500/40 flex items-center justify-center font-bold text-sm">
                <ShoppingBag class="w-4 h-4" />
              </div>
              <span class="absolute -top-1 -right-1 flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-md bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-400 border-2 border-zinc-900"></span>
              </span>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase font-bold tracking-wider bg-amber-400 text-zinc-950 px-2 py-0.5 rounded font-mono inline-flex items-center gap-1">
                  <span>New Incoming Order</span>
                </span>
                <span class="text-xs font-medium text-zinc-300">
                  {{ unacknowledgedOrders.length }} Pending Order{{ unacknowledgedOrders.length > 1 ? 's' : '' }}
                </span>
              </div>
              <p class="text-sm font-semibold text-white mt-0.5">
                Order {{ unacknowledgedOrders[0].order_number }} · {{ unacknowledgedOrders[0].customer_name }} · ${{ Number(unacknowledgedOrders[0].total).toFixed(2) }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
            <button
              type="button"
              @click="updateOrderStatus(unacknowledgedOrders[0], 'packing')"
              class="flex-1 sm:flex-initial h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs inline-flex items-center justify-center gap-1.5 cursor-pointer transition-colors"
            >
              <Check class="w-3.5 h-3.5" />
              <span>Accept & Pack</span>
            </button>

            <button
              type="button"
              @click="openOrderDetails(unacknowledgedOrders[0])"
              class="flex-1 sm:flex-initial h-8 px-3 rounded-md bg-white hover:bg-zinc-100 text-zinc-900 font-semibold text-xs inline-flex items-center justify-center gap-1.5 cursor-pointer transition-colors"
            >
              <Eye class="w-3.5 h-3.5" />
              <span>Inspect</span>
            </button>

            <button
              type="button"
              @click="stopOrderAlarm"
              class="h-8 px-3 rounded-md bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 text-xs font-medium inline-flex items-center justify-center gap-1.5 cursor-pointer transition-colors"
              title="Silence alert chime"
            >
              <VolumeX class="w-3.5 h-3.5" />
              <span class="hidden sm:inline">Silence</span>
            </button>
          </div>
        </div>
      </transition>

      <!-- ========================================================= -->
      <!-- TOP HEADER: TITLE, VIEW SWITCHER, SALES & AUDIO CONTROLS  -->
      <!-- ========================================================= -->
      <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white p-4 rounded-lg border border-zinc-200 shadow-2xs">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-lg sm:text-xl font-semibold text-zinc-900 tracking-tight">
              Live Orders & Dispatch
            </h1>

            <!-- Real-time live status pill -->
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold border"
              :class="isReverbConnected ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-amber-50 text-amber-800 border-amber-300'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="isReverbConnected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
              <span>{{ isReverbConnected ? 'Live Sync' : 'Standby' }}</span>
            </span>

            <!-- Active Queue Summary Pill -->
            <button
              type="button"
              @click="toggleActiveQueueOnly"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold border transition-colors cursor-pointer"
              :class="showActiveQueueOnly
                ? 'bg-[#18181b] text-[#a1a1aa] border-[#18181b]'
                : 'bg-[#f4f4f5] text-[#27272a] border-[#e4e4e7] hover:border-[#18181b]'"
              title="Filter to active unfulfilled orders only"
            >
              <Layers class="w-3 h-3" />
              <span>{{ activeQueueCount }} Active in Queue</span>
            </button>

            <!-- New Orders Quick Alert Badge in Header -->
            <button
              v-if="liveStats.confirmed > 0"
              type="button"
              @click="selectStage('confirmed')"
              class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-950 border border-amber-300 transition-colors cursor-pointer animate-pulse"
            >
              <Sparkles class="w-3 h-3 text-amber-700" />
              <span>{{ liveStats.confirmed }} New Order{{ liveStats.confirmed === 1 ? '' : 's' }} Needs Acceptance</span>
            </button>
          </div>
          <p class="text-xs text-[#52525b] mt-1">
            New orders are prioritized first. Check off items as you pack, switch between Card, Kanban &amp; Table views, or advance status in 1 click.
          </p>
        </div>

        <!-- Right Toolbar: View Switcher + Today's Sales + Audio Controls -->
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Segmented View Mode Switcher (Cards | Board | Table) -->
          <div class="h-10 p-1 rounded-md bg-[#f4f4f5] border border-[#e4e4e7] inline-flex items-center gap-0.5">
            <button
              type="button"
              @click="setViewMode('cards')"
              class="h-8 px-3 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
              :class="viewMode === 'cards' ? 'bg-[#18181b] text-white shadow-2xs' : 'text-zinc-600 hover:text-[#09090b]'"
              title="Detailed Packing Cards View"
            >
              <LayoutGrid class="w-3.5 h-3.5" />
              <span>Cards</span>
            </button>
            <button
              type="button"
              @click="setViewMode('board')"
              class="h-8 px-3 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
              :class="viewMode === 'board' ? 'bg-[#18181b] text-white shadow-2xs' : 'text-zinc-600 hover:text-[#09090b]'"
              title="4-Column Live Kanban Board View"
            >
              <Columns3 class="w-3.5 h-3.5" />
              <span>Board</span>
            </button>
            <button
              type="button"
              @click="setViewMode('table')"
              class="h-8 px-3 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
              :class="viewMode === 'table' ? 'bg-[#18181b] text-white shadow-2xs' : 'text-zinc-600 hover:text-[#09090b]'"
              title="Compact Manager Dispatch Table"
            >
              <List class="w-3.5 h-3.5" />
              <span>Table</span>
            </button>
          </div>

          <!-- Today's Net Sales Pill -->
          <div class="h-10 px-4 rounded-md bg-[#18181b] text-white border border-zinc-800 flex items-center gap-2 shadow-2xs">
            <DollarSign class="w-3.5 h-3.5 text-[#a1a1aa] shrink-0" />
            <span class="text-xs text-zinc-300 font-medium whitespace-nowrap">
              Today <span class="text-zinc-400">({{ liveStats.total_today || 0 }})</span>:
            </span>
            <span class="text-sm font-semibold text-[#a1a1aa] whitespace-nowrap">
              ${{ Number(liveStats.revenue_today || 0).toFixed(2) }}
            </span>
          </div>

          <!-- Test Bell Button -->
          <button
            type="button"
            @click="playOrderChime"
            class="h-10 px-3.5 rounded-md text-xs font-semibold text-[#09090b] bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] hover:border-[#18181b] inline-flex items-center gap-1.5 transition-all cursor-pointer"
            title="Preview kitchen bell chime"
          >
            <Sparkles class="w-3.5 h-3.5 text-[#18181b] shrink-0" />
            <span class="hidden sm:inline">Test Bell</span>
          </button>

          <!-- Bell Toggle Button -->
          <button
            type="button"
            @click="toggleSound"
            :class="isSoundEnabled
              ? 'text-amber-950 bg-amber-50 hover:bg-amber-100 border-amber-300'
              : 'text-zinc-600 bg-zinc-100 hover:bg-zinc-200 border-zinc-300'"
            class="h-10 px-3.5 rounded-md text-xs font-semibold border inline-flex items-center gap-1.5 transition-all cursor-pointer"
            title="Toggle kitchen chime audio"
          >
            <Volume2 v-if="isSoundEnabled" class="w-4 h-4 text-amber-600 shrink-0" />
            <VolumeX v-else class="w-4 h-4 text-zinc-500 shrink-0" />
            <span>{{ isSoundEnabled ? 'Bell On' : 'Muted' }}</span>
          </button>

          <!-- Refresh Button -->
          <button
            type="button"
            @click="refreshOrders"
            :disabled="isRefreshing"
            class="h-10 px-3.5 rounded-md text-xs font-semibold text-[#09090b] bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] hover:border-[#18181b] inline-flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-60"
            title="Refresh orders from server"
          >
            <RefreshCw class="w-3.5 h-3.5 text-zinc-600 shrink-0" :class="isRefreshing ? 'animate-spin' : ''" />
            <span class="hidden sm:inline">Refresh</span>
          </button>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- UNIFIED STAGE PIPELINE CARDS (Single Source of Stage Filter) -->
      <!-- ========================================================= -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 sm:gap-3">

        <!-- 1. All Orders -->
        <button
          type="button"
          @click="selectStage('all')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden group"
          :class="activeStage === 'all' && !showActiveQueueOnly
            ? 'bg-[#18181b] text-white border-[#18181b] shadow-md'
            : 'bg-white text-[#09090b] border-[#e4e4e7] hover:border-[#18181b] shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'all' && !showActiveQueueOnly ? 'text-[#a1a1aa]' : 'text-[#71717a]'">
            <span>All Orders</span>
            <ShoppingBag class="w-3.5 h-3.5" />
          </div>
          <div class="text-2xl font-semibold mt-1">
            {{ totalAllOrdersCount }}
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'all' && !showActiveQueueOnly ? 'text-zinc-300' : 'text-zinc-500'">
            {{ liveStats.total_today || 0 }} placed today
          </div>
        </button>

        <!-- 2. New Orders (Confirmed) -->
        <button
          type="button"
          @click="selectStage('confirmed')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden"
          :class="activeStage === 'confirmed'
            ? 'bg-amber-500 text-zinc-950 border-amber-600 shadow-md ring-2 ring-amber-500/30'
            : 'bg-amber-50/70 text-amber-950 border-amber-300/90 hover:border-amber-500 shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'confirmed' ? 'text-zinc-950' : 'text-amber-900'">
            <span>New Orders</span>
            <Sparkles class="w-3.5 h-3.5" :class="activeStage === 'confirmed' ? 'text-zinc-950' : 'text-amber-600'" />
          </div>
          <div class="text-2xl font-semibold mt-1 flex items-center gap-2">
            <span>{{ liveStats.confirmed }}</span>
            <span v-if="liveStats.confirmed > 0" class="w-2 h-2 rounded-full bg-amber-600 animate-ping"></span>
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'confirmed' ? 'text-zinc-900 font-semibold' : 'text-amber-800'">
            Needs acceptance
          </div>
        </button>

        <!-- 3. In Packing -->
        <button
          type="button"
          @click="selectStage('packing')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden"
          :class="activeStage === 'packing'
            ? 'bg-blue-600 text-white border-blue-700 shadow-md ring-2 ring-blue-600/30'
            : 'bg-blue-50/60 text-blue-950 border-blue-200/90 hover:border-blue-400 shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'packing' ? 'text-blue-100' : 'text-blue-800'">
            <span>In Packing</span>
            <Package class="w-3.5 h-3.5" :class="activeStage === 'packing' ? 'text-white' : 'text-blue-600'" />
          </div>
          <div class="text-2xl font-semibold mt-1">
            {{ liveStats.packing }}
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'packing' ? 'text-blue-100' : 'text-blue-800/80'">
            Being hand-picked
          </div>
        </button>

        <!-- 4. Ready for Dispatch -->
        <button
          type="button"
          @click="selectStage('ready_for_pickup')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden"
          :class="activeStage === 'ready_for_pickup'
            ? 'bg-emerald-600 text-white border-emerald-700 shadow-md ring-2 ring-emerald-600/30'
            : 'bg-emerald-50/60 text-emerald-950 border-emerald-200/90 hover:border-emerald-400 shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'ready_for_pickup' ? 'text-emerald-100' : 'text-emerald-800'">
            <span>Ready Dispatch</span>
            <Truck class="w-3.5 h-3.5" :class="activeStage === 'ready_for_pickup' ? 'text-white' : 'text-emerald-600'" />
          </div>
          <div class="text-2xl font-semibold mt-1">
            {{ liveStats.ready_for_pickup }}
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'ready_for_pickup' ? 'text-emerald-100' : 'text-emerald-800/80'">
            Waiting for handoff
          </div>
        </button>

        <!-- 5. Fulfilled / Completed -->
        <button
          type="button"
          @click="selectStage('completed')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden"
          :class="activeStage === 'completed'
            ? 'bg-zinc-800 text-white border-zinc-900 shadow-md'
            : 'bg-zinc-50/80 text-zinc-900 border-zinc-200/90 hover:border-zinc-400 shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'completed' ? 'text-emerald-300' : 'text-zinc-600'">
            <span>Fulfilled</span>
            <CheckCircle2 class="w-3.5 h-3.5" :class="activeStage === 'completed' ? 'text-emerald-400' : 'text-emerald-600'" />
          </div>
          <div class="text-2xl font-semibold mt-1">
            {{ completedOrdersCount }}
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'completed' ? 'text-zinc-300' : 'text-zinc-500'">
            {{ liveStats.completed_today || 0 }} completed today
          </div>
        </button>

        <!-- 6. Cancelled & Refunded -->
        <button
          type="button"
          @click="selectStage('cancelled')"
          class="text-left rounded-lg p-3.5 transition-all cursor-pointer border relative overflow-hidden"
          :class="activeStage === 'cancelled'
            ? 'bg-rose-600 text-white border-rose-700 shadow-md ring-2 ring-rose-600/30'
            : 'bg-rose-50/50 text-rose-950 border-rose-200/80 hover:border-rose-400 shadow-2xs'"
        >
          <div class="text-[11px] font-bold uppercase tracking-wider flex items-center justify-between"
               :class="activeStage === 'cancelled' ? 'text-rose-100' : 'text-rose-800'">
            <span>Cancelled</span>
            <Ban class="w-3.5 h-3.5" :class="activeStage === 'cancelled' ? 'text-white' : 'text-rose-500'" />
          </div>
          <div class="text-2xl font-semibold mt-1">
            {{ cancelledOrdersCount }}
          </div>
          <div class="text-[10px] mt-0.5" :class="activeStage === 'cancelled' ? 'text-rose-100' : 'text-rose-800/80'">
            Refunded &amp; restocked
          </div>
        </button>

      </div>

      <!-- ========================================================= -->
      <!-- FILTER, SORT & SEARCH BAR                                 -->
      <!-- ========================================================= -->
      <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 bg-white p-3 sm:px-4 rounded-lg border border-[#e4e4e7] shadow-2xs">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 mr-0.5">
            Queue:
          </span>

          <!-- Active Queue Only Toggle -->
          <button
            type="button"
            @click="toggleActiveQueueOnly"
            class="h-9 px-3.5 text-xs font-semibold rounded-md border transition-all cursor-pointer inline-flex items-center gap-1.5"
            :class="showActiveQueueOnly
              ? 'bg-[#18181b] text-[#a1a1aa] border-zinc-900'
              : 'bg-[#fafafa] hover:bg-[#f4f4f5] text-[#09090b] border-[#e4e4e7]'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="showActiveQueueOnly ? 'bg-[#a1a1aa]' : 'bg-emerald-500'"></span>
            <span>Active Only ({{ activeQueueCount }})</span>
          </button>

          <div class="h-4 w-px bg-[#e4e4e7] mx-0.5 hidden sm:block"></div>

          <!-- All Fulfillment Modes -->
          <button
            type="button"
            @click="fulfillmentFilter = 'all'"
            class="h-9 px-3.5 text-xs font-semibold rounded-md border transition-all cursor-pointer inline-flex items-center"
            :class="fulfillmentFilter === 'all'
              ? 'bg-[#18181b] text-white border-zinc-900'
              : 'bg-[#fafafa] hover:bg-[#f4f4f5] text-[#09090b] border-[#e4e4e7]'"
          >
            All Modes ({{ fulfillmentCounts.all }})
          </button>

          <!-- Store Pickup -->
          <button
            type="button"
            @click="fulfillmentFilter = fulfillmentFilter === 'Store Pickup' ? 'all' : 'Store Pickup'"
            class="h-9 px-3.5 text-xs font-semibold rounded-md border transition-all cursor-pointer inline-flex items-center gap-1.5"
            :class="fulfillmentFilter === 'Store Pickup'
              ? 'bg-[#18181b] text-white border-[#27272a]'
              : 'bg-[#fafafa] hover:bg-[#f4f4f5] text-[#09090b] border-[#e4e4e7]'"
          >
            <ShoppingBag class="w-3.5 h-3.5 shrink-0" />
            <span>Pickup ({{ fulfillmentCounts.pickup }})</span>
          </button>

          <!-- Home Delivery -->
          <button
            type="button"
            @click="fulfillmentFilter = fulfillmentFilter === 'Home Delivery' ? 'all' : 'Home Delivery'"
            class="h-9 px-3.5 text-xs font-semibold rounded-md border transition-all cursor-pointer inline-flex items-center gap-1.5"
            :class="fulfillmentFilter === 'Home Delivery'
              ? 'bg-blue-700 text-white border-blue-800'
              : 'bg-[#fafafa] hover:bg-[#f4f4f5] text-[#09090b] border-[#e4e4e7]'"
          >
            <Truck class="w-3.5 h-3.5 shrink-0" />
            <span>Delivery ({{ fulfillmentCounts.delivery }})</span>
          </button>

          <!-- ASAP Quick Filter -->
          <button
            type="button"
            @click="timingFilter = timingFilter === 'asap' ? 'all' : 'asap'"
            class="h-9 px-3.5 text-xs font-semibold rounded-md border transition-all cursor-pointer inline-flex items-center gap-1.5"
            :class="timingFilter === 'asap'
              ? 'bg-amber-600 text-white border-amber-700'
              : 'bg-[#fafafa] hover:bg-amber-50 text-[#09090b] border-[#e4e4e7]'"
          >
            <Zap class="w-3.5 h-3.5 shrink-0" :class="timingFilter === 'asap' ? 'text-white' : 'text-amber-600'" />
            <span>ASAP Rush ({{ fulfillmentCounts.asap }})</span>
          </button>

          <!-- Clear Filters -->
          <button
            v-if="hasActiveFilters"
            type="button"
            @click="resetAllFilters"
            class="h-9 px-3 rounded-md bg-[#f4f4f5] text-[#27272a] hover:bg-[#e4e4e7] text-xs font-bold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span>Reset</span>
          </button>
        </div>

        <!-- Right: Sort Dropdown + Wide Search Input + Result Count -->
        <div class="flex items-center gap-2.5 w-full xl:w-auto justify-end flex-wrap sm:flex-nowrap">
          <!-- Sort Select -->
          <div class="relative inline-flex items-center">
            <ArrowUpDown class="w-3.5 h-3.5 text-zinc-400 absolute left-3 pointer-events-none" />
            <select
              v-model="sortBy"
              aria-label="Sort orders"
              class="h-9 pl-8 pr-7 text-xs font-semibold text-[#09090b] bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] rounded-md cursor-pointer"
            >
              <option value="priority">Sort: Priority Queue</option>
              <option value="wait_desc">Sort: Longest Wait First</option>
              <option value="newest">Sort: Newest Placed</option>
              <option value="total_desc">Sort: Highest Total</option>
            </select>
          </div>

          <!-- Search Input with '/' shortcut hint -->
          <div class="relative flex-1 sm:w-72 lg:w-80">
            <Search class="w-3.5 h-3.5 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <Input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Search #order, customer, phone, item..."
              class="h-9 pl-9 pr-9 text-xs bg-[#fafafa] border-[#e4e4e7] rounded-md focus-visible:bg-white w-full"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 p-0.5 cursor-pointer"
              title="Clear search"
            >
              <X class="w-3.5 h-3.5" />
            </button>
            <span
              v-else
              class="hidden sm:inline-flex items-center justify-center absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-mono text-zinc-400 bg-white border border-[#e4e4e7] rounded pointer-events-none"
            >
              /
            </span>
          </div>

          <span class="text-xs text-zinc-500 whitespace-nowrap shrink-0 hidden md:inline">
            Showing <strong class="text-[#09090b]">{{ filteredOrders.length }}</strong> order{{ filteredOrders.length === 1 ? '' : 's' }}
          </span>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- VIEW 1: PACKING CARDS GRID VIEW (Default KDS Cards)       -->
      <!-- ========================================================= -->
      <template v-if="viewMode === 'cards'">
        <div v-if="filteredOrders.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-start">
          <Card
            v-for="order in filteredOrders"
            :key="order.id"
            class="bg-white rounded-lg overflow-hidden shadow-2xs hover:shadow-md transition-all flex flex-col justify-between"
            :class="[
              order.isUnacknowledged
                ? 'border-amber-500 ring-2 ring-amber-500 shadow-xl'
                : order.status === 'confirmed'
                  ? 'border-amber-300/90 hover:border-amber-500'
                  : 'border-[#e4e4e7] hover:border-[#18181b]/70'
            ]"
          >
            <!-- Top Colored Status Strip -->
            <div class="h-1.5 w-full" :class="getStatusBadge(order.status, order.fulfillment_type).topBar"></div>

            <!-- Card Header: Status Row + Order Number Row + Stepper + Customer Box -->
            <div class="p-4 bg-[#fafafa] border-b border-[#e4e4e7]/70 space-y-3">
              <!-- Top Row: Status Badge + ASAP/Urgency on Left | Price & Payment on Right -->
              <div class="flex items-start justify-between gap-2">
                <div class="space-y-1.5 min-w-0">
                  <!-- Status & Urgency Pills -->
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      class="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-0.5 rounded-md border"
                      :class="getStatusBadge(order.status, order.fulfillment_type).class"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(order.status, order.fulfillment_type).dot"></span>
                      <span>{{ getStatusBadge(order.status, order.fulfillment_type).label }}</span>
                    </span>

                    <!-- ASAP Badge -->
                    <span
                      v-if="isAsapOrder(order)"
                      class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-bold"
                    >
                      <Zap class="w-2.5 h-2.5 text-amber-600 fill-amber-500" />
                      <span>ASAP</span>
                    </span>

                    <!-- Live Elapsed Wait Urgency Badge -->
                    <span
                      v-if="getOrderUrgency(order)"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold"
                      :class="getOrderUrgency(order).class"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="getOrderUrgency(order).dot"></span>
                      <span>{{ getOrderUrgency(order).label }}</span>
                    </span>
                  </div>

                  <!-- Order Number + Copy -->
                  <div class="flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="copyText(order.order_number, 'Order # copied')"
                      class="font-mono text-sm font-bold text-[#09090b] hover:text-[#18181b] inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                      title="Click to copy order number"
                    >
                      <span>{{ order.order_number }}</span>
                      <Copy class="w-3.5 h-3.5 text-zinc-400 hover:text-[#18181b]" />
                    </button>
                  </div>

                  <!-- Live Relative Timestamp & Online Payment Status -->
                  <div class="text-[11px] text-[#52525b] flex items-center gap-1.5 flex-wrap">
                    <Clock class="w-3 h-3 text-zinc-400 shrink-0" />
                    <span>Placed {{ getLiveRelativeTime(order) }}</span>
                    <span v-if="order.stripe_payment_id" class="text-emerald-700 font-semibold">· Paid Online</span>
                  </div>
                </div>

                <!-- Total Price, Payment Method & Unit Summary -->
                <div class="text-right shrink-0">
                  <div
                    class="font-semibold text-xl leading-none"
                    :class="isOrderFullyRefunded(order) ? 'line-through text-rose-500' : 'text-[#09090b]'"
                  >
                    ${{ displayOrderTotal(order).toFixed(2) }}
                  </div>
                  <div
                    v-if="Boolean(Number(order.refunded_total || 0))"
                    class="text-[10px] font-bold text-rose-700 mt-0.5"
                  >
                    Refunded -${{ Number(order.refunded_total).toFixed(2) }}
                  </div>
                  <div class="text-[10px] text-[#71717a] font-semibold uppercase tracking-wider inline-flex items-center justify-end gap-1 mt-1.5 bg-white px-2 py-0.5 rounded-md border border-[#e4e4e7]/70">
                    <CreditCard class="w-2.5 h-2.5 text-zinc-500" />
                    <span>{{ order.payment_method }}</span>
                  </div>
                  <div class="text-[10px] text-zinc-500 mt-1 font-medium">
                    {{ order.items?.length || order.items_count }} item{{ (order.items?.length || order.items_count) === 1 ? '' : 's' }} · {{ totalUnitsForOrder(order) }} unit{{ totalUnitsForOrder(order) === 1 ? '' : 's' }}
                  </div>
                </div>
              </div>

              <!-- Mini 4-Step Visual Progress Stepper (Interactive on active orders) -->
              <div v-if="order.status !== 'cancelled' && order.status !== 'completed'" class="grid grid-cols-4 gap-1 pt-0.5">
                <button
                  v-for="step in workflowSteps"
                  :key="step.key"
                  type="button"
                  :disabled="updatingOrderId === order.id || order.status === step.key || order.status === 'completed'"
                  @click="updateOrderStatus(order, step.key)"
                  class="py-1 px-1.5 rounded-lg text-[10px] font-bold text-center transition-all cursor-pointer disabled:cursor-default truncate border"
                  :class="[
                    order.status === step.key
                      ? 'bg-[#18181b] text-[#a1a1aa] border-[#18181b] shadow-2xs'
                      : getStepIndex(order.status) > step.stepIndex
                        ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 hover:bg-emerald-100/70'
                        : 'bg-white text-zinc-400 border-[#e4e4e7]/60 hover:text-zinc-700 hover:border-[#18181b]/50'
                  ]"
                  :title="`Move order to ${step.fullLabel}`"
                >
                  <Check v-if="getStepIndex(order.status) > step.stepIndex" class="w-2.5 h-2.5 inline mr-0.5 text-emerald-600" />
                  <span>{{ step.shortLabel }}</span>
                </button>
              </div>

              <!-- Customer & Fulfillment Slot/Address Box -->
              <div class="p-3 rounded-md bg-white border border-[#e4e4e7]/80 space-y-2 text-xs">
                <div class="flex items-center justify-between gap-2">
                  <div class="font-bold text-[#09090b] truncate">{{ order.customer_name }}</div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <a
                      v-if="order.customer_phone"
                      :href="'tel:' + order.customer_phone"
                      class="text-[#18181b] hover:text-[#27272a] hover:underline inline-flex items-center gap-1 text-[11px] font-semibold"
                    >
                      <Phone class="w-3 h-3" />
                      <span>{{ order.customer_phone }}</span>
                    </a>
                    <button
                      v-if="order.customer_phone"
                      type="button"
                      @click="copyText(order.customer_phone, 'Phone copied')"
                      class="text-zinc-400 hover:text-[#09090b] p-0.5 cursor-pointer"
                      title="Copy phone number"
                    >
                      <Copy class="w-3 h-3" />
                    </button>
                  </div>
                </div>

                <!-- Delivery Street Address OR Store Pickup Window -->
                <div
                  v-if="order.fulfillment_type === 'Home Delivery'"
                  class="pt-2 border-t border-[#e4e4e7]/50 flex items-start justify-between gap-2 text-[11px]"
                >
                  <div class="flex items-start gap-2 min-w-0">
                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-800 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0">
                      <Truck class="w-3 h-3 text-blue-600" />
                      <span>Delivery</span>
                    </span>
                    <span class="text-zinc-700 font-medium break-words leading-snug pt-0.5">
                      {{ order.delivery_address || 'Address not specified' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <a
                      v-if="order.delivery_address"
                      :href="'https://maps.google.com/?q=' + encodeURIComponent(order.delivery_address)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-blue-600 hover:text-blue-800 p-0.5"
                      title="Open delivery address in Google Maps"
                    >
                      <MapPin class="w-3.5 h-3.5" />
                    </a>
                    <button
                      v-if="order.delivery_address"
                      type="button"
                      @click="copyText(order.delivery_address, 'Address copied')"
                      class="text-zinc-400 hover:text-[#09090b] p-0.5 cursor-pointer"
                      title="Copy delivery address"
                    >
                      <Copy class="w-3 h-3" />
                    </button>
                  </div>
                </div>

                <div
                  v-else
                  class="pt-2 border-t border-[#e4e4e7]/50 flex items-center justify-between gap-2 text-[11px]"
                >
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex items-center gap-1 bg-[#f4f4f5] text-[#27272a] border border-[#e4e4e7] text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0">
                      <ShoppingBag class="w-3 h-3 text-[#18181b]" />
                      <span>Pickup</span>
                    </span>
                    <span class="text-zinc-800 font-semibold truncate">{{ order.pickup_slot }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Card Body: Interactive Packing Checklist -->
            <CardContent class="p-4 flex-1 space-y-2.5">
              <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-[#71717a]">
                <span class="inline-flex items-center gap-1.5">
                  <span>Packing List ({{ packedCountForOrder(order) }}/{{ packableItemsForOrder(order).length }})</span>
                  <span
                    v-if="packingProgressPercent(order) === 100 && packableItemsForOrder(order).length"
                    class="text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-300 px-1.5 py-0.2 rounded-md normal-case font-bold"
                  >
                    All Packed ✓
                  </span>
                </span>
                <button
                  v-if="packableItemsForOrder(order).length > 0"
                  type="button"
                  @click="markAllItemsPacked(order)"
                  class="text-[11px] font-bold text-[#18181b] hover:text-[#27272a] hover:underline cursor-pointer normal-case transition-colors"
                >
                  {{ packedCountForOrder(order) === packableItemsForOrder(order).length ? 'Uncheck all' : 'Check all ✓' }}
                </button>
              </div>

              <!-- Packing Progress Bar -->
              <div
                v-if="order.status !== 'cancelled'"
                class="w-full h-1.5 bg-[#f4f4f5] rounded-full overflow-hidden"
              >
                <div
                  class="h-full transition-all duration-200 rounded-md"
                  :class="packingProgressPercent(order) === 100 ? 'bg-emerald-600' : 'bg-[#18181b]'"
                  :style="{ width: `${packingProgressPercent(order)}%` }"
                ></div>
              </div>

              <!-- Item Checklist Rows (With Thumbnails, Multi-Quantity Callouts & OOS Refund Action) -->
              <div class="space-y-1 pt-0.5">
                <div
                  v-for="item in (expandedOrderCards[order.id] ? order.items : order.items.slice(0, 4))"
                  :key="item.id"
                  class="w-full flex items-center justify-between gap-2 text-xs py-1.5 px-2 rounded-md transition-colors group border"
                  :class="isItemFullyRefunded(item)
                    ? 'bg-rose-50/50 border-rose-200/80 opacity-80'
                    : isItemPacked(order.id, item.id)
                      ? 'bg-emerald-50/40 border-emerald-100 hover:bg-emerald-50/70'
                      : 'border-transparent hover:bg-[#fafafa]'"
                >
                  <button
                    type="button"
                    :disabled="isItemFullyRefunded(item)"
                    @click="toggleItemPacked(order.id, item.id)"
                    class="flex items-center gap-2 min-w-0 flex-1 text-left"
                    :class="isItemFullyRefunded(item) ? 'cursor-default' : 'cursor-pointer'"
                  >
                    <Ban v-if="isItemFullyRefunded(item)" class="w-4 h-4 text-rose-500 shrink-0" />
                    <CheckSquare v-else-if="isItemPacked(order.id, item.id)" class="w-4 h-4 text-emerald-600 shrink-0" />
                    <Square v-else class="w-4 h-4 text-zinc-300 group-hover:text-[#18181b] shrink-0" />

                    <!-- Product Thumbnail -->
                    <div class="w-7 h-7 rounded-lg bg-[#f4f4f5] border border-[#e4e4e7] overflow-hidden shrink-0 flex items-center justify-center">
                      <img
                        v-if="item.image"
                        :src="item.image"
                        :alt="item.name"
                        class="w-full h-full object-cover"
                        loading="lazy"
                      />
                      <Package v-else class="w-3.5 h-3.5 text-[#18181b]" />
                    </div>

                    <!-- Quantity Badge -->
                    <span
                      class="px-1.5 h-5 rounded-md border text-[11px] font-mono font-bold flex items-center justify-center shrink-0"
                      :class="isItemFullyRefunded(item)
                        ? 'bg-rose-100 text-rose-800 border-rose-300 line-through'
                        : activeItemQuantity(item) > 1
                          ? 'bg-amber-100 text-amber-950 border-amber-400 shadow-2xs'
                          : 'bg-[#f4f4f5] text-[#09090b] border-[#e4e4e7]'"
                    >
                      {{ isItemFullyRefunded(item) ? item.quantity : isItemPartiallyRefunded(item) ? `${activeItemQuantity(item)}/${item.quantity}` : activeItemQuantity(item) }}×
                    </span>

                    <div class="min-w-0">
                      <span
                        class="font-semibold block truncate"
                        :class="isItemFullyRefunded(item)
                          ? 'line-through text-rose-800'
                          : isItemPacked(order.id, item.id)
                            ? 'line-through text-zinc-400'
                            : 'text-[#09090b]'"
                      >
                        {{ item.name }}
                      </span>
                      <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                        <span v-if="item.size" class="text-[10px] text-zinc-400 leading-none">
                          {{ item.size }}
                        </span>
                        <span
                          v-if="isItemFullyRefunded(item)"
                          class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 border border-rose-200 leading-tight"
                        >
                          OOS · {{ item.refunded_quantity || item.quantity }}× Refunded (-${{ Number(item.refunded_amount || item.total_price).toFixed(2) }})
                        </span>
                        <span
                          v-else-if="isItemPartiallyRefunded(item)"
                          class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-900 border border-amber-300 leading-tight"
                        >
                          {{ item.refunded_quantity }}× OOS Refunded (-${{ Number(item.refunded_amount).toFixed(2) }})
                        </span>
                      </div>
                    </div>
                  </button>

                  <div class="flex items-center gap-1.5 shrink-0">
                    <button
                      v-if="['confirmed', 'packing', 'ready_for_pickup'].includes(order.status) && !isItemFullyRefunded(item)"
                      type="button"
                      @click.stop="openItemRefundModal(order, item)"
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white border border-rose-200 hover:border-rose-600 transition-colors cursor-pointer"
                      title="Mark item Out of Stock & issue partial Stripe refund"
                    >
                      {{ isItemPartiallyRefunded(item) ? `OOS (${activeItemQuantity(item)})` : 'OOS' }}
                    </button>
                    <span
                      class="font-mono text-[11px] shrink-0"
                      :class="isItemFullyRefunded(item) ? 'line-through text-rose-400' : 'text-zinc-600'"
                    >
                      ${{ (isItemFullyRefunded(item) ? Number(item.total_price) : Math.max(0, Number(item.total_price) - Number(item.refunded_amount || 0))).toFixed(2) }}
                    </span>
                  </div>
                </div>

                <!-- Expand / Collapse Toggle when order has > 4 items -->
                <button
                  v-if="order.items.length > 4"
                  type="button"
                  @click="toggleExpandCard(order.id)"
                  class="w-full py-1.5 px-2 text-[11px] text-[#18181b] hover:text-[#27272a] hover:bg-[#fafafa] rounded-lg font-bold flex items-center justify-center gap-1 cursor-pointer transition-colors"
                >
                  <template v-if="expandedOrderCards[order.id]">
                    <ChevronUp class="w-3.5 h-3.5" />
                    <span>Show fewer items</span>
                  </template>
                  <template v-else>
                    <ChevronDown class="w-3.5 h-3.5" />
                    <span>+ {{ order.items.length - 4 }} more item(s) — tap to expand</span>
                  </template>
                </button>
              </div>

              <!-- Customer / Order Note -->
              <div v-if="order.notes" class="p-2.5 rounded-md bg-amber-50/90 border border-amber-200 text-[11px] text-amber-950 font-medium leading-snug flex items-start gap-2">
                <MessageSquareWarning class="w-3.5 h-3.5 text-amber-700 shrink-0 mt-0.5" />
                <div><span class="font-bold">Customer Note:</span> {{ order.notes }}</div>
              </div>
            </CardContent>

            <!-- ======================================================= -->
            <!-- CARD FOOTER: ZERO CLIPPING, 100% ALIGNED, CRISP HOVER   -->
            <!-- ======================================================= -->
            <div class="p-3.5 bg-[#fafafa] border-t border-[#e4e4e7]/70 space-y-2">
              <!-- Row 1: Full-Width Primary Progression Button (Active Orders) -->
              <button
                v-if="getNextStatusInfo(order)"
                type="button"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order, getNextStatusInfo(order).nextStatus)"
                :class="[
                  'w-full h-10 px-4 rounded-md text-xs font-bold shadow-xs flex items-center justify-center gap-2 transition-all cursor-pointer active:scale-[0.99] disabled:opacity-50',
                  getNextStatusInfo(order).badgeClass,
                ]"
              >
                <Loader2 v-if="updatingOrderId === order.id" class="w-4 h-4 animate-spin shrink-0" />
                <component :is="getNextStatusInfo(order).icon" v-else class="w-4 h-4 shrink-0" />
                <span>{{ getNextStatusInfo(order).label }}</span>
                <ArrowRight class="w-4 h-4 shrink-0" />
              </button>

              <!-- Completed Banner (When order is fulfilled) -->
              <div
                v-else-if="order.status === 'completed'"
                class="w-full h-9 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-center gap-1.5"
              >
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Order Fulfilled</span>
              </div>

              <!-- Cancelled Banner (When order is cancelled) -->
              <div
                v-else-if="order.status === 'cancelled'"
                class="w-full h-9 rounded-md bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-center gap-1.5"
              >
                <Ban class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                <span>Order Cancelled &amp; Refunded</span>
              </div>

              <!-- Row 2: Perfectly Aligned Secondary Actions Grid -->
              <div
                class="grid gap-2"
                :class="order.status !== 'completed' && order.status !== 'cancelled' ? 'grid-cols-3' : 'grid-cols-2'"
              >
                <!-- Details Button -->
                <button
                  type="button"
                  @click="openOrderDetails(order)"
                  class="h-9 px-3 rounded-md text-xs font-semibold text-[#09090b] bg-white hover:bg-[#f4f4f5] hover:text-[#09090b] border border-[#e4e4e7] hover:border-[#18181b] inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                  title="Inspect full order details"
                >
                  <Eye class="w-3.5 h-3.5 text-zinc-700 shrink-0" />
                  <span>Details</span>
                </button>

                <!-- Print Slip Button -->
                <button
                  type="button"
                  @click="printOrderTicket(order)"
                  class="h-9 px-3 rounded-md text-xs font-semibold text-[#09090b] bg-white hover:bg-[#f4f4f5] hover:text-[#09090b] border border-[#e4e4e7] hover:border-[#18181b] inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                  title="Print Thermal Packing Slip"
                >
                  <Printer class="w-3.5 h-3.5 text-zinc-700 shrink-0" />
                  <span>Print Slip</span>
                </button>

                <!-- Cancel & Refund Button -->
                <button
                  v-if="order.status !== 'completed' && order.status !== 'cancelled'"
                  type="button"
                  @click="confirmCancelOrder(order)"
                  class="h-9 px-3 rounded-md text-xs font-semibold text-rose-700 bg-white hover:bg-rose-600 hover:text-white border border-rose-200 hover:border-rose-600 inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer group"
                  title="Cancel Order, Restock & Refund via Stripe"
                >
                  <Ban class="w-3.5 h-3.5 text-rose-600 group-hover:text-white shrink-0 transition-colors" />
                  <span>Cancel</span>
                </button>
              </div>
            </div>
          </Card>
        </div>
      </template>

      <!-- ========================================================= -->
      <!-- VIEW 2: 4-COLUMN LIVE KANBAN DISPATCH BOARD               -->
      <!-- ========================================================= -->
      <template v-else-if="viewMode === 'board'">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
          <div
            v-for="col in kanbanColumns"
            :key="col.key"
            class="bg-[#f4f4f5]/60 border border-[#e4e4e7] rounded-lg p-3 space-y-3 flex flex-col"
          >
            <!-- Column Header -->
            <div class="p-3 rounded-lg border flex items-center justify-between" :class="col.headerBg">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" :class="col.dotClass"></span>
                <div>
                  <h3 class="text-xs font-bold uppercase tracking-wider text-[#09090b]">
                    {{ col.title }}
                  </h3>
                  <p class="text-[10px] text-zinc-500">{{ col.subtitle }}</p>
                </div>
              </div>
              <span class="px-2.5 py-0.5 rounded-md text-xs font-mono font-bold shadow-2xs" :class="col.badgeClass">
                {{ col.orders.length }}
              </span>
            </div>

            <!-- Column Tickets -->
            <div v-if="col.orders.length > 0" class="space-y-3">
              <div
                v-for="order in col.orders"
                :key="order.id"
                class="bg-white rounded-lg border border-[#e4e4e7] hover:border-[#18181b] p-3.5 shadow-2xs hover:shadow-md transition-all space-y-2.5"
                :class="order.isUnacknowledged ? 'ring-2 ring-amber-500 border-amber-500' : ''"
              >
                <!-- Ticket Top Row -->
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="font-mono text-xs font-bold text-[#09090b]">{{ order.order_number }}</span>
                      <span
                        v-if="isAsapOrder(order)"
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded-md bg-amber-100 text-amber-900 border border-amber-300 text-[9px] font-bold"
                      >
                        <Zap class="w-2.5 h-2.5 text-amber-600 fill-amber-500" />
                        <span>ASAP</span>
                      </span>
                      <span
                        v-if="getOrderUrgency(order)"
                        class="px-1.5 py-0.2 rounded-md border text-[9px] font-bold"
                        :class="getOrderUrgency(order).class"
                      >
                        {{ getOrderUrgency(order).label }}
                      </span>
                    </div>
                    <div class="text-xs font-bold text-zinc-800 mt-0.5">{{ order.customer_name }}</div>
                    <div class="text-[10px] text-zinc-500">{{ getLiveRelativeTime(order) }}</div>
                  </div>
                  <div class="text-right shrink-0">
                    <div
                      class="font-semibold text-base"
                      :class="isOrderFullyRefunded(order) ? 'line-through text-rose-500' : 'text-[#09090b]'"
                    >
                      ${{ displayOrderTotal(order).toFixed(2) }}
                    </div>
                    <div v-if="Boolean(Number(order.refunded_total || 0))" class="text-[9px] font-bold text-rose-700">
                      Refunded -${{ Number(order.refunded_total).toFixed(2) }}
                    </div>
                    <div class="text-[10px] text-zinc-500">{{ totalUnitsForOrder(order) }} units</div>
                  </div>
                </div>

                <!-- Fulfillment Pill -->
                <div class="text-[11px] px-2.5 py-1.5 rounded-md bg-[#fafafa] border border-[#e4e4e7]/70 flex items-center gap-1.5">
                  <Truck v-if="order.fulfillment_type === 'Home Delivery'" class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                  <ShoppingBag v-else class="w-3.5 h-3.5 text-[#18181b] shrink-0" />
                  <span class="font-medium text-zinc-700 truncate">
                    {{ order.fulfillment_type === 'Home Delivery' ? (order.delivery_address || 'Home Delivery') : order.pickup_slot }}
                  </span>
                </div>

                <!-- Compact Item Checklist -->
                <div class="space-y-1 max-h-40 overflow-y-auto pr-0.5">
                  <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="w-full flex items-center justify-between gap-1.5 text-[11px] py-1 px-1.5 rounded-lg hover:bg-[#fafafa]"
                  >
                    <button
                      type="button"
                      :disabled="isItemFullyRefunded(item)"
                      @click="toggleItemPacked(order.id, item.id)"
                      class="flex items-center gap-1.5 min-w-0 flex-1 text-left"
                      :class="isItemFullyRefunded(item) ? 'cursor-default' : 'cursor-pointer'"
                    >
                      <Ban v-if="isItemFullyRefunded(item)" class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                      <CheckSquare v-else-if="isItemPacked(order.id, item.id)" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                      <Square v-else class="w-3.5 h-3.5 text-zinc-300 shrink-0" />
                      <span
                        class="px-1 rounded text-[10px] font-mono font-bold shrink-0"
                        :class="isItemFullyRefunded(item)
                          ? 'bg-rose-100 text-rose-800 border border-rose-300 line-through'
                          : activeItemQuantity(item) > 1
                            ? 'bg-amber-100 text-amber-950 border border-amber-300'
                            : 'bg-[#f4f4f5] text-zinc-800'"
                      >
                        {{ isItemFullyRefunded(item) ? item.quantity : isItemPartiallyRefunded(item) ? `${activeItemQuantity(item)}/${item.quantity}` : activeItemQuantity(item) }}×
                      </span>
                      <span
                        class="truncate font-medium"
                        :class="isItemFullyRefunded(item)
                          ? 'line-through text-rose-800'
                          : isItemPacked(order.id, item.id)
                            ? 'line-through text-zinc-400'
                            : 'text-zinc-900'"
                      >
                        {{ item.name }}
                      </span>
                    </button>
                    <button
                      v-if="['confirmed', 'packing', 'ready_for_pickup'].includes(order.status) && !isItemFullyRefunded(item)"
                      type="button"
                      @click.stop="openItemRefundModal(order, item)"
                      class="px-1.5 py-0.2 rounded text-[9px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white border border-rose-200 transition-colors cursor-pointer shrink-0"
                    >
                      {{ isItemPartiallyRefunded(item) ? `OOS (${activeItemQuantity(item)})` : 'OOS' }}
                    </button>
                  </div>
                </div>

                <!-- Ticket Actions -->
                <div class="pt-2 border-t border-[#e4e4e7]/60 flex items-center gap-1.5">
                  <button
                    v-if="getNextStatusInfo(order)"
                    type="button"
                    :disabled="updatingOrderId === order.id"
                    @click="updateOrderStatus(order, getNextStatusInfo(order).nextStatus)"
                    :class="[
                      'flex-1 h-8 px-3 rounded-md text-[11px] font-bold flex items-center justify-center gap-1 transition-all cursor-pointer',
                      getNextStatusInfo(order).badgeClass,
                    ]"
                  >
                    <Loader2 v-if="updatingOrderId === order.id" class="w-3 h-3 animate-spin" />
                    <span>{{ getNextStatusInfo(order).shortLabel }}</span>
                    <ArrowRight class="w-3 h-3" />
                  </button>
                  <button
                    type="button"
                    @click="openOrderDetails(order)"
                    class="h-8 w-8 rounded-md bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] flex items-center justify-center text-zinc-700 cursor-pointer shrink-0"
                    title="Order details"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="printOrderTicket(order)"
                    class="h-8 w-8 rounded-md bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] flex items-center justify-center text-zinc-700 cursor-pointer shrink-0"
                    title="Print packing slip"
                  >
                    <Printer class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty Column Placeholder -->
            <div v-else class="py-10 px-4 text-center text-xs text-zinc-400 border border-dashed border-[#e4e4e7] rounded-lg bg-white/50">
              No orders in {{ col.title.toLowerCase() }}
            </div>
          </div>
        </div>
      </template>

      <!-- ========================================================= -->
      <!-- VIEW 3: COMPACT MANAGER DISPATCH TABLE VIEW               -->
      <!-- ========================================================= -->
      <template v-else-if="viewMode === 'table'">
        <div v-if="filteredOrders.length > 0" class="bg-white border border-[#e4e4e7] rounded-lg overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-[#fafafa] border-b border-[#e4e4e7] text-[10px] font-mono uppercase tracking-wider text-zinc-500">
                  <th class="py-3.5 px-4 font-bold">Order &amp; Wait</th>
                  <th class="py-3.5 px-4 font-bold">Customer</th>
                  <th class="py-3.5 px-4 font-bold">Fulfillment &amp; Window</th>
                  <th class="py-3.5 px-4 font-bold">Items &amp; Packing</th>
                  <th class="py-3.5 px-4 font-bold">Total</th>
                  <th class="py-3.5 px-4 font-bold">Status</th>
                  <th class="py-3.5 px-4 font-bold text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e4e4e7]/60">
                <tr
                  v-for="order in filteredOrders"
                  :key="order.id"
                  class="hover:bg-[#fafafa] transition-colors"
                  :class="order.isUnacknowledged ? 'bg-amber-50/40' : ''"
                >
                  <!-- Order & Wait -->
                  <td class="py-3.5 px-4 whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <button
                        type="button"
                        @click="openOrderDetails(order)"
                        class="font-mono font-bold text-[#09090b] hover:text-[#18181b] cursor-pointer"
                      >
                        {{ order.order_number }}
                      </button>
                      <span
                        v-if="isAsapOrder(order)"
                        class="px-1.5 py-0.2 rounded-md bg-amber-100 text-amber-900 border border-amber-300 text-[9px] font-bold"
                      >
                        ASAP
                      </span>
                    </div>
                    <div class="text-[11px] text-zinc-500 flex items-center gap-1.5 mt-0.5">
                      <span>{{ getLiveRelativeTime(order) }}</span>
                      <span
                        v-if="getOrderUrgency(order)"
                        class="px-1.5 py-0.2 rounded-md border text-[9px] font-bold"
                        :class="getOrderUrgency(order).class"
                      >
                        {{ getOrderUrgency(order).label }}
                      </span>
                    </div>
                  </td>

                  <!-- Customer -->
                  <td class="py-3.5 px-4">
                    <div class="font-bold text-[#09090b]">{{ order.customer_name }}</div>
                    <div class="text-[11px] text-zinc-500">{{ order.customer_phone || order.customer_email }}</div>
                  </td>

                  <!-- Fulfillment -->
                  <td class="py-3.5 px-4 max-w-xs">
                    <div class="inline-flex items-center gap-1 font-semibold" :class="order.fulfillment_type === 'Home Delivery' ? 'text-blue-700' : 'text-[#27272a]'">
                      <Truck v-if="order.fulfillment_type === 'Home Delivery'" class="w-3.5 h-3.5" />
                      <ShoppingBag v-else class="w-3.5 h-3.5" />
                      <span>{{ order.fulfillment_type }}</span>
                    </div>
                    <div class="text-[11px] text-zinc-600 truncate">
                      {{ order.fulfillment_type === 'Home Delivery' ? order.delivery_address : order.pickup_slot }}
                    </div>
                  </td>

                  <!-- Items & Packing -->
                  <td class="py-3.5 px-4">
                    <div class="font-semibold text-[#09090b]">
                      {{ order.items?.length || order.items_count }} items ({{ totalUnitsForOrder(order) }} units)
                    </div>
                    <div class="text-[11px] text-zinc-500">
                      {{ packedCountForOrder(order) }}/{{ packableItemsForOrder(order).length }} packed
                    </div>
                  </td>

                  <!-- Total -->
                  <td class="py-3.5 px-4 whitespace-nowrap">
                    <div
                      class="font-semibold text-sm"
                      :class="isOrderFullyRefunded(order) ? 'line-through text-rose-500' : 'text-[#09090b]'"
                    >
                      ${{ displayOrderTotal(order).toFixed(2) }}
                    </div>
                    <div v-if="Boolean(Number(order.refunded_total || 0))" class="text-[10px] font-bold text-rose-700">
                      Refunded -${{ Number(order.refunded_total).toFixed(2) }}
                    </div>
                    <div class="text-[10px] text-zinc-500 uppercase">{{ order.payment_method }}</div>
                  </td>

                  <!-- Status -->
                  <td class="py-3.5 px-4 whitespace-nowrap">
                    <span
                      class="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-0.5 rounded-md border"
                      :class="getStatusBadge(order.status, order.fulfillment_type).class"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(order.status, order.fulfillment_type).dot"></span>
                      <span>{{ getStatusBadge(order.status, order.fulfillment_type).label }}</span>
                    </span>
                  </td>

                  <!-- Quick Actions -->
                  <td class="py-3.5 px-4 whitespace-nowrap text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                      <button
                        v-if="getNextStatusInfo(order)"
                        type="button"
                        :disabled="updatingOrderId === order.id"
                        @click="updateOrderStatus(order, getNextStatusInfo(order).nextStatus)"
                        :class="[
                          'h-8 px-3 rounded-md text-[11px] font-bold inline-flex items-center gap-1 cursor-pointer transition-all',
                          getNextStatusInfo(order).badgeClass,
                        ]"
                      >
                        <span>{{ getNextStatusInfo(order).shortLabel }}</span>
                        <ArrowRight class="w-3 h-3" />
                      </button>
                      <button
                        type="button"
                        @click="openOrderDetails(order)"
                        class="h-8 px-2.5 rounded-md bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] text-zinc-700 font-semibold inline-flex items-center gap-1 cursor-pointer"
                        title="Inspect Order"
                      >
                        <Eye class="w-3.5 h-3.5" />
                        <span class="hidden lg:inline">Details</span>
                      </button>
                      <button
                        type="button"
                        @click="printOrderTicket(order)"
                        class="h-8 w-8 rounded-md bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] text-zinc-700 inline-flex items-center justify-center cursor-pointer"
                        title="Print Slip"
                      >
                        <Printer class="w-3.5 h-3.5" />
                      </button>
                      <button
                        v-if="order.status !== 'completed' && order.status !== 'cancelled'"
                        type="button"
                        @click="confirmCancelOrder(order)"
                        class="h-8 w-8 rounded-md bg-white hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 inline-flex items-center justify-center cursor-pointer transition-colors"
                        title="Cancel & Refund"
                      >
                        <Ban class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- EMPTY STATE (When no orders match active filters in Cards or Table mode) -->
      <div
        v-if="viewMode !== 'board' && filteredOrders.length === 0"
        class="p-14 bg-white rounded-lg border border-[#e4e4e7] text-center space-y-3 shadow-xs"
      >
        <div class="w-14 h-14 rounded-md bg-[#f4f4f5] text-[#18181b] flex items-center justify-center mx-auto shadow-inner">
          <ShoppingBag class="w-7 h-7" />
        </div>
        <h3 class="text-lg font-semibold text-[#09090b]">
          No orders matching your filters
        </h3>
        <p class="text-xs text-[#52525b] max-w-sm mx-auto">
          {{ hasActiveFilters
            ? 'Try clearing your search query, fulfillment mode, or stage filter above.'
            : 'Live orders will appear here automatically with instant audio alerts as customers checkout.' }}
        </p>
        <div v-if="hasActiveFilters" class="pt-1">
          <button
            type="button"
            @click="resetAllFilters"
            class="h-9 px-4 rounded-md text-xs font-semibold text-[#09090b] bg-[#fafafa] hover:bg-[#f4f4f5] border border-[#e4e4e7] cursor-pointer transition-colors"
          >
            Reset All Filters
          </button>
        </div>
      </div>

      <!-- PAGINATION BAR (When multiple pages exist) -->
      <div
        v-if="props.orders?.links && props.orders.links.length > 3"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-3.5 rounded-lg border border-[#e4e4e7] text-xs"
      >
        <div class="text-zinc-500">
          Showing <span class="font-bold text-[#09090b]">{{ props.orders.from || 1 }}</span> to
          <span class="font-bold text-[#09090b]">{{ props.orders.to || ordersList.length }}</span> of
          <span class="font-bold text-[#09090b]">{{ props.orders.total || ordersList.length }}</span> orders
        </div>
        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in props.orders.links" :key="idx">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              class="px-3 py-1.5 rounded-md text-xs font-semibold border transition-colors"
              :class="link.active ? 'bg-[#18181b] text-white border-[#18181b]' : 'bg-[#fafafa] hover:bg-[#f4f4f5] text-zinc-700 border-[#e4e4e7]'"
              v-html="link.label"
            />
            <span
              v-else
              class="px-3 py-1.5 rounded-md text-xs text-zinc-400"
              v-html="link.label"
            />
          </template>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- ORDER DETAILS DIALOG MODAL                                -->
      <!-- ========================================================= -->
      <Dialog :open="isDetailsModalOpen" @update:open="isDetailsModalOpen = $event">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg">
          <DialogHeader v-if="selectedOrderForModal">
            <div class="flex items-center justify-between gap-3 flex-wrap pr-6">
              <div class="flex items-center gap-2.5 flex-wrap">
                <DialogTitle class="text-xl font-mono font-bold">{{ selectedOrderForModal.order_number }}</DialogTitle>
                <Badge
                  variant="outline"
                  :class="[
                    'text-xs font-bold px-2.5 py-0.5 rounded-md border',
                    getStatusBadge(selectedOrderForModal.status, selectedOrderForModal.fulfillment_type).class,
                  ]"
                >
                  {{ getStatusBadge(selectedOrderForModal.status, selectedOrderForModal.fulfillment_type).label }}
                </Badge>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="printOrderTicket(selectedOrderForModal)"
                  class="h-8 px-3 rounded-md text-xs font-semibold text-[#09090b] bg-white hover:bg-[#f4f4f5] border border-[#e4e4e7] inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                >
                  <Printer class="w-3.5 h-3.5 text-zinc-700" />
                  <span>Print Slip</span>
                </button>

                <button
                  v-if="selectedOrderForModal.status !== 'cancelled' && selectedOrderForModal.status !== 'completed'"
                  type="button"
                  @click="confirmCancelOrder(selectedOrderForModal)"
                  class="h-8 px-3 rounded-md text-xs font-semibold text-rose-700 bg-white hover:bg-rose-600 hover:text-white border border-rose-300 hover:border-rose-600 inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                >
                  <Ban class="w-3.5 h-3.5" />
                  <span>Cancel &amp; Refund</span>
                </button>
              </div>
            </div>

            <DialogDescription class="text-xs text-zinc-500 pt-1 flex items-center gap-1.5 flex-wrap">
              <span>Placed {{ getLiveRelativeTime(selectedOrderForModal) }} · Payment via {{ selectedOrderForModal.payment_method }}</span>
              <button
                v-if="selectedOrderForModal.stripe_payment_id"
                type="button"
                @click="copyText(selectedOrderForModal.stripe_payment_id, 'Stripe Payment ID copied')"
                class="font-mono text-emerald-700 hover:underline inline-flex items-center gap-1 cursor-pointer"
                title="Click to copy Stripe PaymentIntent ID"
              >
                <span>({{ selectedOrderForModal.stripe_payment_id }})</span>
                <Copy class="w-3 h-3" />
              </button>
            </DialogDescription>
          </DialogHeader>

          <div v-if="selectedOrderForModal" class="space-y-4 py-2 text-xs">

            <!-- Interactive 4-Step Order Progress Switcher -->
            <div v-if="selectedOrderForModal.status !== 'cancelled' && selectedOrderForModal.status !== 'completed'" class="space-y-1.5">
              <div class="text-[10px] font-mono uppercase tracking-wider text-zinc-400 font-semibold px-1">
                Order Stage (Click any stage to switch status)
              </div>
              <div class="grid grid-cols-4 gap-2 p-2.5 bg-[#fafafa] rounded-lg border border-[#e4e4e7]">
                <button
                  v-for="step in workflowSteps"
                  :key="step.key"
                  type="button"
                  :disabled="updatingOrderId === selectedOrderForModal.id || selectedOrderForModal.status === step.key || selectedOrderForModal.status === 'completed'"
                  @click="updateOrderStatus(selectedOrderForModal, step.key)"
                  class="text-center py-2 px-2 rounded-md text-[11px] font-bold transition-all cursor-pointer disabled:cursor-default"
                  :class="selectedOrderForModal.status === step.key
                    ? 'bg-[#18181b] text-[#a1a1aa] shadow-2xs'
                    : getStepIndex(selectedOrderForModal.status) > step.stepIndex
                      ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'
                      : 'text-zinc-600 bg-white border border-[#e4e4e7]/60 hover:border-[#18181b]'"
                >
                  {{ step.fullLabel }}
                </button>
              </div>
            </div>

            <!-- Customer & Fulfillment Card -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Customer Details -->
              <div class="bg-[#fafafa] border border-[#e4e4e7] rounded-lg p-3.5 space-y-1.5">
                <div class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Customer</div>
                <div class="font-bold text-sm text-[#09090b]">{{ selectedOrderForModal.customer_name }}</div>
                <div class="text-zinc-600 flex flex-col gap-1 text-xs">
                  <a
                    v-if="selectedOrderForModal.customer_email"
                    :href="'mailto:' + selectedOrderForModal.customer_email"
                    class="flex items-center gap-1.5 hover:text-[#09090b] hover:underline"
                  >
                    <Mail class="w-3 h-3 text-zinc-400" />
                    <span>{{ selectedOrderForModal.customer_email }}</span>
                  </a>
                  <a
                    v-if="selectedOrderForModal.customer_phone"
                    :href="'tel:' + selectedOrderForModal.customer_phone"
                    class="flex items-center gap-1.5 text-[#18181b] font-semibold hover:underline"
                  >
                    <Phone class="w-3 h-3" />
                    <span>{{ selectedOrderForModal.customer_phone }}</span>
                  </a>
                </div>
              </div>

              <!-- Fulfillment Details -->
              <div class="bg-[#fafafa] border border-[#e4e4e7] rounded-lg p-3.5 space-y-1.5">
                <div class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Fulfillment</div>
                <div class="font-bold text-sm text-[#09090b] flex items-center gap-1.5">
                  <Truck v-if="selectedOrderForModal.fulfillment_type === 'Home Delivery'" class="w-4 h-4 text-blue-600" />
                  <ShoppingBag v-else class="w-4 h-4 text-[#18181b]" />
                  <span>{{ selectedOrderForModal.fulfillment_type }}</span>
                </div>

                <div v-if="selectedOrderForModal.fulfillment_type === 'Home Delivery' && selectedOrderForModal.delivery_address" class="text-zinc-600 text-xs space-y-1">
                  <div class="font-semibold text-zinc-900">Delivery Street Address:</div>
                  <div class="flex items-start justify-between gap-2">
                    <span>{{ selectedOrderForModal.delivery_address }}</span>
                    <div class="flex items-center gap-2 shrink-0">
                      <a
                        :href="'https://maps.google.com/?q=' + encodeURIComponent(selectedOrderForModal.delivery_address)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-blue-600 hover:underline text-[11px] font-semibold inline-flex items-center gap-0.5"
                      >
                        <span>Maps</span>
                        <ExternalLink class="w-2.5 h-2.5" />
                      </a>
                      <button
                        type="button"
                        @click="copyText(selectedOrderForModal.delivery_address, 'Address copied')"
                        class="text-[#18181b] hover:underline text-[11px] font-semibold shrink-0 cursor-pointer"
                      >
                        Copy
                      </button>
                    </div>
                  </div>
                </div>
                <div v-else class="text-zinc-600 text-xs">
                  <div class="font-semibold text-zinc-900">Pickup Window:</div>
                  <div>{{ selectedOrderForModal.pickup_slot }}</div>
                  <div class="text-[11px] text-zinc-500">{{ selectedOrderForModal.pickup_location }}</div>
                </div>
              </div>
            </div>

            <!-- Customer Notes if any -->
            <div v-if="selectedOrderForModal.notes" class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-950 flex items-start gap-2">
              <MessageSquareWarning class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
              <div><span class="font-bold">Order Notes:</span> {{ selectedOrderForModal.notes }}</div>
            </div>

            <!-- Interactive Packing Checklist Table -->
            <div>
              <div class="font-bold text-xs uppercase tracking-wider text-zinc-500 mb-2 flex items-center justify-between">
                <span>
                  Order Items ({{ packedCountForOrder(selectedOrderForModal) }}/{{ packableItemsForOrder(selectedOrderForModal).length }} Packed · {{ totalUnitsForOrder(selectedOrderForModal) }} Active Units)
                </span>
                <button
                  v-if="packableItemsForOrder(selectedOrderForModal).length > 0"
                  type="button"
                  @click="markAllItemsPacked(selectedOrderForModal)"
                  class="text-[11px] text-[#18181b] hover:underline font-bold normal-case cursor-pointer"
                >
                  {{ packedCountForOrder(selectedOrderForModal) === packableItemsForOrder(selectedOrderForModal).length ? 'Uncheck All' : 'Mark All Packed ✓' }}
                </button>
              </div>
              <div class="border border-[#e4e4e7] rounded-lg divide-y divide-[#e4e4e7] overflow-hidden">
                <div
                  v-for="item in selectedOrderForModal.items"
                  :key="item.id"
                  class="w-full p-3 flex items-center justify-between text-xs gap-3 transition-colors"
                  :class="isItemFullyRefunded(item) ? 'bg-rose-50/40' : 'bg-white hover:bg-[#fafafa]'"
                >
                  <button
                    type="button"
                    :disabled="isItemFullyRefunded(item)"
                    @click="toggleItemPacked(selectedOrderForModal.id, item.id)"
                    class="flex items-center gap-3 min-w-0 flex-1 text-left"
                    :class="isItemFullyRefunded(item) ? 'cursor-default' : 'cursor-pointer'"
                  >
                    <Ban v-if="isItemFullyRefunded(item)" class="w-4 h-4 text-rose-500 shrink-0" />
                    <CheckSquare v-else-if="isItemPacked(selectedOrderForModal.id, item.id)" class="w-4 h-4 text-emerald-600 shrink-0" />
                    <Square v-else class="w-4 h-4 text-zinc-300 shrink-0" />
                    <div class="w-9 h-9 rounded-md bg-[#f4f4f5] border border-[#e4e4e7] overflow-hidden shrink-0 flex items-center justify-center">
                      <img
                        v-if="item.image"
                        :src="item.image"
                        :alt="item.name"
                        class="w-full h-full object-cover"
                      />
                      <Package v-else class="w-4 h-4 text-[#18181b]" />
                    </div>
                    <span
                      class="font-mono font-bold px-2 h-7 rounded-md flex items-center justify-center text-xs shrink-0 border"
                      :class="isItemFullyRefunded(item)
                        ? 'bg-rose-100 text-rose-800 border-rose-300 line-through'
                        : activeItemQuantity(item) > 1
                          ? 'bg-amber-100 text-amber-950 border-amber-400'
                          : 'bg-[#f4f4f5] text-zinc-900 border-zinc-200'"
                    >
                      {{ isItemFullyRefunded(item) ? item.quantity : isItemPartiallyRefunded(item) ? `${activeItemQuantity(item)}/${item.quantity}` : activeItemQuantity(item) }}×
                    </span>
                    <div class="truncate">
                      <div
                        class="font-bold truncate"
                        :class="isItemFullyRefunded(item)
                          ? 'line-through text-rose-800'
                          : isItemPacked(selectedOrderForModal.id, item.id)
                            ? 'line-through text-zinc-400'
                            : 'text-zinc-900'"
                      >
                        {{ item.name }}
                      </div>
                      <div class="text-[11px] text-zinc-500 flex items-center gap-1.5 flex-wrap mt-0.5">
                        <span>{{ item.size || 'Standard package' }} · ${{ Number(item.unit_price).toFixed(2) }} each</span>
                        <span
                          v-if="isItemFullyRefunded(item)"
                          class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 border border-rose-200"
                        >
                          Out of Stock · {{ item.refunded_quantity || item.quantity }}× Refunded (-${{ Number(item.refunded_amount || item.total_price).toFixed(2) }})
                        </span>
                        <span
                          v-else-if="isItemPartiallyRefunded(item)"
                          class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-900 border border-amber-300"
                        >
                          {{ item.refunded_quantity }}× OOS Refunded (-${{ Number(item.refunded_amount).toFixed(2) }})
                        </span>
                      </div>
                    </div>
                  </button>
                  <div class="flex items-center gap-2 shrink-0">
                    <button
                      v-if="['confirmed', 'packing', 'ready_for_pickup'].includes(selectedOrderForModal.status) && !isItemFullyRefunded(item)"
                      type="button"
                      @click.stop="openItemRefundModal(selectedOrderForModal, item)"
                      class="h-7 px-2.5 rounded-md text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white border border-rose-200 hover:border-rose-600 transition-colors cursor-pointer"
                    >
                      {{ isItemPartiallyRefunded(item) ? `OOS / Refund (${activeItemQuantity(item)} left)` : 'OOS / Refund' }}
                    </button>
                    <div
                      class="font-mono font-medium text-right shrink-0"
                      :class="isItemFullyRefunded(item) ? 'line-through text-rose-400' : 'text-zinc-900'"
                    >
                      ${{ (isItemFullyRefunded(item) ? Number(item.total_price) : Math.max(0, Number(item.total_price) - Number(item.refunded_amount || 0))).toFixed(2) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Financials Breakdown -->
            <div class="bg-[#fafafa] p-3.5 rounded-lg border border-zinc-200 space-y-1.5 text-xs text-zinc-600">
              <div class="flex justify-between">
                <span>Subtotal:</span>
                <span class="font-mono text-zinc-900">${{ Number(selectedOrderForModal.subtotal).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>{{ selectedOrderForModal.fulfillment_type === 'Home Delivery' ? 'Home Delivery Fee' : 'Store Pickup (Curbside)' }}:</span>
                <span class="font-mono" :class="Number(selectedOrderForModal.delivery_fee || 0) > 0 ? 'text-zinc-900' : 'text-emerald-700 font-bold'">
                  {{ Number(selectedOrderForModal.delivery_fee || 0) > 0 ? `$${Number(selectedOrderForModal.delivery_fee).toFixed(2)}` : 'FREE' }}
                </span>
              </div>
              <div v-if="Number(selectedOrderForModal.discount || 0) > 0" class="flex justify-between text-emerald-700">
                <span>Discount:</span>
                <span class="font-mono">-${{ Number(selectedOrderForModal.discount).toFixed(2) }}</span>
              </div>
              <div v-if="Number(selectedOrderForModal.refunded_total || 0) > 0" class="flex justify-between text-rose-700 font-semibold">
                <span>Refunded (Out-of-Stock / Cancelled):</span>
                <span class="font-mono">-${{ Number(selectedOrderForModal.refunded_total).toFixed(2) }}</span>
              </div>
              <div v-if="selectedOrderForModal.points_earned" class="flex justify-between text-[#27272a]">
                <span class="inline-flex items-center gap-1">
                  <Award class="w-3.5 h-3.5 text-[#18181b]" />
                  <span>Loyalty Points Earned:</span>
                </span>
                <span class="font-mono font-bold">+{{ selectedOrderForModal.points_earned }} pts</span>
              </div>
              <Separator class="my-1.5" />
              <div class="flex justify-between text-sm font-bold text-zinc-950">
                <span>{{ Number(selectedOrderForModal.refunded_total || 0) > 0 ? 'Net Charged (After Refund):' : 'Total Charged:' }}</span>
                <span class="font-semibold text-base text-[#18181b]">${{ effectiveOrderTotal(selectedOrderForModal).toFixed(2) }}</span>
              </div>
            </div>

          </div>

          <DialogFooter v-if="selectedOrderForModal" class="flex items-center justify-between sm:justify-between w-full pt-2">
            <Button
              type="button"
              variant="outline"
              @click="isDetailsModalOpen = false"
              class="rounded-md text-xs cursor-pointer"
            >
              Close
            </Button>

            <!-- Quick advance action from modal -->
            <div v-if="getNextStatusInfo(selectedOrderForModal)">
              <Button
                type="button"
                :disabled="updatingOrderId === selectedOrderForModal.id"
                @click="updateOrderStatus(selectedOrderForModal, getNextStatusInfo(selectedOrderForModal).nextStatus); isDetailsModalOpen = false"
                :class="[
                  'h-9 px-5 rounded-md text-xs font-bold shadow-xs gap-1.5 cursor-pointer disabled:opacity-50',
                  getNextStatusInfo(selectedOrderForModal).badgeClass,
                ]"
              >
                <component :is="getNextStatusInfo(selectedOrderForModal).icon" class="w-3.5 h-3.5" />
                <span>{{ getNextStatusInfo(selectedOrderForModal).label }}</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- ========================================================= -->
      <!-- THERMAL PACKING TICKET PRINT MODAL                        -->
      <!-- ========================================================= -->
      <Dialog :open="isPrintModalOpen" @update:open="isPrintModalOpen = $event">
        <DialogContent class="max-w-md max-h-[90vh] overflow-y-auto rounded-lg">
          <DialogHeader v-if="orderToPrint">
            <DialogTitle class="text-base font-bold">Kitchen Packing Slip</DialogTitle>
            <DialogDescription class="text-xs">Thermal receipt preview for store packing &amp; dispatch</DialogDescription>
          </DialogHeader>

          <!-- Printable Ticket Body -->
          <div v-if="orderToPrint" id="printable-kitchen-ticket" class="p-4 bg-white border border-dashed border-zinc-300 rounded-lg font-mono text-xs text-zinc-900 space-y-3">
            <div class="text-center pb-2 border-b border-dashed border-zinc-300">
              <div class="font-bold text-base tracking-wider">MASALA MART</div>
              <div class="text-[11px] text-zinc-600">Kitchen &amp; Dispatch Ticket</div>
              <div class="text-[11px] text-zinc-600 font-sans mt-0.5">{{ new Date().toLocaleString() }}</div>
            </div>

            <div class="flex justify-between items-center text-sm font-bold pb-2 border-b border-zinc-200">
              <span>{{ orderToPrint.order_number }}</span>
              <span class="text-xs font-sans px-2 py-0.5 bg-zinc-100 rounded">
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

            <div class="pt-2 border-t border-dashed border-zinc-300">
              <div class="font-bold uppercase text-[10px] text-zinc-500 mb-1.5">
                Items to Pack ({{ totalUnitsForOrder(orderToPrint) }} units):
              </div>
              <div class="space-y-1.5 divide-y divide-zinc-100">
                <div
                  v-for="item in orderToPrint.items"
                  :key="item.id"
                  class="pt-1.5 flex justify-between items-start"
                >
                  <div>
                    <span class="font-bold text-base mr-1.5">
                      [{{ isItemFullyRefunded(item) ? 'OOS' : `${activeItemQuantity(item)}×` }}]
                    </span>
                    <span class="font-sans font-bold" :class="isItemFullyRefunded(item) ? 'line-through text-zinc-500' : ''">
                      {{ item.name }}
                    </span>
                    <div class="text-[10px] text-zinc-500 font-sans pl-6">
                      {{ item.size }}
                      <span v-if="Number(item.refunded_quantity || 0) > 0" class="font-bold text-rose-700">
                        ({{ item.refunded_quantity }}× OOS Refunded -${{ Number(item.refunded_amount).toFixed(2) }})
                      </span>
                    </div>
                  </div>
                  <span class="shrink-0">${{ Number(item.total_price - Number(item.refunded_amount || 0)).toFixed(2) }}</span>
                </div>
              </div>
            </div>

            <div v-if="orderToPrint.notes" class="p-2 bg-zinc-50 border border-zinc-200 rounded text-[11px] font-sans">
              <strong>Note:</strong> {{ orderToPrint.notes }}
            </div>

            <div class="pt-2 border-t border-dashed border-zinc-300 space-y-1 text-xs">
              <div v-if="Number(orderToPrint.refunded_total || 0) > 0" class="flex justify-between text-rose-700 font-semibold">
                <span>OOS Refunded:</span>
                <span>-${{ Number(orderToPrint.refunded_total).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between items-center font-bold text-sm">
                <span>Total Amount:</span>
                <span>${{ effectiveOrderTotal(orderToPrint).toFixed(2) }} (PAID)</span>
              </div>
            </div>
          </div>

          <DialogFooter v-if="orderToPrint" class="flex gap-2">
            <Button type="button" variant="outline" @click="isPrintModalOpen = false" class="rounded-md text-xs cursor-pointer">
              Close
            </Button>
            <Button type="button" @click="triggerPrint" class="rounded-md text-xs font-bold bg-[#18181b] text-white gap-1.5 cursor-pointer">
              <Printer class="w-4 h-4" />
              <span>Print Ticket</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- ========================================================= -->
      <!-- CANCEL ORDER & AUTOMATIC STRIPE REFUND CONFIRMATION MODAL -->
      <!-- ========================================================= -->
      <Dialog :open="isCancelModalOpen" @update:open="isCancelModalOpen = $event">
        <DialogContent class="max-w-md rounded-lg">
          <DialogHeader v-if="orderToCancel">
            <DialogTitle class="text-base font-bold text-rose-800 flex items-center gap-2">
              <AlertCircle class="w-5 h-5 text-rose-600" />
              <span>Cancel Order {{ orderToCancel.order_number }}?</span>
            </DialogTitle>
            <DialogDescription class="text-xs text-zinc-600 pt-1">
              Cancelling this order will immediately return its reserved items back to inventory stock
              {{ orderToCancel.stripe_payment_id
                ? "and issue an automatic refund of the remaining balance to the customer's card via Stripe."
                : 'and mark the order as cancelled.' }}
            </DialogDescription>
          </DialogHeader>

          <div v-if="orderToCancel" class="p-3.5 bg-rose-50 border border-rose-200 rounded-lg text-xs space-y-1.5 text-rose-950">
            <div class="flex justify-between">
              <span class="text-rose-700">Customer:</span>
              <span class="font-bold">{{ orderToCancel.customer_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-rose-700">Refund Amount:</span>
              <span class="font-semibold text-sm">${{ effectiveOrderTotal(orderToCancel).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-rose-700">Inventory Action:</span>
              <span class="font-semibold">{{ totalUnitsForOrder(orderToCancel) }} active unit(s) restocked</span>
            </div>
            <div v-if="orderToCancel.stripe_payment_id" class="flex justify-between">
              <span class="text-rose-700">Stripe Payment ID:</span>
              <span class="font-mono text-[11px]">{{ orderToCancel.stripe_payment_id }}</span>
            </div>
          </div>

          <DialogFooter class="flex gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              :disabled="isCancelling"
              @click="isCancelModalOpen = false"
              class="rounded-md text-xs cursor-pointer"
            >
              Keep Order
            </Button>
            <Button
              type="button"
              :disabled="isCancelling"
              @click="executeCancelOrder"
              class="rounded-md text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white gap-1.5 cursor-pointer"
            >
              <Loader2 v-if="isCancelling" class="w-3.5 h-3.5 animate-spin" />
              <Ban v-else class="w-3.5 h-3.5" />
              <span>{{ orderToCancel?.stripe_payment_id ? 'Confirm & Refund via Stripe' : 'Confirm Cancellation' }}</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- ========================================================= -->
      <!-- ITEM OUT-OF-STOCK & PARTIAL STRIPE REFUND MODAL           -->
      <!-- ========================================================= -->
      <Dialog :open="isItemRefundModalOpen" @update:open="isItemRefundModalOpen = $event">
        <DialogContent class="max-w-md rounded-lg">
          <DialogHeader v-if="itemToRefund && orderForItemRefund">
            <DialogTitle class="text-base font-bold text-rose-800 flex items-center gap-2">
              <AlertCircle class="w-5 h-5 text-rose-600" />
              <span>Item Out of Stock · Partial Refund</span>
            </DialogTitle>
            <DialogDescription class="text-xs text-zinc-600 pt-1">
              Issue a partial refund for <strong>{{ itemToRefund.name }}</strong> on order <strong>{{ orderForItemRefund.order_number }}</strong> and keep the rest of the order moving.
            </DialogDescription>
          </DialogHeader>

          <div v-if="itemToRefund && orderForItemRefund" class="space-y-3.5 text-xs">
            <!-- Item Summary Box -->
            <div class="p-3 bg-zinc-50 border border-zinc-200 rounded-lg flex items-center justify-between gap-3">
              <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-md bg-white border border-zinc-200 overflow-hidden shrink-0 flex items-center justify-center">
                  <img v-if="itemToRefund.image" :src="itemToRefund.image" :alt="itemToRefund.name" class="w-full h-full object-cover" />
                  <Package v-else class="w-4 h-4 text-zinc-700" />
                </div>
                <div class="min-w-0">
                  <div class="font-bold text-zinc-900 truncate">{{ itemToRefund.name }}</div>
                  <div class="text-[11px] text-zinc-500">
                    {{ activeItemQuantity(itemToRefund) }}× active · ${{ Number(itemToRefund.unit_price).toFixed(2) }} each
                  </div>
                </div>
              </div>
              <div class="text-right shrink-0">
                <div class="text-[10px] uppercase font-bold text-rose-600">Refund</div>
                <div class="font-mono font-bold text-sm text-rose-700">${{ estimatedItemRefundAmount.toFixed(2) }}</div>
              </div>
            </div>

            <!-- Quantity Selector (when customer ordered > 1 of this item) -->
            <div v-if="maxRefundableItemQty > 1" class="space-y-1.5">
              <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-700">
                How many units are Out of Stock?
              </label>
              <div class="flex items-center gap-2">
                <button
                  v-for="qty in maxRefundableItemQty"
                  :key="qty"
                  type="button"
                  @click="refundQtyInput = qty"
                  class="px-3 py-1.5 rounded-md font-mono font-bold text-xs border transition-colors cursor-pointer"
                  :class="refundQtyInput === qty
                    ? 'bg-rose-600 text-white border-rose-600'
                    : 'bg-white text-zinc-800 border-zinc-200 hover:border-zinc-400'"
                >
                  {{ qty }}× (${{ Math.min(effectiveOrderTotal(orderForItemRefund), Number(itemToRefund.unit_price) * qty).toFixed(2) }})
                </button>
              </div>
            </div>

            <!-- Auto-Zero Store Stock Checkbox -->
            <label class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50/80 border border-amber-200 cursor-pointer">
              <input
                v-model="zeroProductStockInput"
                type="checkbox"
                class="mt-0.5 rounded border-amber-400 text-zinc-900 focus:ring-zinc-900"
              />
              <div class="text-[11px] leading-snug text-amber-950">
                <span class="font-bold block">Mark product as Out of Stock (0 inventory) on storefront</span>
                <span class="text-amber-800">Prevents new customers from ordering this item until restocked.</span>
              </div>
            </label>

            <!-- Reason Input -->
            <div class="space-y-1">
              <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-700">
                Reason / Order Note
              </label>
              <Input
                v-model="refundReasonInput"
                type="text"
                placeholder="Out of stock during packing"
                class="h-9 text-xs rounded-md"
              />
            </div>
          </div>

          <DialogFooter class="flex gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              :disabled="isRefundingItem"
              @click="isItemRefundModalOpen = false"
              class="rounded-md text-xs cursor-pointer"
            >
              Cancel
            </Button>
            <Button
              type="button"
              :disabled="isRefundingItem"
              @click="executeItemRefund"
              class="rounded-md text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white gap-1.5 cursor-pointer"
            >
              <Loader2 v-if="isRefundingItem" class="w-3.5 h-3.5 animate-spin" />
              <Ban v-else class="w-3.5 h-3.5" />
              <span>Refund ${{ estimatedItemRefundAmount.toFixed(2) }} &amp; Mark OOS</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AdminLayout>
</template>
