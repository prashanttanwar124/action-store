<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { 
  Ban,
  Check,
  CheckCircle2, 
  Clock, 
  MapPin, 
  ShoppingBag, 
  Truck, 
  Sparkles, 
  Phone, 
  Store, 
  ChevronRight,
  Package,
  Car
} from 'lucide-vue-next';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';

const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
});

const currentStatus = ref(props.order.status);
const liveRefundedTotal = ref(Number(props.order.refunded_total || 0));
const liveItems = ref((props.order.items || []).map((item) => ({ ...item })));
const isReverbConnected = ref(false);

const isCancelled = computed(() => currentStatus.value === 'cancelled');
const isCompleted = computed(() => currentStatus.value === 'completed');

const effectiveOrderTotal = computed(() => {
  if (isCancelled.value) return 0;
  return Math.max(0, Number(props.order.total || 0) - Number(liveRefundedTotal.value || 0));
});

const isOrderFullyRefunded = computed(() => {
  return isCancelled.value || (effectiveOrderTotal.value <= 0 && liveRefundedTotal.value > 0);
});

function isItemFullyRefunded(item) {
  return isCancelled.value || Number(item?.refunded_quantity || 0) >= Number(item?.quantity || 1);
}

function isItemPartiallyRefunded(item) {
  if (isCancelled.value) return false;
  const refunded = Number(item?.refunded_quantity || 0);
  return refunded > 0 && refunded < Number(item?.quantity || 1);
}

function activeItemQty(item) {
  return Math.max(0, Number(item?.quantity || 1) - Number(item?.refunded_quantity || 0));
}

const page = usePage();
const storeInfo = computed(() => page.props.storeInfo || {});

const isDelivery = computed(() => {
  const type = String(props.order.fulfillment_type || '').toLowerCase();
  return type.includes('delivery');
});

const steps = computed(() => {
  if (isDelivery.value) {
    return [
      { id: 'confirmed', label: 'Order Received', desc: `Sent to ${storeInfo.value.brand_name} store` },
      { id: 'packing', label: 'Being Picked & Packed', desc: 'Hand-selecting fresh groceries' },
      { id: 'ready_for_pickup', label: 'Out for Delivery', desc: 'Driver is on the way to your address' },
      { id: 'completed', label: 'Delivered', desc: 'Safely delivered to your door' },
    ];
  }
  return [
    { id: 'confirmed', label: 'Order Received', desc: `Sent to ${storeInfo.value.brand_name} store` },
    { id: 'packing', label: 'Being Picked & Packed', desc: 'Hand-selecting fresh groceries' },
    { id: 'ready_for_pickup', label: 'Ready for Pickup', desc: 'Park in Curbside Bay 3' },
    { id: 'completed', label: 'Completed', desc: 'Handed over to customer' },
  ];
});

const currentStepIndex = computed(() => {
  switch (currentStatus.value) {
    case 'confirmed':
      return 0;
    case 'packing':
      return 1;
    case 'ready_for_pickup':
      return 2;
    case 'completed':
      return 3;
    case 'cancelled':
      return -1;
    default:
      return 0;
  }
});

function isStepCompleted(idx) {
  if (isCancelled.value) return false;
  if (isCompleted.value) return true;
  return idx < currentStepIndex.value;
}

function isStepInProgress(idx) {
  if (isCancelled.value || isCompleted.value) return false;
  return idx === currentStepIndex.value;
}

function isStepReached(idx) {
  if (isCancelled.value) return false;
  return idx <= currentStepIndex.value;
}

// Clean channel name (Pusher/Reverb does not allow '#' in channel names)
const orderChannelName = computed(() => {
  const clean = String(props.order.order_number || props.order.id).replace(/^#/, '');
  return `orders.${clean}`;
});

// Real-time listener for customer order updates
onMounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    try {
      window.Echo.private(orderChannelName.value)
        .listen('.order.status.updated', (payload) => {
          currentStatus.value = payload.status;
          if (payload.status === 'cancelled' && !liveRefundedTotal.value) {
            liveRefundedTotal.value = Number(props.order.total || 0);
          }
        })
        .listen('.order.items.packed', (payload) => {
          if (payload?.status) {
            currentStatus.value = payload.status;
          }
          if (typeof payload?.refunded_total !== 'undefined') {
            liveRefundedTotal.value = Number(payload.refunded_total || 0);
          }
          if (Array.isArray(payload?.items)) {
            const incomingById = new Map(payload.items.map((i) => [Number(i.id), i]));
            liveItems.value = liveItems.value.map((item) => {
              const incoming = incomingById.get(Number(item.id));
              if (!incoming) return item;
              return {
                ...item,
                refunded_quantity: Number(incoming.refunded_quantity || 0),
                refunded_amount: Number(incoming.refunded_amount || 0),
                refund_reason: incoming.refund_reason || null,
              };
            });
          }
        });
      isReverbConnected.value = true;
    } catch (e) {
      console.warn('Echo listener error:', e);
    }
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leave(orderChannelName.value);
  }
});
</script>

