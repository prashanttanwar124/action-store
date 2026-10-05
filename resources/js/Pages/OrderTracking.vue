<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { 
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
const isReverbConnected = ref(false);

const isDelivery = computed(() => {
  const type = String(props.order.fulfillment_type || '').toLowerCase();
  return type.includes('delivery');
});

const steps = computed(() => {
  if (isDelivery.value) {
    return [
      { id: 'confirmed', label: 'Order Received', desc: 'Sent to Masala Mart store' },
      { id: 'packing', label: 'Being Picked & Packed', desc: 'Hand-selecting fresh groceries' },
      { id: 'ready_for_pickup', label: 'Out for Delivery', desc: 'Driver is on the way to your address' },
      { id: 'completed', label: 'Delivered', desc: 'Safely delivered to your door' },
    ];
  }
  return [
    { id: 'confirmed', label: 'Order Received', desc: 'Sent to Masala Mart store' },
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
    default:
      return 0;
  }
});

// Clean channel name (Pusher/Reverb does not allow '#' in channel names)
const orderChannelName = computed(() => {
  const clean = String(props.order.order_number || props.order.id).replace(/^#/, '');
  return `orders.${clean}`;
});

// Real-time listener for customer order updates
onMounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    try {
      window.Echo.channel(orderChannelName.value)
        .listen('.order.status.updated', (payload) => {
          currentStatus.value = payload.status;
        });
      isReverbConnected.value = true;
    } catch (e) {
      console.warn('Echo listener error:', e);
    }
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leaveChannel(orderChannelName.value);
  }
});
</script>

