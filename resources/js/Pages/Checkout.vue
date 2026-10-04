<script setup>
import { ref, computed } from 'vue';
import { confirmedOrder } from '../composables/checkoutConfirmation';
import { useCheckoutSchedule } from '../composables/useCheckoutSchedule';
import CheckoutOrderSummary from '../Components/Store/CheckoutOrderSummary.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import {
  ChevronLeft,
  MapPin,
  Clock,
  CheckCircle2,
  Store,
  Lock,
  Loader2,
  Sparkles,
  ShoppingBag,
  AlertCircle,
  Truck,
  Zap,
  Calendar
} from 'lucide-vue-next';
import IconApplePay from '../Components/Icons/IconApplePay.vue';
import IconGooglePay from '../Components/Icons/IconGooglePay.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

const page = usePage();
const store = useStore();

const storeInfo = computed(() => page.props.storeInfo || {});
const pickupSlotWindowLabel = computed(() => {
  if (storeInfo.value?.pickup_slot_window_label) {
    return storeInfo.value.pickup_slot_window_label;
  }
  const duration = Number(storeInfo.value?.pickup_slot_duration_minutes || 60);
  if (duration === 30) return '30-min window';
  if (duration === 120) return '2-hour window';
  return '1-hour window';
});

// Fulfillment Mode: 'pickup' | 'delivery'
const fulfillmentMode = ref('pickup');

const {
  pickupTimingMode, scheduledDay, scheduledTimingType, selectedScheduledTime,
  customHour, customMinute, customPeriod, availableHours, availableMinutes,
  isAmAvailable, isPmAvailable, popularTimesList, formattedCustomTime,
  availableScheduledSlots, setCustomTime, scheduleError, pickupPayload,
} = useCheckoutSchedule(storeInfo, computed(() => page.props.storeTimezone || 'UTC'));

// Delivery Inputs
const deliveryAddress = ref('');
const deliveryApt = ref('');
const deliveryNotes = ref('');

const paymentMethod = ref('apple-pay'); // 'apple-pay' | 'google-pay' | 'card'
const isProcessing = ref(false);
const orderPlaced = ref(false);

const confirmedOrderNumber = ref('');
const confirmedTotalPaid = ref('0.00');
const confirmedPointsEarned = ref(0);
const confirmedSlotLabel = ref('');
const errorMessage = ref('');

const effectivePrepTime = computed(() => {
  return Number(storeInfo.value.effective_prep_time_minutes || 15);
});

const isStoreBusy = computed(() => {
  return Boolean(storeInfo.value.is_busy);
});

// Delivery Calculation
const isDeliveryFree = computed(() => {
  const threshold = Number(storeInfo.value.free_delivery_threshold ?? 50.00);
  return store.subtotal >= threshold;
});

const deliveryOptionFee = computed(() => {
  if (isDeliveryFree.value) return 0;
  return Number(storeInfo.value.delivery_fee ?? 4.99);
});

const deliveryFeeAmount = computed(() => fulfillmentMode.value === 'delivery' ? deliveryOptionFee.value : 0);

const checkoutTotal = computed(() => {
  return (store.total + deliveryFeeAmount.value).toFixed(2);
});

// Effective Chosen Slot Description
const effectiveFulfillmentSlotLabel = computed(() => {
  if (fulfillmentMode.value === 'delivery') {
    return `Home Delivery · ${storeInfo.value.delivery_estimated_time || '5:00 PM – 8:00 PM'}`;
  }
  if (pickupTimingMode.value === 'asap') {
    return `ASAP (Ready in ~${effectivePrepTime.value} mins)`;
  }
  const dayText = scheduledDay.value === 'today' ? 'Today' : 'Tomorrow';
  if (scheduledTimingType.value === 'custom') {
    return `${dayText} at ${formattedCustomTime.value} (Custom Time)`;
  }
  return `${dayText} · ${selectedScheduledTime.value}`;
});