<template>
  <Head :title="`Order ${order.order_number} Tracking — ${storeInfo.brand_name}`" />

  <StoreLayout :showHeader="true" :showFooter="true" :showBottomNav="true" headerMode="simple" :headerTitle="'Order ' + order.order_number">
    <div class="max-w-3xl mx-auto py-6 space-y-6 pb-28">

      <!-- HERO STATUS BANNER -->
      <div
        class="p-6 sm:p-8 rounded-lg border text-center space-y-3 relative overflow-hidden shadow-xs"
        :class="isCancelled ? 'bg-rose-50/70 border-rose-200' : 'bg-[#f3efe7] border-[#e0d9cc]'"
      >
        <div
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-white border shadow-2xs"
          :class="isCancelled ? 'border-rose-200 text-rose-800' : 'border-[#e0d9cc] text-[#1d1d1f]'"
        >
          <span
            class="w-2 h-2 rounded-full"
            :class="isCancelled ? 'bg-rose-500' : isCompleted ? 'bg-emerald-600' : 'bg-emerald-500 animate-pulse'"
          ></span>
          <span>
            {{ isCancelled ? 'Order Cancelled · Refund Processed' : isCompleted ? 'Order Fulfilled' : 'Live Order Tracking · Auto-Updates' }}
          </span>
        </div>

        <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          {{ 
            isCancelled
              ? 'Order Cancelled & Refunded'
              : currentStatus === 'ready_for_pickup' 
                ? (isDelivery ? 'Out for Delivery!' : 'Your Order is Ready for Pickup!') 
                : (currentStatus === 'packing' 
                    ? 'Our Team is Picking Your Items' 
                    : (isCompleted 
                        ? (isDelivery ? 'Order Delivered · Thank you!' : 'Order Picked Up · Thank you!') 
                        : 'Order Placed Successfully'))
          }}
        </h1>

        <p class="text-xs sm:text-sm text-[#6e6e73] max-w-md mx-auto">
          <template v-if="isCancelled">
            This order was cancelled and <strong class="text-rose-800 font-semibold">${{ Number(liveRefundedTotal || order.total || 0).toFixed(2) }}</strong> has been refunded to your original payment method.
          </template>
          <template v-else-if="isDelivery">
            Delivery: <strong class="text-[#1d1d1f] font-semibold">{{ order.delivery_window || order.pickup_slot || 'Scheduled Day' }}</strong>
            <span v-if="order.delivery_address || order.shipping_address"> to {{ order.delivery_address || order.shipping_address }}</span>
          </template>
          <template v-else>
            Pickup Slot: <strong class="text-[#1d1d1f] font-semibold">{{ order.pickup_slot }}</strong> at {{ order.pickup_location }}
          </template>
        </p>
      </div>

      <!-- PARTIAL REFUND NOTICE BANNER -->
      <div
        v-if="Boolean(liveRefundedTotal) && !isCancelled"
        class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-950 text-xs flex items-center justify-between gap-3 shadow-2xs"
      >
        <div>
          <div class="font-bold text-rose-900">Automatic Refund Issued (-${{ liveRefundedTotal.toFixed(2) }})</div>
          <p class="text-rose-800 mt-0.5">
            One or more out-of-stock items were automatically refunded back to your original payment method.
          </p>
        </div>
        <span class="px-2.5 py-1 rounded-md bg-white border border-rose-200 font-mono font-bold text-rose-800 shrink-0">
          -${{ liveRefundedTotal.toFixed(2) }}
        </span>
      </div>

      <!-- CANCELLED STATUS CARD (Replaces active stepper when order is cancelled) -->
      <Card v-if="isCancelled" class="bg-white border-rose-200 rounded-lg shadow-xs p-6">
        <CardContent class="p-0">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-md bg-rose-100 border-2 border-rose-300 text-rose-700 flex items-center justify-center shrink-0">
              <Ban class="w-5 h-5 stroke-[2.2]" />
            </div>
            <div class="pt-1 flex-1 space-y-1">
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm sm:text-base font-semibold text-rose-900">
                  Order Cancelled &amp; Refunded
                </h3>
                <Badge variant="outline" class="text-[10px] font-bold bg-rose-50 text-rose-800 border-rose-300">
                  Cancelled
                </Badge>
              </div>
              <p class="text-xs text-[#6e6e73] leading-relaxed">
                All items in this order were out of stock or cancelled. A full refund of <strong>${{ Number(liveRefundedTotal || order.total || 0).toFixed(2) }}</strong> has been issued to your original payment method.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- VISUAL STEPPER TRACKER (Active / Completed Orders Only) -->
      <Card v-else class="bg-white border-[#e0d9cc] rounded-lg shadow-xs p-6">
        <CardContent class="p-0">
          <div class="relative">
            
            <!-- Progress Line -->
            <div class="absolute left-6 top-6 bottom-6 w-0.5 bg-[#e0d9cc] hidden sm:block"></div>
            
            <div class="space-y-6 sm:space-y-8">
              <div 
                v-for="(step, idx) in steps" 
                :key="step.id"
                class="flex items-start gap-4 relative"
              >
                <!-- Step Circle -->
                <div 
                  class="w-12 h-12 rounded-md flex items-center justify-center shrink-0 border-2 transition-all duration-300 z-10 shadow-xs"
                  :class="[
                    isStepCompleted(idx) 
                      ? 'bg-[#1a1a1a] border-[#1a1a1a] text-white' 
                      : (isStepInProgress(idx) 
                          ? 'bg-[#a47a3c] border-[#a47a3c] text-white animate-pulse' 
                          : 'bg-[#fbf9f5] border-[#e0d9cc] text-[#86868b]')
                  ]"
                >
                  <Check v-if="isStepCompleted(idx)" class="w-5 h-5 stroke-[2.5]" />
                  <Clock v-else-if="isStepInProgress(idx)" class="w-5 h-5 stroke-[2.2]" />
                  <span v-else class="text-xs font-bold">{{ idx + 1 }}</span>
                </div>

                <!-- Step Info -->
                <div class="pt-1.5 flex-1">
                  <div class="flex items-center justify-between">
                    <h3 
                      class="text-sm sm:text-base font-semibold transition-colors"
                      :class="isStepReached(idx) ? 'text-[#1d1d1f]' : 'text-[#86868b]'"
                    >
                      {{ step.label }}
                    </h3>
                    <Badge 
                      v-if="isStepInProgress(idx)" 
                      variant="outline"
                      class="text-[10px] font-bold bg-amber-50 text-amber-800 border-amber-300"
                    >
                      In Progress
                    </Badge>
                    <Badge 
                      v-else-if="isCompleted && idx === 3" 
                      variant="outline"
                      class="text-[10px] font-bold bg-emerald-50 text-emerald-800 border-emerald-300"
                    >
                      Completed
                    </Badge>
                  </div>
                  <p class="text-xs text-[#6e6e73] mt-0.5">
                    {{ step.desc }}
                  </p>
                </div>
              </div>
            </div>

          </div>
        </CardContent>
      </Card>

      <!-- FULFILLMENT INSTRUCTIONS BOX (Only when order is not cancelled) -->
      <template v-if="!isCancelled">
        <div v-if="isDelivery" class="p-5 rounded-lg bg-[#fbfaf8] border border-[#e0d9cc] flex items-start gap-4 shadow-2xs">
          <div class="w-10 h-10 rounded-lg bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0 border border-[#e0d9cc]">
            <Truck class="w-5 h-5" />
          </div>
          <div class="space-y-1 text-xs">
            <div class="font-semibold text-sm text-[#1d1d1f]">Home Delivery Details</div>
            <p class="text-[#6e6e73] leading-relaxed">
              Your fresh grocery items will be safely delivered to <strong>{{ order.delivery_address || order.shipping_address || 'your address' }}</strong>. Please ensure someone is available or instructions are left for contact-free drop off.
            </p>
          </div>
        </div>

        <div v-else class="p-5 rounded-lg bg-[#fbfaf8] border border-[#e0d9cc] flex items-start gap-4 shadow-2xs">
          <div class="w-10 h-10 rounded-lg bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0 border border-[#e0d9cc]">
            <Car class="w-5 h-5" />
          </div>
          <div class="space-y-1 text-xs">
            <div class="font-semibold text-sm text-[#1d1d1f]">Curbside Pickup Instructions</div>
            <p class="text-[#6e6e73] leading-relaxed">
              When you arrive at the store, pull into <strong>Curbside Bay 3</strong> or walk into the front express pickup desk. Present your Order number <strong>{{ order.order_number }}</strong> to our associate.
            </p>
          </div>
        </div>
      </template>

      <!-- ORDER ITEMS BREAKDOWN -->
      <Card class="bg-white border-[#e0d9cc] rounded-lg p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-3">
          <h2 class="text-base font-serif font-medium text-[#1d1d1f]">
            Order Items ({{ liveItems.length }})
          </h2>
          <div class="text-right">
            <span
              class="font-mono text-sm font-semibold"
              :class="isOrderFullyRefunded ? 'line-through text-rose-400' : 'text-[#1d1d1f]'"
            >
              ${{ (isOrderFullyRefunded ? Number(order.total || 0) : effectiveOrderTotal).toFixed(2) }}
            </span>
            <span v-if="Boolean(liveRefundedTotal)" class="block text-[10px] font-semibold text-rose-700">
              Refunded -${{ liveRefundedTotal.toFixed(2) }}
            </span>
          </div>
        </div>

        <div class="divide-y divide-[#e0d9cc]/60">
          <div 
            v-for="item in liveItems" 
            :key="item.id"
            class="py-3 flex items-center justify-between gap-3 text-xs"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-md bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0">
                <img 
                  :src="item.image || '/images/products/atta.jpg'" 
                  :alt="item.name" 
                  class="w-full h-full object-cover"
                />
              </div>
              <div>
                <div
                  class="font-semibold"
                  :class="isItemFullyRefunded(item) ? 'line-through text-rose-800' : 'text-[#1d1d1f]'"
                >
                  {{ item.name }}
                </div>
                <div class="text-[11px] text-[#6e6e73] flex items-center gap-1.5 flex-wrap mt-0.5">
                  <span v-if="isItemFullyRefunded(item)">
                    {{ Number(item.quantity || 1) }}x ordered · {{ item.size || 'Standard pack' }}
                  </span>
                  <span v-else-if="isItemPartiallyRefunded(item)">
                    {{ activeItemQty(item) }}x active ({{ Number(item.quantity || 1) }}x ordered) · {{ item.size || 'Standard pack' }}
                  </span>
                  <span v-else>
                    {{ Number(item.quantity || 1) }}x · {{ item.size || 'Standard pack' }}
                  </span>
                  <span
                    v-if="isItemFullyRefunded(item)"
                    class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 border border-rose-200 text-[10px] font-bold"
                  >
                    Out of Stock · {{ Number(item.refunded_quantity || item.quantity || 1) }}x Refunded (-${{ Number(item.refunded_amount || item.total_price).toFixed(2) }})
                  </span>
                  <span
                    v-else-if="isItemPartiallyRefunded(item)"
                    class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-bold"
                  >
                    {{ item.refunded_quantity }}x OOS Refunded (-${{ Number(item.refunded_amount).toFixed(2) }})
                  </span>
                </div>
              </div>
            </div>
            <div class="text-right shrink-0">
              <div v-if="isItemFullyRefunded(item)">
                <div class="font-serif font-medium line-through text-rose-400">
                  ${{ Number(item.total_price).toFixed(2) }}
                </div>
                <div class="text-[10px] font-bold text-rose-700">
                  Refunded
                </div>
              </div>
              <div v-else-if="isItemPartiallyRefunded(item)">
                <div class="font-serif font-medium text-[#1d1d1f]">
                  ${{ Math.max(0, Number(item.total_price) - Number(item.refunded_amount || 0)).toFixed(2) }}
                </div>
                <div class="text-[10px] line-through text-zinc-400">
                  ${{ Number(item.total_price).toFixed(2) }}
                </div>
              </div>
              <div v-else>
                <div class="font-serif font-medium text-[#1d1d1f]">
                  ${{ Number(item.total_price).toFixed(2) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <Separator class="my-2" />

        <div class="flex items-center justify-between pt-1">
          <Link href="/" class="text-xs font-semibold text-[#a47a3c] hover:underline flex items-center gap-1">
            <span>Continue Shopping</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </Link>
          <Link href="/account" class="text-xs font-semibold text-[#1d1d1f] hover:underline">
            View My Orders
          </Link>
        </div>
      </Card>

    </div>
  </StoreLayout>
</template>