<template>
  <Head :title="`Order ${order.order_number} Tracking — Masala Mart`" />

  <StoreLayout :showHeader="true" :showFooter="true" :showBottomNav="true" headerMode="simple" :headerTitle="'Order ' + order.order_number">
    <div class="max-w-3xl mx-auto py-6 space-y-6 pb-28">

      <!-- HERO STATUS BANNER -->
      <div class="p-6 sm:p-8 rounded-3xl bg-[#f3efe7] border border-[#e0d9cc] text-center space-y-3 relative overflow-hidden shadow-xs">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-[#e0d9cc] shadow-2xs">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Live Order Tracking · Auto-Updates</span>
        </div>

        <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          {{ 
            currentStatus === 'ready_for_pickup' 
              ? (isDelivery ? 'Out for Delivery!' : 'Your Order is Ready for Pickup!') 
              : (currentStatus === 'packing' 
                  ? 'Our Team is Picking Your Items' 
                  : (currentStatus === 'completed' 
                      ? (isDelivery ? 'Order Delivered · Thank you!' : 'Order Picked Up · Thank you!') 
                      : 'Order Placed Successfully'))
          }}
        </h1>

        <p class="text-xs sm:text-sm text-[#6e6e73] max-w-md mx-auto">
          <template v-if="isDelivery">
            Delivery: <strong class="text-[#1d1d1f] font-semibold">{{ order.delivery_window || order.pickup_slot || 'Scheduled Day' }}</strong>
            <span v-if="order.delivery_address || order.shipping_address"> to {{ order.delivery_address || order.shipping_address }}</span>
          </template>
          <template v-else>
            Pickup Slot: <strong class="text-[#1d1d1f] font-semibold">{{ order.pickup_slot }}</strong> at {{ order.pickup_location }}
          </template>
        </p>
      </div>

      <!-- VISUAL STEPPER TRACKER -->
      <Card class="bg-white border-[#e0d9cc] rounded-3xl shadow-xs p-6">
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
                  class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 border-2 transition-all duration-300 z-10 shadow-xs"
                  :class="[
                    idx < currentStepIndex 
                      ? 'bg-[#1a1a1a] border-[#1a1a1a] text-white' 
                      : (idx === currentStepIndex 
                          ? 'bg-[#a47a3c] border-[#a47a3c] text-white animate-pulse' 
                          : 'bg-[#fbf9f5] border-[#e0d9cc] text-[#86868b]')
                  ]"
                >
                  <Check v-if="idx < currentStepIndex" class="w-5 h-5 stroke-[2.5]" />
                  <Clock v-else-if="idx === currentStepIndex" class="w-5 h-5 stroke-[2.2]" />
                  <span v-else class="text-xs font-bold">{{ idx + 1 }}</span>
                </div>

                <!-- Step Info -->
                <div class="pt-1.5 flex-1">
                  <div class="flex items-center justify-between">
                    <h3 
                      class="text-sm sm:text-base font-semibold transition-colors"
                      :class="idx <= currentStepIndex ? 'text-[#1d1d1f]' : 'text-[#86868b]'"
                    >
                      {{ step.label }}
                    </h3>
                    <Badge 
                      v-if="idx === currentStepIndex" 
                      variant="outline"
                      class="text-[10px] font-bold bg-amber-50 text-amber-800 border-amber-300"
                    >
                      In Progress
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

      <!-- FULFILLMENT INSTRUCTIONS BOX -->
      <div v-if="isDelivery" class="p-5 rounded-3xl bg-[#fbfaf8] border border-[#e0d9cc] flex items-start gap-4 shadow-2xs">
        <div class="w-10 h-10 rounded-2xl bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0 border border-[#e0d9cc]">
          <Truck class="w-5 h-5" />
        </div>
        <div class="space-y-1 text-xs">
          <div class="font-semibold text-sm text-[#1d1d1f]">Home Delivery Details</div>
          <p class="text-[#6e6e73] leading-relaxed">
            Your fresh grocery items will be safely delivered to <strong>{{ order.delivery_address || order.shipping_address || 'your address' }}</strong>. Please ensure someone is available or instructions are left for contact-free drop off.
          </p>
        </div>
      </div>

      <div v-else class="p-5 rounded-3xl bg-[#fbfaf8] border border-[#e0d9cc] flex items-start gap-4 shadow-2xs">
        <div class="w-10 h-10 rounded-2xl bg-[#f5eee2] text-[#7a5620] flex items-center justify-center shrink-0 border border-[#e0d9cc]">
          <Car class="w-5 h-5" />
        </div>
        <div class="space-y-1 text-xs">
          <div class="font-semibold text-sm text-[#1d1d1f]">Curbside Pickup Instructions</div>
          <p class="text-[#6e6e73] leading-relaxed">
            When you arrive at the store, pull into <strong>Curbside Bay 3</strong> or walk into the front express pickup desk. Present your Order number <strong>{{ order.order_number }}</strong> to our associate.
          </p>
        </div>
      </div>

      <!-- ORDER ITEMS BREAKDOWN -->
      <Card class="bg-white border-[#e0d9cc] rounded-3xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-3">
          <h2 class="text-base font-serif font-medium text-[#1d1d1f]">
            Order Items ({{ order.items.length }})
          </h2>
          <span class="font-mono text-sm font-semibold text-[#1d1d1f]">
            ${{ Number(order.total).toFixed(2) }}
          </span>
        </div>

        <div class="divide-y divide-[#e0d9cc]/60">
          <div 
            v-for="item in order.items" 
            :key="item.id"
            class="py-3 flex items-center justify-between gap-3 text-xs"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0">
                <img 
                  :src="item.image || '/images/products/atta.jpg'" 
                  :alt="item.name" 
                  class="w-full h-full object-cover"
                />
              </div>
              <div>
                <div class="font-semibold text-[#1d1d1f]">{{ item.name }}</div>
                <div class="text-[11px] text-[#6e6e73]">{{ item.quantity }}x · {{ item.size || 'Standard pack' }}</div>
              </div>
            </div>
            <div class="font-serif font-medium text-[#1d1d1f]">
              ${{ Number(item.total_price).toFixed(2) }}
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