async function completeOrder() {
  if (isProcessing.value || store.cartItems.length === 0) {
    return;
  }
  isProcessing.value = true;
  errorMessage.value = '';

  const isDelivery = fulfillmentMode.value === 'delivery';
  if (isDelivery && !deliveryAddress.value.trim()) {
    errorMessage.value = 'Please provide a delivery street address.';
    isProcessing.value = false;
    return;
  }

  const pickupLoc = isDelivery
    ? `${deliveryAddress.value.trim()}${deliveryApt.value ? ', Apt ' + deliveryApt.value.trim() : ''}`
    : (store.pickupLocation || `${storeInfo.value.address} · ${storeInfo.value.name || 'Masala Mart'}`);

  let schedule = {};
  try {
    if (!isDelivery) schedule = pickupPayload();
  } catch (error) {
    errorMessage.value = error.message;
    isProcessing.value = false;
    return;
  }

  const payload = {
    items: store.cartItems.map(item => ({
      id: item.id,
      name: item.name,
      price: Number(item.price),
      quantity: Number(item.quantity || 1),
      size: item.size || item.weight || '',
      weight: item.weight || item.size || '',
      image: item.image || '',
      is_subscribed: Boolean(item.isSubscribed),
    })),
    payment_method: paymentMethod.value,
    fulfillment_type: isDelivery ? 'Home Delivery' : 'Store Pickup',
    ...schedule,
    delivery_address: isDelivery ? pickupLoc : null,
    pickup_location: pickupLoc,
    notes: isDelivery ? deliveryNotes.value : (storeInfo.value.curbside_instructions || null),
  };

  try {
    const response = await axios.post('/checkout', payload);
    const order = confirmedOrder(response.data);
    const orderNumber = order.order_number;
    const totalPaid = Number(order.total);
    const pointsEarned = Number(order.points_earned);
    const slotLabel = order.pickup_slot;

    orderPlaced.value = true;
    confirmedOrderNumber.value = orderNumber;
    confirmedTotalPaid.value = totalPaid.toFixed(2);
    confirmedPointsEarned.value = pointsEarned;
    confirmedSlotLabel.value = slotLabel;

    store.masalaPoints += pointsEarned;

    const orderItems = (order?.items && order.items.length > 0)
      ? order.items.map(i => ({
          id: i.product_id || i.id,
          name: i.name,
          price: Number(i.unit_price),
          quantity: i.quantity,
          size: i.size,
          image: i.image,
        }))
      : store.cartItems.map(i => ({ ...i }));

    store.pastOrders.unshift({
      id: orderNumber,
      date: 'Today',
      type: order?.fulfillment_type || (isDelivery ? 'Home Delivery' : 'Store Pickup'),
      total: totalPaid,
      itemCount: orderItems.reduce((acc, i) => acc + (i.quantity || 1), 0),
      summary: orderItems.map(i => i.name).join(', '),
      items: orderItems,
    });

    store.clearCart();
  } catch (error) {
    console.error('Checkout error:', error);
    errorMessage.value = error.response?.data?.message || error.message || 'Something went wrong while processing your order. Please try again.';
  } finally {
    isProcessing.value = false;
  }
}
</script>

<template>
  <Head title="Checkout — Masala Mart" />

  <StoreLayout :showHeader="false" :showFooter="false" :showCartBar="false" :showBottomNav="false">
    <div class="space-y-6 max-w-4xl mx-auto pb-24 sm:pb-8 pt-2 sm:pt-4">

      <!-- Breadcrumbs / Top Navigation -->
      <div class="flex items-center gap-1">
        <Link href="/cart" class="inline-flex items-center gap-1 text-[#1d1d1f] hover:text-[#a47a3c] transition-colors -ml-1">
          <ChevronLeft class="w-6 h-6 stroke-[2.5]" />
          <h1 class="text-xl sm:text-2xl font-serif font-medium tracking-tight text-[#1d1d1f]">Checkout</h1>
        </Link>
      </div>

      <!-- Order Confirmation State -->
      <div v-if="orderPlaced" class="bg-white rounded-3xl border border-[#e0d9cc] p-6 sm:p-10 text-center space-y-6 shadow-sm">
        <div class="w-16 h-16 bg-[#f5eee2] text-[#7a5620] rounded-full flex items-center justify-center mx-auto border-2 border-[#e0d9cc]">
          <CheckCircle2 class="w-9 h-9 stroke-[2.2]" />
        </div>

        <div>
          <span class="text-[11px] font-semibold uppercase tracking-widest text-[#7a5620] bg-[#f5eee2] px-3 py-1 rounded-full border border-[#e0d9cc]">
            ORDER CONFIRMED {{ confirmedOrderNumber }}
          </span>
          <h2 class="text-2xl sm:text-4xl text-[#1d1d1f] tracking-tight mt-3 leading-tight font-serif font-medium">
            Thank you for your order!
          </h2>
          <p class="text-sm text-[#6e6e73] mt-2.5 max-w-md mx-auto font-normal leading-relaxed">
            Your items are being hand-picked at <strong>{{ storeInfo.name || 'Masala Mart' }}</strong> and will be ready for <strong>{{ confirmedSlotLabel }}</strong>.
          </p>
        </div>

        <div class="max-w-md mx-auto bg-[#f3efe7] rounded-2xl p-5 border border-[#e0d9cc] text-left space-y-3 text-xs">
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Fulfillment Window:</span>
            <span class="font-bold text-[#1d1d1f]">{{ confirmedSlotLabel }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Location / Address:</span>
            <span class="font-bold text-[#1d1d1f] truncate max-w-[240px]">
              {{ fulfillmentMode === 'delivery' ? deliveryAddress : (storeInfo.address || '214 Main St.') }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Total Paid:</span>
            <span class="font-serif font-medium text-lg text-[#1d1d1f]">${{ confirmedTotalPaid }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[#6e6e73] font-normal">Masala Points Earned:</span>
            <span class="font-bold text-[#7a5620]">+{{ confirmedPointsEarned }} pts</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-3 pt-3">
          <Link
            :href="'/orders/' + encodeURIComponent(confirmedOrderNumber)"
            class="px-6 py-3.5 bg-[#a47a3c] hover:bg-[#8a6b32] text-white font-semibold text-xs rounded-full text-center transition-colors shadow-xs flex items-center justify-center gap-1.5"
          >
            <Sparkles class="w-4 h-4" />
            <span>Track Order Live ➔</span>
          </Link>
          <Link
            href="/account"
            class="px-6 py-3.5 bg-[#ece7de] text-[#1d1d1f] font-semibold text-xs rounded-full text-center hover:bg-[#e0d9cc] transition-colors"
          >
            View Account & Orders
          </Link>
          <Link
            href="/"
            class="px-6 py-3.5 bg-[#1a1a1a] text-white font-semibold text-xs rounded-full text-center hover:bg-black transition-colors shadow-sm"
          >
            Continue Shopping
          </Link>
        </div>
      </div>

      <!-- Empty Cart State -->
      <div v-else-if="store.cartItems.length === 0" class="bg-white rounded-3xl border border-[#e0d9cc] p-10 text-center space-y-4 shadow-xs max-w-lg mx-auto my-8">
        <div class="w-16 h-16 rounded-full bg-[#f3efe7] flex items-center justify-center mx-auto text-[#6e6e73]">
          <ShoppingBag class="w-8 h-8 stroke-[1.8]" />
        </div>
        <h2 class="text-xl font-serif font-medium text-[#1d1d1f]">Your cart is empty</h2>
        <p class="text-xs text-[#6e6e73] font-normal leading-relaxed">
          You don't have any items in your cart to checkout. Add groceries to your cart first.
        </p>
        <Link
          href="/"
          class="inline-flex items-center gap-2 px-6 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full shadow-sm transition-colors"
        >
          <span>Explore Groceries</span>
          <ChevronLeft class="w-3.5 h-3.5 rotate-180" />
        </Link>
      </div>

      <!-- Main Checkout Flow -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">

        <!-- LEFT COLUMN: Form Steps -->
        <div class="lg:col-span-7 space-y-6">

          <!-- STEP 01: FULFILLMENT METHOD & TIMINGS -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">
                01 · FULFILLMENT & TIME SLOT
              </div>
              <!-- Store Rush Indicator Pill -->
              <span
                v-if="isStoreBusy"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300"
              >
                <Flame class="w-3 h-3 text-amber-600" />
                <span>Store Busy (+{{ storeInfo.busy_mode_extra_minutes }}m Prep Buffer)</span>
              </span>
            </div>

            <!-- Fulfillment Mode Switcher Tabs (Store Pickup vs Home Delivery) -->
            <div
              v-if="storeInfo.is_delivery_active"
              class="grid grid-cols-2 p-1 bg-[#ece7de] rounded-2xl border border-[#dfd6c8]"
            >
              <button
                type="button"
                @click="fulfillmentMode = 'pickup'"
                class="py-2.5 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="fulfillmentMode === 'pickup'
                  ? 'bg-white text-[#1d1d1f] shadow-xs'
                  : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
              >
                <Store class="w-4 h-4 text-[#a47a3c]" />
                <span>Store Pickup (Free)</span>
              </button>

              <button
                type="button"
                @click="fulfillmentMode = 'delivery'"
                class="py-2.5 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="fulfillmentMode === 'delivery'
                  ? 'bg-white text-[#1d1d1f] shadow-xs'
                  : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
              >
                <Truck class="w-4 h-4 text-[#a47a3c]" />
                <span>Home Delivery {{ isDeliveryFree ? '(Free)' : `($${deliveryOptionFee.toFixed(2)})` }}</span>
              </button>
            </div>

            <!-- OPTION A: STORE PICKUP SELECTED -->
            <div v-if="fulfillmentMode === 'pickup'" class="space-y-3.5">

              <!-- Store Location Card -->
              <div class="p-5 rounded-2xl bg-[#1a1a1a] text-white shadow-md border border-stone-800 space-y-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-[#e4b97a]">
                      <Store class="w-5 h-5 stroke-[2]" />
                    </div>
                    <div>
                      <div class="font-bold text-sm text-white">{{ storeInfo.name || 'Masala Mart — Main St.' }}</div>
                      <div class="text-xs text-stone-300 font-normal mt-0.5">
                        {{ storeInfo.address || '214 Main St.' }} · {{ storeInfo.city || 'Edison' }}
                      </div>
                    </div>
                  </div>
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#a47a3c]/20 text-[#e4b97a] text-[10px] font-bold border border-[#a47a3c]/40">
                    <CheckCircle2 class="w-3 h-3" />
                    Free Curbside
                  </span>
                </div>

                <div class="pt-2 border-t border-white/10 flex items-center justify-between text-xs text-stone-400 font-normal">
                  <span class="flex items-center gap-1.5 text-stone-300">
                    <Clock class="w-3.5 h-3.5 text-[#e4b97a]" />
                    <span>{{ storeInfo.opening_hours || 'Daily 9:00 AM – 9:00 PM' }}</span>
                  </span>
                  <span class="text-[11px] text-[#e4b97a] font-medium">Curbside Bay 3 or Express Desk</span>
                </div>
              </div>

              <!-- Pickup Timing Mode Tabs: ASAP vs Schedule For Later -->
              <div class="space-y-2.5">
                <div class="text-xs font-semibold text-[#1d1d1f] flex items-center justify-between">
                  <span>Choose When to Pick Up</span>
                  <span class="text-[11px] text-[#6e6e73] font-normal">Bagged fresh before arrival</span>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                  <!-- ASAP Option Button -->
                  <button
                    type="button"
                    @click="pickupTimingMode = 'asap'"
                    class="p-3.5 rounded-2xl border text-left transition-all cursor-pointer relative"
                    :class="pickupTimingMode === 'asap'
                      ? 'bg-[#1a1a1a] text-white border-black shadow-xs'
                      : 'bg-white text-[#1d1d1f] border-[#e0d9cc] hover:border-[#1a1a1a]'"
                  >
                    <div class="flex items-center gap-1.5 text-xs font-bold">
                      <Zap class="w-3.5 h-3.5 text-amber-400" />
                      <span>Fastest ASAP Pickup</span>
                    </div>
                    <div class="text-xs mt-1.5" :class="pickupTimingMode === 'asap' ? 'text-[#e4b97a] font-semibold' : 'text-[#7a5620] font-medium'">
                      Ready in ~{{ effectivePrepTime }} mins
                    </div>
                    <div class="text-[10px] opacity-75 mt-0.5">
                      {{ isStoreBusy ? 'Store is busy (Rush Buffer applied)' : 'Standard express packing' }}
                    </div>
                  </button>

                  <!-- Schedule for Later Option Button -->
                  <button
                    type="button"
                    @click="pickupTimingMode = 'scheduled'"
                    class="p-3.5 rounded-2xl border text-left transition-all cursor-pointer relative"
                    :class="pickupTimingMode === 'scheduled'
                      ? 'bg-[#1a1a1a] text-white border-black shadow-xs'
                      : 'bg-white text-[#1d1d1f] border-[#e0d9cc] hover:border-[#1a1a1a]'"
                  >
                    <div class="flex items-center gap-1.5 text-xs font-bold">
                      <Calendar class="w-3.5 h-3.5 text-[#a47a3c]" />
                      <span>Schedule for Later</span>
                    </div>
                    <div class="text-xs mt-1.5 font-semibold" :class="pickupTimingMode === 'scheduled' ? 'text-white' : 'text-[#1d1d1f]'">
                      {{ storeInfo.pickup_slot_window_label ? `Pick Day & ${storeInfo.pickup_slot_window_label}` : `Pick Day & ${pickupSlotWindowLabel}` }}
                    </div>
                    <div class="text-[10px] opacity-75 mt-0.5">
                      Choose exact time today or tomorrow
                    </div>
                  </button>
                </div>
              </div>

              <!-- Customer Time Slot Picker (When 'Schedule for Later' is selected) -->
              <div v-if="pickupTimingMode === 'scheduled'" class="p-4 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc] space-y-3.5 animate-in fade-in duration-150">

                <!-- Day Switcher -->
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-[#1d1d1f]">Select Pickup Day:</span>
                  <div class="flex gap-1.5">
                    <button
                      type="button"
                      @click="scheduledDay = 'today'"
                      class="px-3 py-1 rounded-full text-xs font-bold transition-all cursor-pointer"
                      :class="scheduledDay === 'today' ? 'bg-[#1a1a1a] text-white' : 'bg-white text-[#6e6e73] border border-[#e0d9cc]'"
                    >
                      Today
                    </button>
                    <button
                      type="button"
                      @click="scheduledDay = 'tomorrow'"
                      class="px-3 py-1 rounded-full text-xs font-bold transition-all cursor-pointer"
                      :class="scheduledDay === 'tomorrow' ? 'bg-[#1a1a1a] text-white' : 'bg-white text-[#6e6e73] border border-[#e0d9cc]'"
                    >
                      Tomorrow
                    </button>
                  </div>
                </div>

                <!-- Timing Type Switcher (1-Hr Windows vs Custom Time) -->
                <div class="flex items-center justify-between pt-1 border-t border-[#e0d9cc]/60">
                  <span class="text-xs font-bold text-[#1d1d1f]">Pickup Timing Preference:</span>
                  <div class="flex gap-1 bg-[#ece7de] p-0.5 rounded-xl border border-[#dfd6c8]">
                    <button
                      type="button"
                      @click="scheduledTimingType = 'slot'"
                      class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer"
                      :class="scheduledTimingType === 'slot' ? 'bg-white text-[#1d1d1f] shadow-2xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
                    >
                      {{ pickupSlotWindowLabel }}
                    </button>
                    <button
                      type="button"
                      @click="scheduledTimingType = 'custom'"
                      class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer flex items-center gap-1"
                      :class="scheduledTimingType === 'custom' ? 'bg-white text-[#1d1d1f] shadow-2xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
                    >
                      <span>Custom Time</span>
                      <span class="w-1.5 h-1.5 rounded-full bg-[#a47a3c]"></span>
                    </button>
                  </div>
                </div>

                <!-- SUB-OPTION A: 1-HOUR TIME SLOT GRID -->
                <div v-if="scheduledTimingType === 'slot'" class="space-y-2">
                  <div class="flex items-center justify-between text-[11px] text-[#6e6e73]">
                    <span>Select {{ pickupSlotWindowLabel }} for {{ scheduledDay === 'today' ? 'Today' : 'Tomorrow' }}:</span>
                    <button
                      type="button"
                      @click="scheduledTimingType = 'custom'"
                      class="text-[#a47a3c] font-semibold hover:underline cursor-pointer"
                    >
                      Enter exact time ➔
                    </button>
                  </div>

                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <button
                      v-for="slot in availableScheduledSlots"
                      :key="slot.label"
                      type="button"
                      :disabled="slot.disabled"
                      @click="!slot.disabled ? selectedScheduledTime = slot.label : null"
                      class="p-2.5 rounded-xl border text-center transition-all flex flex-col items-center justify-center min-h-[46px]"
                      :class="[
                        slot.disabled
                          ? 'bg-[#ece7de] text-[#a19f9d] border-[#dfd6c8] cursor-not-allowed opacity-60'
                          : (selectedScheduledTime === slot.label
                              ? 'bg-[#1a1a1a] text-white font-bold border-black shadow-xs cursor-pointer'
                              : 'bg-white text-[#1d1d1f] border-[#e0d9cc] hover:border-[#1a1a1a] cursor-pointer')
                      ]"
                    >
                      <span class="text-[11px] font-semibold">{{ slot.label }}</span>
                      <span v-if="slot.disabled" class="text-[9px] text-[#86868b] mt-0.5">Unavailable</span>
                    </button>
                  </div>
                </div>

                <!-- SUB-OPTION B: CUSTOM EXACT TIME PICKER (OUR UI FRAMEWORK) -->
                <div v-else class="space-y-3.5 p-4 bg-white rounded-2xl border border-[#e0d9cc] shadow-2xs">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-lg bg-[#f3efe7] text-[#a47a3c] flex items-center justify-center border border-[#e0d9cc]">
                        <Clock class="w-3.5 h-3.5 stroke-[2.2]" />
                      </div>
                      <div>
                        <div class="text-xs font-bold text-[#1d1d1f]">Select Exact Pickup Time</div>
                        <div class="text-[10px] text-[#6e6e73]">Custom hour, minute & period</div>
                      </div>
                    </div>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-[#1a1a1a] text-[#e4b97a] border border-[#a47a3c]/30 shadow-2xs">
                      {{ formattedCustomTime }}
                    </span>
                  </div>

                  <!-- 3-Column Custom Time Selector (Using UI Style Framework) -->
                  <div class="grid grid-cols-3 gap-2">
                    <!-- Hour Select -->
                    <div class="space-y-1">
                      <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Hour</label>
                      <Select v-model="customHour">
                        <SelectTrigger class="h-10 w-full rounded-xl border border-[#e0d9cc] bg-[#fbf9f5] hover:bg-white text-xs font-bold text-[#1d1d1f] focus:ring-2 focus:ring-[#1a1a1a]">
                          <SelectValue placeholder="Hour" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl border border-[#e0d9cc] bg-white shadow-xl max-h-56 z-50">
                          <SelectItem v-for="h in availableHours" :key="h" :value="h" class="text-xs font-semibold cursor-pointer">
                            {{ h }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- Minute Select -->
                    <div class="space-y-1">
                      <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Minute</label>
                      <Select v-model="customMinute">
                        <SelectTrigger class="h-10 w-full rounded-xl border border-[#e0d9cc] bg-[#fbf9f5] hover:bg-white text-xs font-bold text-[#1d1d1f] focus:ring-2 focus:ring-[#1a1a1a]">
                          <SelectValue placeholder="Minute" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl border border-[#e0d9cc] bg-white shadow-xl max-h-56 z-50">
                          <SelectItem v-for="m in availableMinutes" :key="m" :value="m" class="text-xs font-semibold cursor-pointer">
                            :{{ m }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- AM/PM Segmented Switcher -->
                    <div class="space-y-1">
                      <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Period</label>
                      <div class="grid grid-cols-2 p-0.5 bg-[#ece7de] rounded-xl border border-[#dfd6c8] h-10">
                        <button
                          type="button"
                          :disabled="!isAmAvailable"
                          @click="isAmAvailable && (customPeriod = 'AM')"
                          class="rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                          :class="customPeriod === 'AM' ? 'bg-[#1a1a1a] text-white shadow-2xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
                        >
                          AM
                        </button>
                        <button
                          type="button"
                          :disabled="!isPmAvailable"
                          @click="isPmAvailable && (customPeriod = 'PM')"
                          class="rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                          :class="customPeriod === 'PM' ? 'bg-[#1a1a1a] text-white shadow-2xs' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
                        >
                          PM
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Quick Preset Time Chips -->
                  <div class="space-y-1.5 pt-1">
                    <div class="text-[10px] font-semibold text-[#86868b] uppercase tracking-wider">Popular Times:</div>
                    <div class="flex flex-wrap gap-1.5">
                      <button
                        v-for="t in popularTimesList"
                        :key="t"
                        type="button"
                        @click="setCustomTime(t)"
                        class="px-2.5 py-1 rounded-lg text-[10px] font-semibold transition-all cursor-pointer border"
                        :class="formattedCustomTime === t ? 'bg-[#1a1a1a] text-white border-black shadow-2xs' : 'bg-[#fbf9f5] text-[#1d1d1f] border-[#e0d9cc] hover:bg-[#ece7de]'"
                      >
                        {{ t }}
                      </button>
                    </div>
                  </div>

                  <p class="text-[11px] text-[#6e6e73] leading-relaxed pt-1 border-t border-[#e0d9cc]/60">
                    Pickup scheduled for <strong class="text-[#1d1d1f]">{{ scheduledDay === 'today' ? 'Today' : 'Tomorrow' }} at {{ formattedCustomTime }}</strong>. Our team will pack and have your order ready by this exact minute.
                  </p>
                </div>

              </div>

            </div>

            <!-- OPTION B: HOME DELIVERY SELECTED -->
            <div v-else-if="fulfillmentMode === 'delivery'" class="space-y-4 animate-in fade-in duration-150">

              <!-- Delivery Schedule & Status Banner -->
              <div
                class="p-4 rounded-2xl border space-y-1.5"
                :class="storeInfo.is_delivery_available_today
                  ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
                  : 'bg-amber-50 text-amber-900 border-amber-200'"
              >
                <div class="flex items-center gap-2 font-bold text-xs">
                  <Truck class="w-4 h-4 text-[#a47a3c]" />
                  <span>{{ storeInfo.is_delivery_available_today ? 'Delivery is active today!' : 'Home Delivery Schedule Notice' }}</span>
                </div>
                <div class="text-[11px] leading-relaxed">
                  <span v-if="storeInfo.is_delivery_available_today">
                    Your order will be hand-delivered during today's window: <strong>{{ storeInfo.delivery_estimated_time || '5:00 PM – 8:00 PM' }}</strong>.
                  </span>
                  <span v-else>
                    Weekly delivery is active on <strong>{{ Array.isArray(storeInfo.delivery_days) ? storeInfo.delivery_days.map(d => d.toUpperCase()).join(', ') : 'weekends' }}</strong>. Orders placed today will be delivered on the next scheduled run.
                  </span>
                </div>
              </div>

              <!-- Address Inputs -->
              <div class="p-4 bg-white rounded-2xl border border-[#e0d9cc] space-y-3">
                <div class="text-xs font-bold text-[#1d1d1f] flex items-center gap-1.5">
                  <MapPin class="w-4 h-4 text-[#a47a3c]" />
                  <span>Delivery Address</span>
                </div>

                <div>
                  <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                    Street Address *
                  </Label>
                  <Input
                    v-model="deliveryAddress"
                    type="text"
                    required
                    placeholder="e.g. 142 Oak Tree Rd"
                    class="w-full bg-[#f3efe7] border-[#e0d9cc] text-xs h-10"
                  />
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                      Apt / Suite / Unit (Optional)
                    </Label>
                    <Input
                      v-model="deliveryApt"
                      type="text"
                      placeholder="e.g. Apt 4B"
                      class="w-full bg-[#f3efe7] border-[#e0d9cc] text-xs h-10"
                    />
                  </div>
                  <div>
                    <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                      Delivery Driver Notes
                    </Label>
                    <Input
                      v-model="deliveryNotes"
                      type="text"
                      placeholder="e.g. Leave at front door"
                      class="w-full bg-[#f3efe7] border-[#e0d9cc] text-xs h-10"
                    />
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!-- STEP 02: PAYMENT -->
          <div class="space-y-3.5 pt-3 border-t border-[#e0d9cc]">
            <div class="text-[11px] font-semibold text-[#86868b] uppercase tracking-wider">
              02 · PAYMENT METHOD
            </div>

            <!-- Apple Pay / Google Pay Pills -->
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                @click="paymentMethod = 'apple-pay'"
                :class="[
                  'h-12 bg-black hover:bg-neutral-900 text-white font-semibold rounded-full flex items-center justify-center transition-all cursor-pointer shadow-xs active:scale-[0.99] border border-black',
                  paymentMethod === 'apple-pay' ? 'ring-2 ring-[#a47a3c] ring-offset-2' : ''
                ]"
                aria-label="Pay with Apple Pay"
              >
                <IconApplePay :width="62" :height="25" class="text-white" />
              </button>

              <button
                type="button"
                @click="paymentMethod = 'google-pay'"
                :class="[
                  'h-12 bg-black hover:bg-neutral-900 text-white font-semibold rounded-full flex items-center justify-center transition-all cursor-pointer shadow-xs active:scale-[0.99] border border-black',
                  paymentMethod === 'google-pay' ? 'ring-2 ring-[#a47a3c] ring-offset-2' : ''
                ]"
                aria-label="Pay with Google Pay"
              >
                <IconGooglePay :width="62" :height="25" class="text-white" />
              </button>
            </div>

            <!-- "or pay by card" divider -->
            <div class="relative flex items-center justify-center my-3">
              <Separator class="w-full bg-[#e0d9cc]" />
              <span class="bg-[#fbf9f5] px-3 text-[11px] font-normal text-[#86868b] absolute">
                or pay with card
              </span>
            </div>

            <!-- Card Inputs -->
            <div class="space-y-3">
              <div>
                <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                  Card number
                </Label>
                <div class="relative flex items-center">
                  <Input
                    type="text"
                    placeholder="1234 1234 1234 1234"
                    value="1234 1234 1234 1234"
                    class="w-full px-4 h-11 bg-[#f3efe7] border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus-visible:bg-white"
                  />
                  <div class="absolute right-3 flex items-center gap-1">
                    <Badge variant="outline" class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border-[#e0d9cc] text-[#1d1d1f] rounded-xs">VISA</Badge>
                    <Badge variant="outline" class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border-[#e0d9cc] text-[#1d1d1f] rounded-xs">MC</Badge>
                    <Badge variant="outline" class="text-[9px] font-bold font-mono px-1.5 py-0.5 bg-white border-[#e0d9cc] text-[#1d1d1f] rounded-xs">AMEX</Badge>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                    Expires (MM/YY)
                  </Label>
                  <Input
                    type="text"
                    placeholder="12/28"
                    value="12/28"
                    class="w-full px-4 h-11 bg-[#f3efe7] border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus-visible:bg-white"
                  />
                </div>
                <div>
                  <Label class="block text-xs font-normal text-[#6e6e73] mb-1">
                    CVV
                  </Label>
                  <Input
                    type="password"
                    placeholder="•••"
                    value="123"
                    class="w-full px-4 h-11 bg-[#f3efe7] border-[#e0d9cc] rounded-xl text-xs font-normal text-[#1d1d1f] placeholder-[#86868b] focus-visible:bg-white font-mono"
                  />
                </div>
              </div>
            </div>

            <!-- Error Banner -->
            <p v-if="fulfillmentMode === 'pickup' && scheduleError" role="alert" class="text-xs text-rose-700">{{ scheduleError }}</p>
            <div v-if="errorMessage" class="p-3.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-2xl flex items-center gap-2.5">
              <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
              <span>{{ errorMessage }}</span>
            </div>

            <!-- Desktop Pay Button -->
            <div class="pt-2 hidden sm:block">
              <Button
                type="button"
                @click="completeOrder"
                :disabled="isProcessing || (fulfillmentMode === 'pickup' && Boolean(scheduleError))"
                class="w-full h-13 py-3.5 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-sm rounded-full transition-all cursor-pointer flex items-center justify-between px-6 shadow-md active:scale-[0.99] disabled:opacity-60"
              >
                <div class="flex items-center gap-2">
                  <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin" />
                  <Lock v-else class="w-4 h-4 stroke-[2.2]" />
                  <span>{{ isProcessing ? 'Authorizing Payment...' : `Complete Order · ${effectiveFulfillmentSlotLabel}` }}</span>
                </div>
                <span class="font-serif font-medium text-base">${{ checkoutTotal }}</span>
              </Button>
            </div>

          </div>

        </div>

        <CheckoutOrderSummary
          :items="store.cartItems"
          :item-count="store.totalItemCount"
          :subtotal="store.subtotal"
          :fulfillment-mode="fulfillmentMode"
          :delivery-fee-amount="deliveryFeeAmount"
          :checkout-total="checkoutTotal"
        />

      </div>

      <!-- Mobile Sticky Bottom Pay Bar -->
      <div
        v-if="!orderPlaced && store.cartItems.length > 0"
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#e0d9cc] p-3.5 shadow-2xl"
      >
        <button
          type="button"
          @click="completeOrder"
          :disabled="isProcessing || (fulfillmentMode === 'pickup' && Boolean(scheduleError))"
          class="w-full h-13 py-3.5 bg-[#1a1a1a] active:bg-black text-white font-semibold text-sm rounded-full cursor-pointer flex items-center justify-between px-6 shadow-md disabled:opacity-60"
        >
          <div class="flex items-center gap-2">
            <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin" />
            <Lock v-else class="w-4 h-4 stroke-[2.2]" />
            <span v-if="isProcessing">Authorizing Payment...</span>
            <span v-else>Pay · ${{ checkoutTotal }}</span>
          </div>
          <span class="text-xs text-stone-300 font-semibold truncate max-w-[120px]">
            {{ effectiveFulfillmentSlotLabel }}
          </span>
        </button>
      </div>

    </div>
  </StoreLayout>
</template>
