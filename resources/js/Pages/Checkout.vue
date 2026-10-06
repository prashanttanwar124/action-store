<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { confirmedOrder } from '../composables/checkoutConfirmation';
import { useCheckoutSchedule } from '../composables/useCheckoutSchedule';
import CheckoutOrderSummary from '../Components/Store/CheckoutOrderSummary.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { loadStripe } from '@stripe/stripe-js';
import IconApplePay from '../Components/Icons/IconApplePay.vue';
import IconGooglePay from '../Components/Icons/IconGooglePay.vue';
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
  Calendar,
  Sun,
  Moon,
  Sunrise,
  LayoutGrid,
  Flame,
  ChevronDown,
  ChevronRight,
  CreditCard,
  X
} from 'lucide-vue-next';
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
  todayFormatted, tomorrowFormatted, isDayOpen, isAsapAvailable, storeOpenTimeFormatted,
  isTodayAvailable, isTomorrowAvailable,
} = useCheckoutSchedule(
  storeInfo,
  computed(() => page.props.storeTimezone || 'America/Toronto'),
  {
    bookedSlots: computed(() => page.props.bookedSlots || {}),
    maxOrdersPerSlot: computed(() => page.props.maxOrdersPerSlot || 0),
    pickupDays: computed(() => page.props.pickupDays || null),
  }
);

// Delivery Inputs
const deliveryAddress = ref('');
const deliveryApt = ref('');
const deliveryNotes = ref('');

const paymentMethod = ref('card'); // 'card' | 'apple-pay' | 'google-pay'

// Dynamic Device & Browser Wallet Detection
const isApplePayAvailable = ref(false);
const isGooglePayAvailable = ref(false);
const showAllPaymentMethodsForTesting = ref(false);

async function detectDeviceWallets() {
  if (typeof window === 'undefined') return;

  try {
    if (window.ApplePaySession && typeof window.ApplePaySession.canMakePayments === 'function') {
      isApplePayAvailable.value = window.ApplePaySession.canMakePayments();
    } else {
      const isApple = /Macintosh|iPhone|iPad|iPod/.test(navigator.userAgent);
      const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
      isApplePayAvailable.value = Boolean(isApple && isSafari && window.ApplePaySession);
    }
  } catch (e) {
    isApplePayAvailable.value = false;
  }

  try {
    const isChromeOrAndroid = /Chrome|Android/i.test(navigator.userAgent) && !/Edg|OPR/i.test(navigator.userAgent);
    if (isChromeOrAndroid && (window.PaymentRequest || window.google)) {
      isGooglePayAvailable.value = true;
    }
  } catch (e) {
    isGooglePayAvailable.value = false;
  }

  if (stripe.value) {
    try {
      const pr = stripe.value.paymentRequest({
        country: 'CA',
        currency: 'cad',
        total: {
          label: 'Masala Mart',
          amount: Math.max(50, Math.round(Number(checkoutTotal.value || 10) * 100)),
        },
      });
      const result = await pr.canMakePayment();
      if (result) {
        if (result.applePay) isApplePayAvailable.value = true;
        if (result.googlePay) isGooglePayAvailable.value = true;
      }
    } catch (e) {
      // Ignore
    }
  }
}

const availablePaymentMethods = computed(() => {
  if (showAllPaymentMethodsForTesting.value) {
    return ['card', 'apple-pay', 'google-pay'];
  }
  const methods = ['card'];
  if (isApplePayAvailable.value) {
    methods.push('apple-pay');
  }
  if (isGooglePayAvailable.value) {
    methods.push('google-pay');
  }
  return methods;
});

watch(availablePaymentMethods, (methods) => {
  if (!methods.includes(paymentMethod.value)) {
    paymentMethod.value = 'card';
  }
});

// Stripe SDK & Elements State
const stripe = ref(null);
const elements = ref(null);
const paymentElement = ref(null);
const isStripeLoading = ref(false);
const isStripeMounted = ref(false);
const stripeError = ref('');

const stripePublishableKey = computed(() => page.props.stripeKey || import.meta.env.VITE_STRIPE_KEY || '');
const isStripeConfigured = computed(() => Boolean(stripePublishableKey.value));

async function initStripe() {
  if (!isStripeConfigured.value) return;
  const total = Number(checkoutTotal.value);
  if (!total || total < 0.5) return;
  if (isStripeMounted.value || isStripeLoading.value) return;

  isStripeLoading.value = true;
  stripeError.value = '';

  try {
    if (!stripe.value) {
      stripe.value = await loadStripe(stripePublishableKey.value);
    }
    if (!stripe.value) {
      throw new Error('Failed to load Stripe SDK');
    }

    elements.value = stripe.value.elements({
      mode: 'payment',
      amount: Math.max(50, Math.round(total * 100)),
      currency: 'cad',
      appearance: {
        theme: 'stripe',
        variables: {
          colorPrimary: '#a47a3c',
          colorBackground: '#ffffff',
          colorText: '#1a1a1a',
          colorDanger: '#e11d48',
          fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          borderRadius: '12px',
          spacingUnit: '4px',
        },
        rules: {
          '.Tab': {
            display: 'none',
          },
          '.TabList': {
            display: 'none',
          },
          '.Tab--selected': {
            display: 'none',
          },
          '.Input': {
            backgroundColor: '#ffffff',
            borderColor: '#e0d9cc',
            boxShadow: 'none',
          },
          '.Input:focus': {
            borderColor: '#1a1a1a',
            boxShadow: '0 0 0 1px #1a1a1a',
          },
        },
      },
    });

    if (paymentElement.value) {
      try {
        paymentElement.value.unmount();
        paymentElement.value.destroy();
      } catch (e) {
        // ignore
      }
      paymentElement.value = null;
      isStripeMounted.value = false;
    }

    paymentElement.value = elements.value.create('payment', {
      layout: 'tabs',
      wallets: {
        applePay: 'auto',
        googlePay: 'auto',
      },
    });
    await nextTick();
    const container = document.getElementById('stripe-payment-element-mount');
    if (container) {
      paymentElement.value.mount('#stripe-payment-element-mount');
      isStripeMounted.value = true;
      detectDeviceWallets();
    }
  } catch (err) {
    console.error('Stripe initialization failed:', err);
    stripeError.value = err.response?.data?.error || err.message || 'Stripe initialization failed.';
  } finally {
    isStripeLoading.value = false;
  }
}


const isProcessing = ref(false);
const isMobileSummaryOpen = ref(false);
const isScheduleModalOpen = ref(false);
const orderPlaced = ref(false);

const confirmedOrderNumber = ref('');
const confirmedTotalPaid = ref('0.00');
const confirmedPointsEarned = ref(0);
const confirmedSlotLabel = ref('');
const errorMessage = ref('');
const pickupError = ref('');
const deliveryError = ref('');
const paymentError = ref('');
const generalError = ref('');

function clearErrors() {
  errorMessage.value = '';
  pickupError.value = '';
  deliveryError.value = '';
  paymentError.value = '';
  generalError.value = '';
}

function scrollToError() {
  if (pickupError.value || (fulfillmentMode.value === 'pickup' && scheduleError.value)) {
    document.getElementById('fulfillment-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    isScheduleModalOpen.value = true;
  } else if (deliveryError.value) {
    document.getElementById('delivery-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } else {
    document.getElementById('payment-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// Clear errors when customer interacts
watch([selectedScheduledTime, scheduledDay, scheduledTimingType, customHour, customMinute, customPeriod], () => {
  pickupError.value = '';
  if (errorMessage.value) errorMessage.value = '';
});

watch(deliveryAddress, () => {
  deliveryError.value = '';
  if (errorMessage.value) errorMessage.value = '';
});


const effectivePrepTime = computed(() => {
  return Number(storeInfo.value.effective_prep_time_minutes || 15);
});

const isStoreBusy = computed(() => {
  return Boolean(storeInfo.value.is_busy);
});

// Slot Period Categorization (Morning, Afternoon, Evening)
const slotPeriodFilter = ref('all'); // 'all' | 'morning' | 'afternoon' | 'evening'

function getSlotPeriod(slotLabel) {
  const match = slotLabel.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)/i);
  if (!match) return 'afternoon';
  let hour = parseInt(match[1], 10);
  const period = match[3].toUpperCase();
  if (period === 'PM' && hour !== 12) hour += 12;
  if (period === 'AM' && hour === 12) hour = 0;

  if (hour < 12) return 'morning';
  if (hour < 17) return 'afternoon';
  return 'evening';
}

const filteredScheduledSlots = computed(() => {
  if (slotPeriodFilter.value === 'all') return availableScheduledSlots.value;
  return availableScheduledSlots.value.filter(s => getSlotPeriod(s.label) === slotPeriodFilter.value);
});

const slotCountsByPeriod = computed(() => {
  const counts = { all: availableScheduledSlots.value.length, morning: 0, afternoon: 0, evening: 0 };
  availableScheduledSlots.value.forEach(s => {
    const p = getSlotPeriod(s.label);
    counts[p] = (counts[p] || 0) + 1;
  });
  return counts;
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

// Update Stripe Elements amount whenever cart total changes (zero backend sync calls!)
watch(checkoutTotal, (newTotal) => {
  const total = Number(newTotal);
  if (elements.value && isStripeMounted.value && total >= 0.5) {
    try {
      elements.value.update({
        amount: Math.round(total * 100),
      });
    } catch (e) {
      console.warn('elements.update warning:', e);
    }
  }
});

// Effective Chosen Slot Description
const effectiveFulfillmentSlotLabel = computed(() => {
  if (fulfillmentMode.value === 'delivery') {
    return `Home Delivery · ${storeInfo.value.delivery_estimated_time || '5:00 PM – 8:00 PM'}`;
  }
  if (pickupTimingMode.value === 'asap') {
    return isAsapAvailable.value
      ? `ASAP (Ready in ~${effectivePrepTime.value} mins)`
      : `Store Closed · Opens at ${storeOpenTimeFormatted.value || '9:00 AM'}`;
  }
  const dayDate = scheduledDay.value === 'today' ? todayFormatted.value : tomorrowFormatted.value;
  const dayText = scheduledDay.value === 'today' ? `Today (${dayDate})` : `Tomorrow (${dayDate})`;
  if (scheduledTimingType.value === 'custom') {
    return `${dayText} at ${formattedCustomTime.value}`;
  }
  return `${dayText} · ${selectedScheduledTime.value}`;
});

onMounted(() => {
  detectDeviceWallets();
  if (isStripeConfigured.value && Number(checkoutTotal.value) >= 0.5) {
    initStripe();
  }
});

watch(
  [
    () => store.cartItems,
    () => fulfillmentMode.value,
    () => deliveryAddress.value,
    () => deliveryApt.value,
    () => effectiveFulfillmentSlotLabel.value,
    () => checkoutTotal.value,
  ],
  () => {
    if (Number(checkoutTotal.value) >= 0.5 && !isStripeMounted.value && !isStripeLoading.value && isStripeConfigured.value) {
      initStripe();
    }
  },
  { deep: true }
);

async function completeOrder() {
  if (isProcessing.value || store.cartItems.length === 0) {
    return;
  }
  isProcessing.value = true;
  clearErrors();

  const minRequired = Number(page.props.minOrderAmount || 0);
  if (minRequired > 0 && store.subtotal < minRequired) {
    generalError.value = `Minimum order amount is $${minRequired.toFixed(2)}. Please add more items to your cart.`;
    errorMessage.value = generalError.value;
    isProcessing.value = false;
    return;
  }

  const isDelivery = fulfillmentMode.value === 'delivery';
  if (isDelivery && !deliveryAddress.value.trim()) {
    deliveryError.value = 'Please provide a delivery street address.';
    errorMessage.value = deliveryError.value;
    isProcessing.value = false;
    document.getElementById('delivery-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  let schedule = {};
  try {
    if (!isDelivery) schedule = pickupPayload();
  } catch (error) {
    pickupError.value = error.message;
    errorMessage.value = error.message;
    isProcessing.value = false;
    document.getElementById('fulfillment-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  const formattedDeliveryAddress = isDelivery
    ? (deliveryApt.value.trim() ? `${deliveryAddress.value.trim()}, Apt ${deliveryApt.value.trim()}` : deliveryAddress.value.trim())
    : null;
  const storePickupLocation = `${storeInfo.value.name || 'Masala Mart'} · ${storeInfo.value.address || '456 Curry Road, Flavor Town'}`;
  const effectivePickupSlot = isDelivery ? null : (schedule.pickup_slot || effectiveFulfillmentSlotLabel.value);

  // 1. If Stripe is configured, validate the card form locally with Elements.submit() first!
  if (isStripeConfigured.value) {
    if (!stripe.value || !elements.value) {
      paymentError.value = 'Stripe payment element is still loading. Please wait a moment and try again.';
      errorMessage.value = paymentError.value;
      isProcessing.value = false;
      return;
    }

    const { error: submitError } = await elements.value.submit();
    if (submitError) {
      paymentError.value = submitError.message || 'Please check your payment information.';
      errorMessage.value = paymentError.value;
      isProcessing.value = false;
      return;
    }
  }

  // 2. Start (or resume) the checkout: the server validates the cart and reserves stock. The order is created only after payment.
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
    expected_total: Number(checkoutTotal.value),
    delivery_address: formattedDeliveryAddress,
    pickup_location: isDelivery ? (formattedDeliveryAddress || 'Delivery Address') : storePickupLocation,
    notes: isDelivery ? deliveryNotes.value : (storeInfo.value.curbside_instructions || null),
  };

  let response;
  try {
    response = await axios.post('/checkout', payload);
  } catch (error) {
    console.error('Checkout error:', error);
    const errors = error.response?.data?.errors;
    const errorCode = Array.isArray(errors?.error_code) ? errors.error_code[0] : errors?.error_code;
    
    // Contextual routing: Pickup errors
    if (errors?.pickup_slot || errors?.pickup_time || errors?.pickup_date || ['slot_inactive', 'slot_full', 'slot_missing', 'store_closed', 'date_invalid'].includes(errorCode)) {
      const msg = (Array.isArray(errors?.pickup_slot) ? errors.pickup_slot[0] : errors?.pickup_slot)
        || (Array.isArray(errors?.pickup_time) ? errors.pickup_time[0] : errors?.pickup_time)
        || (errorCode === 'slot_full' ? 'This pickup window just reached maximum capacity. Please pick another available time slot below.' : 'The selected pickup window is unavailable. Please select another time slot below.');
      
      pickupError.value = msg;
      errorMessage.value = msg;

      // Automatically select the next valid slot if available
      const nextValid = availableScheduledSlots.value.find(s => !s.disabled && s.label !== selectedScheduledTime.value);
      if (nextValid) {
        selectedScheduledTime.value = nextValid.label;
      }

      document.getElementById('fulfillment-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      isProcessing.value = false;
      return;
    }

    // Contextual routing: Delivery errors
    if (errors?.delivery_address || errors?.delivery_zip_codes || errorCode === 'delivery_closed') {
      const msg = (Array.isArray(errors?.delivery_address) ? errors.delivery_address[0] : errors?.delivery_address) || 'Please provide a valid delivery street address.';
      deliveryError.value = msg;
      errorMessage.value = msg;
      document.getElementById('delivery-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      isProcessing.value = false;
      return;
    }

    // Contextual routing: Payment errors
    if (errors?.payment_method || errorCode === 'payment_failed') {
      const msg = (Array.isArray(errors?.payment_method) ? errors.payment_method[0] : errors?.payment_method) || 'Payment authorization failed. Please check your payment details.';
      paymentError.value = msg;
      errorMessage.value = msg;
      document.getElementById('payment-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      isProcessing.value = false;
      return;
    }

    let errorDetails = null;
    if (errors && typeof errors === 'object') {
      errorDetails = Object.entries(errors)
        .filter(([key]) => key !== 'error_code')
        .map(([, val]) => Array.isArray(val) ? val.join(' ') : String(val))
        .join(' ');
    }

    const fallbackMsg = errorDetails || error.response?.data?.message || error.message || 'Something went wrong while processing your order. Please try again.';
    generalError.value = fallbackMsg;
    errorMessage.value = fallbackMsg;
    isProcessing.value = false;
    return;
  }

  const resData = response.data;
  let orderResponse = resData;

  // 3. Collect the payment. A declined card keeps the checkout, so the next "Pay" click retries it.
  if (resData.requires_payment) {
    const paymentIntentId = await collectPayment(resData.clientSecret);
    if (!paymentIntentId) {
      errorMessage.value = paymentError.value;
      isProcessing.value = false;
      return;
    }

    try {
      const { data } = await axios.post('/checkout/complete', {
        checkout_id: resData.checkout_id,
        payment_intent_id: paymentIntentId,
      });
      orderResponse = data;
    } catch (err) {
      console.warn('checkout/complete error:', err);
      const paymentMsg = err.response?.data?.errors?.payment
        ? (Array.isArray(err.response.data.errors.payment) ? err.response.data.errors.payment[0] : err.response.data.errors.payment)
        : null;
      if (paymentMsg) {
        generalError.value = paymentMsg;
        errorMessage.value = paymentMsg;
        isProcessing.value = false;
        return;
      }

      // The payment went through, so Stripe's webhook still creates the order
      store.clearCart();
      generalError.value = 'Payment received. Your order is being confirmed and will appear in your orders shortly.';
      errorMessage.value = generalError.value;
      isProcessing.value = false;
      return;
    }
  }

  let order;
  try {
    order = confirmedOrder(orderResponse);
  } catch (err) {
    generalError.value = err.message;
    errorMessage.value = err.message;
    isProcessing.value = false;
    return;
  }

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
  isProcessing.value = false;
}

/**
 * Confirm the card payment with Stripe.
 * Returns the PaymentIntent id once paid, or null with paymentError explaining why not.
 */
async function collectPayment(clientSecret) {
  try {
    if (!clientSecret || !stripe.value || !elements.value) {
      throw new Error('Payment could not be started. Please try again.');
    }

    const { error: stripeErr, paymentIntent } = await stripe.value.confirmPayment({
      elements: elements.value,
      clientSecret,
      confirmParams: {
        return_url: window.location.origin + '/checkout',
      },
      redirect: 'if_required',
    });

    if (stripeErr) {
      paymentError.value = stripeErr.message || 'Payment processing failed.';
      return null;
    }

    if (paymentIntent?.status === 'succeeded') {
      return paymentIntent.id;
    }

    paymentError.value = 'Payment was not confirmed. Current status: ' + (paymentIntent?.status || 'incomplete');
    return null;
  } catch (err) {
    console.error('Stripe confirmation error:', err);
    paymentError.value = err.message || 'Payment processing error.';
    return null;
  }
}
</script>

<template>
  <Head title="Checkout — Masala Mart" />

  <StoreLayout 
    headerMode="cart" 
    headerTitle="Checkout" 
    backUrl="/cart" 
    :showCartBar="false" 
    :showBottomNav="false"
  >
    <div class="space-y-4 sm:space-y-6 max-w-5xl mx-auto pb-20 sm:pb-8 pt-1 sm:pt-3">

      <!-- Breadcrumbs -->
      <nav class="flex items-center gap-2 text-xs font-normal text-stone-500">
        <Link href="/" class="hover:text-stone-900 transition-colors font-semibold text-stone-700">Home</Link>
        <span>/</span>
        <Link href="/cart" class="hover:text-stone-900 transition-colors font-semibold text-stone-700">Cart</Link>
        <span>/</span>
        <span class="text-stone-950 font-bold">Checkout</span>
      </nav>

      <!-- Mobile Collapsible Order Summary Bar (Shopify-Style: Zero Scroll Required) -->
      <div v-if="!orderPlaced && store.cartItems.length > 0" class="lg:hidden bg-white rounded-2xl border border-[#e0d9cc] overflow-hidden shadow-xs">
        <button
          type="button"
          @click="isMobileSummaryOpen = !isMobileSummaryOpen"
          class="w-full px-4 py-3 flex items-center justify-between text-xs font-semibold text-stone-800 bg-[#fbf9f5] hover:bg-[#f5efe4] transition-colors cursor-pointer"
        >
          <div class="flex items-center gap-2">
            <ShoppingBag class="w-4 h-4 text-[#a47a3c]" />
            <span class="font-bold">{{ isMobileSummaryOpen ? 'Hide order summary' : 'Show order summary' }}</span>
            <ChevronDown class="w-3.5 h-3.5 transition-transform text-stone-500 duration-200" :class="{ 'rotate-180': isMobileSummaryOpen }" />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-stone-500 font-normal">({{ store.totalItemCount }} {{ store.totalItemCount === 1 ? 'item' : 'items' }})</span>
            <span class="font-serif font-bold text-sm text-[#1d1d1f]">${{ checkoutTotal }}</span>
          </div>
        </button>

        <div v-show="isMobileSummaryOpen" class="p-4 border-t border-[#e0d9cc] space-y-3 bg-white animate-in slide-in-from-top-1 duration-150">
          <div class="max-h-52 overflow-y-auto divide-y divide-[#e0d9cc]/60 pr-1">
            <div
              v-for="item in store.cartItems"
              :key="item.id"
              class="py-2.5 flex items-center justify-between gap-3 text-xs"
            >
              <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0">
                  <img
                    :src="item.image || (item.name.toLowerCase().includes('atta') ? '/images/products/atta.jpg' : (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : (item.name.toLowerCase().includes('paneer') ? '/images/products/paneer.jpg' : (item.name.toLowerCase().includes('rice') ? '/images/products/rice.jpg' : (item.name.toLowerCase().includes('biscuit') ? '/images/products/biscuits.jpg' : (item.name.toLowerCase().includes('masala') ? '/images/products/garam_masala.jpg' : '/images/products/sweets.jpg'))))))"
                    :alt="item.name"
                    class="w-full h-full object-cover object-center"
                  />
                </div>
                <div class="min-w-0">
                  <div class="font-bold text-[#1d1d1f] truncate leading-tight">{{ item.name }}</div>
                  <div class="text-[11px] text-stone-600 mt-0.5">{{ item.quantity }}x · {{ item.weight }}</div>
                </div>
              </div>
              <div class="font-serif font-bold text-[#1d1d1f] shrink-0">
                ${{ (item.price * item.quantity).toFixed(2) }}
              </div>
            </div>
          </div>

          <div class="pt-2.5 border-t border-[#e0d9cc] space-y-2 text-xs">
            <div class="flex justify-between text-stone-600">
              <span>Subtotal</span>
              <span class="font-serif font-bold text-stone-900">${{ store.subtotal.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between text-stone-600">
              <span>Fulfillment ({{ fulfillmentMode === 'delivery' ? 'Home Delivery' : 'Store Pickup' }})</span>
              <span :class="deliveryFeeAmount === 0 ? 'text-[#8a6b32] font-bold' : 'text-stone-900 font-bold'">
                {{ deliveryFeeAmount === 0 ? 'FREE' : `$${deliveryFeeAmount.toFixed(2)}` }}
              </span>
            </div>
            <div class="pt-2 border-t border-[#e0d9cc] flex justify-between items-baseline text-[#1d1d1f]">
              <span class="font-serif font-semibold">Total Due</span>
              <span class="font-serif font-bold text-lg text-[#1d1d1f]">${{ checkoutTotal }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Confirmation State -->
      <div v-if="orderPlaced" class="bg-white rounded-3xl border border-[#e0d9cc] p-6 sm:p-10 text-center space-y-6 shadow-sm">
        <div class="w-16 h-16 bg-[#f5eee2] text-[#7a5620] rounded-full flex items-center justify-center mx-auto border border-[#e0d9cc]">
          <CheckCircle2 class="w-9 h-9 stroke-[2.2]" />
        </div>

        <div>
          <span class="text-[11px] font-semibold uppercase tracking-widest text-[#7a5620] bg-[#f5eee2] px-3 py-1 rounded-full border border-[#e0d9cc]">
            ORDER CONFIRMED {{ confirmedOrderNumber }}
          </span>
          <h2 class="text-2xl sm:text-4xl text-[#1d1d1f] tracking-tight mt-3 leading-tight font-serif font-medium">
            Thank you for your order!
          </h2>
          <p class="text-sm text-stone-600 mt-2.5 max-w-md mx-auto font-normal leading-relaxed">
            Your items are being hand-picked at <strong>{{ storeInfo.name || 'Masala Mart' }}</strong> and will be ready for <strong>{{ confirmedSlotLabel }}</strong>.
          </p>
        </div>

        <div class="max-w-md mx-auto bg-[#f3efe7] rounded-2xl p-5 border border-[#e0d9cc] text-left space-y-3 text-xs">
          <div class="flex justify-between items-center">
            <span class="text-stone-600 font-normal">Fulfillment Window:</span>
            <span class="font-bold text-[#1d1d1f]">{{ confirmedSlotLabel }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-stone-600 font-normal">Location / Address:</span>
            <span class="font-bold text-[#1d1d1f] truncate max-w-[240px]">
              {{ fulfillmentMode === 'delivery' ? deliveryAddress : (storeInfo.address || '214 Main St.') }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-stone-600 font-normal">Total Paid:</span>
            <span class="font-serif font-bold text-lg text-[#1d1d1f]">${{ confirmedTotalPaid }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-stone-600 font-normal">Masala Points Earned:</span>
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
            class="px-6 py-3.5 bg-[#f4efe6] text-[#1d1d1f] font-semibold text-xs rounded-full text-center hover:bg-[#ede6da] border border-[#dfd6c8] transition-colors"
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
        <div class="w-16 h-16 rounded-full bg-[#f3efe7] flex items-center justify-center mx-auto text-stone-600">
          <ShoppingBag class="w-8 h-8 stroke-[1.8]" />
        </div>
        <h2 class="text-xl font-serif font-medium text-[#1d1d1f]">Your cart is empty</h2>
        <p class="text-xs text-stone-600 font-normal leading-relaxed">
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
        <div class="lg:col-span-7 space-y-4 sm:space-y-5">

          <!-- STEP 01: FULFILLMENT METHOD & TIMINGS -->
          <div id="fulfillment-section" class="space-y-4 scroll-mt-6">
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

            <!-- Contextual Pickup Error Banner -->
            <div
              v-if="fulfillmentMode === 'pickup' && (pickupError || scheduleError)"
              class="p-4 bg-rose-50/95 border border-rose-200 text-rose-950 rounded-2xl flex items-start gap-3 shadow-xs animate-in fade-in slide-in-from-top-2 duration-200"
            >
              <div class="w-8 h-8 rounded-xl bg-rose-100 flex items-center justify-center shrink-0 text-rose-600 mt-0.5">
                <AlertCircle class="w-4.5 h-4.5 stroke-[2.2]" />
              </div>
              <div class="flex-1 text-xs">
                <div class="font-bold text-rose-950 flex items-center justify-between">
                  <span>Pickup Timing Issue</span>
                  <button
                    v-if="pickupError"
                    type="button"
                    @click="pickupError = ''; errorMessage = ''"
                    class="text-rose-500 hover:text-rose-800 text-[11px] font-semibold cursor-pointer"
                  >
                    Dismiss
                  </button>
                </div>
                <p class="text-rose-800 mt-1 leading-relaxed">{{ pickupError || scheduleError }}</p>
                <div class="mt-2 text-[11px] text-rose-700 font-medium">
                  Please pick an available window or select an exact minute below:
                </div>
              </div>
            </div>

            <!-- Fulfillment Mode Switcher Tabs (Store Pickup vs Home Delivery) -->
            <div
              v-if="storeInfo.is_delivery_active"
              class="grid grid-cols-2 p-1.5 bg-[#f4efe6] rounded-2xl border border-[#dfd6c8]"
            >
              <button
                type="button"
                @click="fulfillmentMode = 'pickup'"
                class="py-3 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="fulfillmentMode === 'pickup'
                  ? 'bg-[#1a1a1a] text-white shadow-xs'
                  : 'text-stone-700 hover:text-stone-900 font-semibold'"
              >
                <Store class="w-4 h-4" :class="fulfillmentMode === 'pickup' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'" />
                <span>Store Pickup (Free)</span>
              </button>

              <button
                type="button"
                @click="fulfillmentMode = 'delivery'"
                class="py-3 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="fulfillmentMode === 'delivery'
                  ? 'bg-[#1a1a1a] text-white shadow-xs'
                  : 'text-stone-700 hover:text-stone-900 font-semibold'"
              >
                <Truck class="w-4 h-4" :class="fulfillmentMode === 'delivery' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'" />
                <span>Home Delivery {{ isDeliveryFree ? '(Free)' : `($${deliveryOptionFee.toFixed(2)})` }}</span>
              </button>
            </div>

            <!-- OPTION A: STORE PICKUP SELECTED -->
            <div v-if="fulfillmentMode === 'pickup'" class="space-y-3">

              <!-- Contextual Pickup/Schedule Error Banner -->
              <div
                v-if="pickupError || scheduleError"
                class="p-4 bg-rose-50 border border-rose-300 text-rose-950 rounded-2xl flex items-center justify-between gap-3 text-xs sm:text-sm shadow-xs font-medium"
              >
                <div class="flex items-center gap-2">
                  <AlertCircle class="w-5 h-5 text-rose-600 shrink-0" />
                  <span>{{ pickupError || scheduleError }}</span>
                </div>
                <button
                  type="button"
                  @click="isScheduleModalOpen = true"
                  class="text-xs font-bold text-rose-700 underline hover:text-rose-900 cursor-pointer shrink-0"
                >
                  Change Time
                </button>
              </div>

              <!-- Store & Pickup Timing Card (Uber Eats / Instacart Style) -->
              <div class="p-4 sm:p-5 bg-white rounded-3xl border border-[#e0d9cc] space-y-3.5 shadow-xs">
                <!-- Top Row: Store Info -->
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-[#fbf9f5] border border-[#e0d9cc] flex items-center justify-center shrink-0 text-[#a47a3c]">
                      <Store class="w-5 h-5 stroke-[2]" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-serif font-bold text-sm sm:text-base text-[#1d1d1f] truncate">
                        {{ storeInfo.name || 'Masala Mart — Main St.' }}
                      </div>
                      <div class="text-xs text-stone-500 truncate">
                        {{ storeInfo.address || '214 Main St.' }} · {{ storeInfo.opening_hours || 'Daily 9:00 AM – 9:00 PM' }}
                      </div>
                    </div>
                  </div>
                  <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200 shrink-0">
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    Free Pickup
                  </span>
                </div>

                <div class="border-t border-[#e0d9cc]/60"></div>

                <!-- Bottom Row: Pickup Slot + Change Action -->
                <div class="flex items-center justify-between gap-3 bg-[#fbf9f5] p-3.5 sm:p-4 rounded-2xl border border-[#e0d9cc]/80">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-white border border-[#e0d9cc] flex items-center justify-center shrink-0 text-[#a47a3c] shadow-2xs">
                      <Zap v-if="pickupTimingMode === 'asap'" class="w-4 h-4 text-[#a47a3c]" />
                      <Clock v-else class="w-4 h-4 text-[#a47a3c]" />
                    </div>
                    <div class="min-w-0">
                      <div class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                        {{ pickupTimingMode === 'asap' ? 'Estimated Pickup' : 'Scheduled Pickup' }}
                      </div>
                      <div class="font-bold text-xs sm:text-sm text-[#1d1d1f] truncate">
                        {{ effectiveFulfillmentSlotLabel }}
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="isScheduleModalOpen = true"
                    class="px-3.5 py-1.5 rounded-xl border border-[#a47a3c] bg-white hover:bg-[#a47a3c] text-[#a47a3c] hover:text-white text-xs font-bold transition-all flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs active:scale-95"
                  >
                    <span>Change</span>
                    <ChevronRight class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

            </div>

            <!-- OPTION B: HOME DELIVERY SELECTED -->
            <div v-else-if="fulfillmentMode === 'delivery'" id="delivery-section" class="space-y-4 animate-in fade-in duration-150 scroll-mt-6">

              <!-- Contextual Delivery Error Banner -->
              <div
                v-if="deliveryError"
                class="p-4 bg-rose-50 border border-rose-300 text-rose-950 rounded-2xl flex items-center gap-3 text-xs sm:text-sm shadow-xs font-medium"
              >
                <AlertCircle class="w-5 h-5 text-rose-600 shrink-0" />
                <span>{{ deliveryError }}</span>
              </div>

              <!-- Delivery Schedule & Status Banner -->
              <div
                class="p-4 sm:p-5 rounded-2xl border space-y-2"
                :class="storeInfo.is_delivery_available_today
                  ? 'bg-emerald-50 text-emerald-950 border-emerald-300'
                  : 'bg-amber-50 text-amber-950 border-amber-300'"
              >
                <div class="flex items-center gap-2 font-bold text-sm">
                  <Truck class="w-4.5 h-4.5 text-[#a47a3c]" />
                  <span>{{ storeInfo.is_delivery_available_today ? 'Delivery is active today!' : 'Home Delivery Schedule Notice' }}</span>
                </div>
                <div class="text-xs leading-relaxed font-medium">
                  <span v-if="storeInfo.is_delivery_available_today">
                    Your order will be hand-delivered during today's window: <strong class="text-stone-900">{{ storeInfo.delivery_estimated_time || '5:00 PM – 8:00 PM' }}</strong>.
                  </span>
                  <span v-else>
                    Weekly delivery is active on <strong>{{ Array.isArray(storeInfo.delivery_days) ? storeInfo.delivery_days.map(d => d.toUpperCase()).join(', ') : 'weekends' }}</strong>. Orders placed today will be delivered on the next scheduled run.
                  </span>
                </div>
              </div>

              <!-- Address Inputs -->
              <div class="p-5 sm:p-6 bg-white rounded-3xl border border-[#e0d9cc] space-y-4 shadow-xs">
                <div class="text-sm font-bold text-[#1d1d1f] flex items-center gap-2">
                  <MapPin class="w-4.5 h-4.5 text-[#a47a3c]" />
                  <span>Delivery Address</span>
                </div>

                <div>
                  <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1.5">
                    Street Address *
                  </Label>
                  <Input
                    v-model="deliveryAddress"
                    type="text"
                    required
                    placeholder="e.g. 142 Oak Tree Rd"
                    class="w-full bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] text-[#1d1d1f] text-sm font-semibold h-12 rounded-xl shadow-xs transition-all"
                  />
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1.5">
                      Apt / Suite / Unit (Optional)
                    </Label>
                    <Input
                      v-model="deliveryApt"
                      type="text"
                      placeholder="e.g. Apt 4B"
                      class="w-full bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] text-[#1d1d1f] text-sm font-semibold h-12 rounded-xl shadow-xs transition-all"
                    />
                  </div>
                  <div>
                    <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1.5">
                      Driver Notes (Optional)
                    </Label>
                    <Input
                      v-model="deliveryNotes"
                      type="text"
                      placeholder="e.g. Leave at front door"
                      class="w-full bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] text-[#1d1d1f] text-sm font-semibold h-12 rounded-xl shadow-xs transition-all"
                    />
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!-- STEP 02: PAYMENT -->
          <div id="payment-section" class="bg-white rounded-3xl border border-[#e0d9cc] p-4 sm:p-6 space-y-4 shadow-xs scroll-mt-6">
            <div class="flex items-center justify-between">
              <div class="text-xs font-bold text-stone-900 uppercase tracking-wider flex items-center gap-2">
                <Lock class="w-4 h-4 text-[#a47a3c]" />
                <span>02 · PAYMENT</span>
              </div>
              <span class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">
                256-Bit Encrypted
              </span>
            </div>

            <!-- Dynamic Device-Aware Payment Method Selector -->
            <div v-if="availablePaymentMethods.length > 1" :class="[
              'grid gap-2',
              availablePaymentMethods.length === 3 ? 'grid-cols-3' : 'grid-cols-2'
            ]">
              <button
                type="button"
                @click="paymentMethod = 'card'"
                :class="[
                  'h-11 sm:h-12 px-2.5 rounded-2xl flex items-center justify-center gap-1.5 transition-all cursor-pointer font-bold text-xs active:scale-[0.98] border',
                  paymentMethod === 'card'
                    ? 'bg-[#1a1a1a] text-white border-[#1a1a1a] shadow-xs'
                    : 'bg-[#fbf9f5] hover:bg-stone-100 text-stone-700 border-[#e0d9cc]'
                ]"
              >
                <CreditCard class="w-4 h-4 text-[#e4b97a]" />
                <span class="truncate">Card</span>
              </button>

              <button
                v-if="availablePaymentMethods.includes('apple-pay')"
                type="button"
                @click="paymentMethod = 'apple-pay'"
                :class="[
                  'h-11 sm:h-12 px-2.5 rounded-2xl flex items-center justify-center transition-all cursor-pointer active:scale-[0.98] border',
                  paymentMethod === 'apple-pay'
                    ? 'bg-black text-white border-black shadow-xs ring-2 ring-[#a47a3c]/30'
                    : 'bg-[#1a1a1a] hover:bg-black text-white border-[#333]'
                ]"
                aria-label="Apple Pay"
              >
                <IconApplePay :width="46" :height="20" class="text-white" />
              </button>

              <button
                v-if="availablePaymentMethods.includes('google-pay')"
                type="button"
                @click="paymentMethod = 'google-pay'"
                :class="[
                  'h-11 sm:h-12 px-2.5 rounded-2xl flex items-center justify-center transition-all cursor-pointer active:scale-[0.98] border',
                  paymentMethod === 'google-pay'
                    ? 'bg-white text-stone-900 border-[#a47a3c] shadow-xs ring-2 ring-[#a47a3c]/30'
                    : 'bg-[#fbf9f5] hover:bg-white text-stone-800 border-[#e0d9cc]'
                ]"
                aria-label="Google Wallet"
              >
                <IconGooglePay :width="48" :height="20" />
              </button>
            </div>

            <!-- Single Card Banner when only card is active on this device -->
            <div v-else class="flex items-center justify-between p-3.5 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc]">
              <div class="flex items-center gap-2">
                <CreditCard class="w-4 h-4 text-[#a47a3c]" />
                <span class="text-xs font-bold text-stone-900">Credit or Debit Card</span>
              </div>
              <span class="text-[11px] text-stone-500 font-medium">Stripe Live Checkout</span>
            </div>

            <!-- Device Detection Status & Dev Testing Toggle -->
            <div class="flex items-center justify-between text-[11px] text-stone-500 px-1 pt-0.5">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span v-if="isApplePayAvailable">Apple Device Detected (Apple Pay enabled)</span>
                <span v-else-if="isGooglePayAvailable">Chrome / Android Detected (Google Pay enabled)</span>
                <span v-else>Card checkout configured</span>
              </span>
              <button
                type="button"
                @click="showAllPaymentMethodsForTesting = !showAllPaymentMethodsForTesting"
                class="text-[#a47a3c] hover:underline font-semibold cursor-pointer"
              >
                {{ showAllPaymentMethodsForTesting ? 'Hide other wallets' : 'Show all wallets' }}
              </button>
            </div>

            <!-- TAB 1: CARD -->
            <div v-show="paymentMethod === 'card'" class="space-y-3">
              <!-- Stripe Live Elements (when STRIPE_KEY is set in .env) -->
              <div v-if="isStripeConfigured" class="space-y-3">
                <!-- Loading State (Sibling, never mounted inside Stripe target) -->
                <div v-if="isStripeLoading" class="flex flex-col items-center justify-center py-8 space-y-2 min-h-[120px]">
                  <Loader2 class="w-6 h-6 animate-spin text-[#a47a3c]" />
                  <span class="text-xs text-stone-500 font-semibold">Loading Stripe payment fields...</span>
                </div>

                <!-- Pure Stripe Container: Always kept completely clean of Vue template children -->
                <div
                  id="stripe-payment-element-mount"
                  class="min-h-[120px] pt-1"
                  :class="{ hidden: isStripeLoading }"
                ></div>

                <div v-if="stripeError" class="p-3 bg-rose-50 border border-rose-200 text-rose-900 text-xs rounded-xl flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{{ stripeError }}</span>
                  </div>
                  <button
                    type="button"
                    @click="isStripeMounted = false; initStripe()"
                    class="px-2.5 py-1 text-[11px] font-bold bg-rose-100 hover:bg-rose-200 text-rose-900 rounded-lg shrink-0 cursor-pointer"
                  >
                    Retry
                  </button>
                </div>
              </div>

              <!-- Stripe Direct Card Fields (Clean minimal fields) -->
              <div v-else class="space-y-3">
                <div>
                  <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1">
                    Card number
                  </Label>
                  <div class="relative flex items-center">
                    <Input
                      type="text"
                      placeholder="1234 1234 1234 1234"
                      value="1234 1234 1234 1234"
                      class="w-full px-4 h-11 bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] rounded-xl text-sm font-semibold text-stone-950 placeholder-stone-400 shadow-xs transition-all"
                    />
                    <div class="absolute right-3 flex items-center gap-1.5">
                      <Badge variant="outline" class="text-[10px] font-bold font-mono px-2 py-0.5 bg-[#f4efe6] border-[#dfd6c8] text-stone-800 rounded-md">VISA</Badge>
                      <Badge variant="outline" class="text-[10px] font-bold font-mono px-2 py-0.5 bg-[#f4efe6] border-[#dfd6c8] text-stone-800 rounded-md">MC</Badge>
                      <Badge variant="outline" class="text-[10px] font-bold font-mono px-2 py-0.5 bg-[#f4efe6] border-[#dfd6c8] text-stone-800 rounded-md">AMEX</Badge>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                  <div>
                    <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1">
                      Expires (MM/YY)
                    </Label>
                    <Input
                      type="text"
                      placeholder="12/28"
                      value="12/28"
                      class="w-full px-4 h-11 bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] rounded-xl text-sm font-semibold text-stone-950 placeholder-stone-400 shadow-xs transition-all"
                    />
                  </div>
                  <div>
                    <Label class="block text-xs font-bold text-stone-800 uppercase tracking-wider mb-1">
                      CVV
                    </Label>
                    <Input
                      type="password"
                      placeholder="•••"
                      value="123"
                      class="w-full px-4 h-11 bg-[#fbf9f5] hover:bg-white focus:bg-white border border-[#e0d9cc] focus:border-[#1a1a1a] rounded-xl text-sm font-semibold text-stone-950 placeholder-stone-400 shadow-xs font-mono transition-all"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 2: APPLE PAY (Apple Wallet) -->
            <div v-show="paymentMethod === 'apple-pay'" class="p-4 sm:p-5 bg-gradient-to-b from-[#1a1a1a] to-black rounded-2xl text-white space-y-3.5 shadow-sm animate-in fade-in duration-150">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center">
                    <IconApplePay :width="40" :height="17" class="text-white" />
                  </div>
                  <div>
                    <div class="text-xs font-bold text-white flex items-center gap-1.5">
                      <span>Apple Pay · Apple Wallet</span>
                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Ready</span>
                    </div>
                    <div class="text-[11px] text-stone-400">Touch ID / Face ID Biometric Authorization</div>
                  </div>
                </div>
              </div>

              <div class="p-3 bg-white/5 rounded-xl border border-white/10 text-xs text-stone-300 flex items-center justify-between">
                <span class="text-stone-400">Apple Wallet Card:</span>
                <span class="font-mono text-white font-bold">•••• 8824 (Apple Card)</span>
              </div>

              <button
                type="button"
                @click="completeOrder"
                :disabled="isProcessing"
                class="w-full h-11 bg-white hover:bg-stone-100 text-black font-bold text-xs rounded-xl flex items-center justify-center gap-2 transition-all cursor-pointer active:scale-[0.99] disabled:opacity-50"
              >
                <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin text-black" />
                <IconApplePay v-else :width="36" :height="16" class="text-black" />
                <span>{{ isProcessing ? 'Authorizing Biometrics...' : `Authorize & Pay $${checkoutTotal}` }}</span>
              </button>
            </div>

            <!-- TAB 3: GOOGLE WALLET (Google Pay) -->
            <div v-show="paymentMethod === 'google-pay'" class="p-4 sm:p-5 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc] space-y-3.5 shadow-2xs animate-in fade-in duration-150">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-xl bg-white border border-[#e0d9cc] flex items-center justify-center">
                    <IconGooglePay :width="38" :height="16" />
                  </div>
                  <div>
                    <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                      <span>Google Wallet · Google Pay</span>
                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">Ready</span>
                    </div>
                    <div class="text-[11px] text-stone-500">Google Account Linked · 1-Tap Checkout</div>
                  </div>
                </div>
              </div>

              <div class="p-3 bg-white rounded-xl border border-[#e0d9cc]/80 text-xs text-stone-600 flex items-center justify-between">
                <span class="text-stone-500">Selected Google Wallet Card:</span>
                <span class="font-mono text-stone-900 font-bold">•••• 4012 (Google Pay)</span>
              </div>

              <button
                type="button"
                @click="completeOrder"
                :disabled="isProcessing"
                class="w-full h-11 bg-[#1a1a1a] hover:bg-black text-white font-bold text-xs rounded-xl flex items-center justify-center gap-2 transition-all cursor-pointer active:scale-[0.99] disabled:opacity-50"
              >
                <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin text-white" />
                <IconGooglePay v-else :width="38" :height="16" />
                <span>{{ isProcessing ? 'Authorizing Google Wallet...' : `Pay $${checkoutTotal} with Google Wallet` }}</span>
              </button>
            </div>

            <!-- Minimum Order Amount Warning -->
            <div v-if="Number(page.props.minOrderAmount || 0) > 0 && store.subtotal < Number(page.props.minOrderAmount)" class="p-3 bg-amber-50 border border-amber-200 text-amber-900 text-xs rounded-xl flex items-center justify-between">
              <span class="font-medium">Minimum order required: ${{ Number(page.props.minOrderAmount).toFixed(2) }}</span>
              <span class="font-bold text-amber-950">Add ${{ (Number(page.props.minOrderAmount) - store.subtotal).toFixed(2) }} more</span>
            </div>

            <!-- Payment / General Error Banner -->
            <div v-if="paymentError || generalError" class="p-3 bg-rose-50 border border-rose-200 text-rose-900 text-xs rounded-xl flex items-start gap-2.5 shadow-xs">
              <AlertCircle class="w-4 h-4 shrink-0 text-rose-600 mt-0.5" />
              <div class="flex-1">
                <span class="font-semibold block text-rose-950">Payment Authorization Issue</span>
                <span class="text-rose-800 leading-relaxed">{{ paymentError || generalError }}</span>
              </div>
            </div>

            <!-- Desktop Pay Button -->
            <div class="pt-1 hidden sm:block">
              <Button
                type="button"
                @click="completeOrder"
                :disabled="isProcessing || (fulfillmentMode === 'pickup' && Boolean(scheduleError)) || (Number(page.props.minOrderAmount || 0) > 0 && store.subtotal < Number(page.props.minOrderAmount))"
                class="w-full h-13 bg-[#1a1a1a] hover:bg-black text-white font-bold text-sm rounded-full transition-all cursor-pointer flex items-center justify-between px-6 shadow-md active:scale-[0.99] disabled:opacity-60"
              >
                <div class="flex items-center gap-2">
                  <Loader2 v-if="isProcessing" key="desktop-loading-spinner" class="w-4 h-4 animate-spin" />
                  <Lock v-else key="desktop-lock-icon" class="w-4 h-4 stroke-[2.2] text-[#e4b97a]" />
                  <span v-if="paymentMethod === 'apple-pay'">
                    {{ isProcessing ? 'Authorizing Apple Pay...' : `Pay with Apple Pay · ${effectiveFulfillmentSlotLabel}` }}
                  </span>
                  <span v-else-if="paymentMethod === 'google-pay'">
                    {{ isProcessing ? 'Authorizing Google Wallet...' : `Pay with Google Wallet · ${effectiveFulfillmentSlotLabel}` }}
                  </span>
                  <span v-else>
                    {{ isProcessing ? 'Authorizing Payment...' : `Complete Order · ${effectiveFulfillmentSlotLabel}` }}
                  </span>
                </div>
                <span class="font-serif font-bold text-base text-[#e4b97a]">${{ checkoutTotal }}</span>
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
          :disabled="isProcessing || (fulfillmentMode === 'pickup' && Boolean(scheduleError)) || (Number(page.props.minOrderAmount || 0) > 0 && store.subtotal < Number(page.props.minOrderAmount))"
          class="w-full h-13 py-3.5 bg-[#1a1a1a] active:bg-black text-white font-semibold text-sm rounded-full cursor-pointer flex items-center justify-between px-6 shadow-md disabled:opacity-60"
        >
          <div class="flex items-center gap-2">
            <Loader2 v-if="isProcessing" key="mobile-loading-spinner" class="w-4 h-4 animate-spin" />
            <Lock v-else key="mobile-lock-icon" class="w-4 h-4 stroke-[2.2] text-[#e4b97a]" />
            <span v-if="paymentMethod === 'apple-pay'">
              {{ isProcessing ? 'Authorizing Apple Pay...' : `Apple Pay · $${checkoutTotal}` }}
            </span>
            <span v-else-if="paymentMethod === 'google-pay'">
              {{ isProcessing ? 'Authorizing Google Wallet...' : `Google Wallet · $${checkoutTotal}` }}
            </span>
            <span v-else>
              {{ isProcessing ? 'Authorizing Payment...' : `Pay · $${checkoutTotal}` }}
            </span>
          </div>
          <span class="text-xs text-[#e4b97a] font-semibold truncate max-w-[140px]">
            {{ effectiveFulfillmentSlotLabel }}
          </span>
        </button>
      </div>

      <!-- Mobile Floating Error Toast -->
      <div
        v-if="!orderPlaced && (pickupError || deliveryError || paymentError || generalError || (fulfillmentMode === 'pickup' && scheduleError))"
        class="sm:hidden fixed bottom-22 left-3 right-3 z-50 p-3.5 bg-stone-900/95 backdrop-blur-md text-white text-xs rounded-2xl shadow-2xl flex items-center justify-between border border-rose-500/40 animate-in fade-in slide-in-from-bottom-3 duration-200"
      >
        <div class="flex items-center gap-2 pr-2 min-w-0">
          <AlertCircle class="w-4 h-4 text-rose-400 shrink-0" />
          <span class="font-medium text-stone-100 truncate text-[11px]">
            {{ pickupError || scheduleError || deliveryError || paymentError || generalError }}
          </span>
        </div>
        <button
          type="button"
          @click="scrollToError"
          class="text-[11px] font-bold bg-[#a47a3c] hover:bg-[#8a6b32] text-white px-3 py-1.5 rounded-xl shrink-0 cursor-pointer shadow-xs active:scale-95 transition-transform"
        >
          Fix ➔
        </button>
      </div>

      <!-- SCHEDULE MODAL (Uber Eats / Instacart Pop-up Sheet) -->
      <Teleport to="body" v-if="isScheduleModalOpen">
        <div
          class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200"
          @click.self="isScheduleModalOpen = false"
        >
          <div
            class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl border border-[#e0d9cc] max-h-[90vh] sm:max-h-[85vh] flex flex-col overflow-hidden animate-in slide-in-from-bottom-6 duration-200"
          >
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-[#e0d9cc]/70 flex items-center justify-between bg-[#fbf9f5]">
              <div>
                <h3 class="font-serif font-bold text-base sm:text-lg text-stone-900">
                  Choose Pickup Time
                </h3>
                <p class="text-xs text-stone-500">
                  {{ storeInfo.name || 'Masala Mart' }} · Prepared fresh
                </p>
              </div>
              <button
                type="button"
                @click="isScheduleModalOpen = false"
                class="w-8 h-8 rounded-full bg-stone-200/70 hover:bg-stone-300 text-stone-700 flex items-center justify-center transition-all cursor-pointer"
                aria-label="Close"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Scrollable Content -->
            <div class="p-4 sm:p-6 overflow-y-auto space-y-4">
              <!-- Timing Mode (ASAP vs Schedule) -->
              <div class="grid grid-cols-2 gap-2.5">
                <!-- ASAP Option Button -->
                <button
                  type="button"
                  :disabled="!isAsapAvailable"
                  @click="isAsapAvailable ? pickupTimingMode = 'asap' : null"
                  class="p-3 sm:p-3.5 rounded-2xl border text-left transition-all relative cursor-pointer"
                  :class="[
                    !isAsapAvailable
                      ? 'bg-stone-100 border-stone-200 text-stone-400 cursor-not-allowed opacity-80'
                      : (pickupTimingMode === 'asap'
                          ? 'bg-[#1a1a1a] text-white border-[#a47a3c]'
                          : 'bg-white hover:bg-stone-50 text-[#1d1d1f] border-[#e0d9cc] hover:border-stone-400')
                  ]"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 min-w-0">
                      <Zap class="w-3.5 h-3.5 shrink-0" :class="pickupTimingMode === 'asap' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'" />
                      <span class="text-xs sm:text-sm font-bold tracking-tight truncate">Fastest ASAP</span>
                    </div>
                    <span
                      v-if="!isAsapAvailable"
                      class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700"
                    >Closed</span>
                    <CheckCircle2 v-else-if="pickupTimingMode === 'asap'" class="w-3.5 h-3.5 text-[#e4b97a] shrink-0" />
                  </div>

                  <div class="mt-1.5">
                    <div
                      class="text-xs font-bold truncate"
                      :class="pickupTimingMode === 'asap' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'"
                    >
                      {{ isAsapAvailable ? `~${effectivePrepTime} mins` : (storeOpenTimeFormatted || '11:00 AM') }}
                    </div>
                    <div
                      class="text-[10px] mt-0.5 truncate"
                      :class="pickupTimingMode === 'asap' ? 'text-stone-300' : 'text-stone-500'"
                    >
                      {{ isAsapAvailable ? (isStoreBusy ? 'Rush buffer (+15m)' : 'Standard packing') : 'Please schedule' }}
                    </div>
                  </div>
                </button>

                <!-- Schedule for Later Option Button -->
                <button
                  type="button"
                  @click="pickupTimingMode = 'scheduled'"
                  class="p-3 sm:p-3.5 rounded-2xl border text-left transition-all relative cursor-pointer"
                  :class="[
                    pickupTimingMode === 'scheduled'
                      ? 'bg-[#1a1a1a] text-white border-[#a47a3c]'
                      : 'bg-white hover:bg-stone-50 text-[#1d1d1f] border-[#e0d9cc] hover:border-stone-400'
                  ]"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 min-w-0">
                      <Calendar class="w-3.5 h-3.5 shrink-0" :class="pickupTimingMode === 'scheduled' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'" />
                      <span class="text-xs sm:text-sm font-bold tracking-tight truncate">Schedule</span>
                    </div>
                    <CheckCircle2 v-if="pickupTimingMode === 'scheduled'" class="w-3.5 h-3.5 text-[#e4b97a] shrink-0" />
                  </div>

                  <div class="mt-1.5">
                    <div
                      class="text-xs font-bold truncate"
                      :class="pickupTimingMode === 'scheduled' ? 'text-[#e4b97a]' : 'text-[#a47a3c]'"
                    >
                      {{ selectedScheduledTime || 'Pick a Slot' }}
                    </div>
                    <div
                      class="text-[10px] mt-0.5 truncate"
                      :class="pickupTimingMode === 'scheduled' ? 'text-stone-300' : 'text-stone-500'"
                    >
                      {{ scheduledDay === 'today' ? 'Today' : 'Tomorrow' }} · Convenient
                    </div>
                  </div>
                </button>
              </div>

              <!-- Customer Time Slot Picker (When 'Schedule for Later' is selected) -->
              <div v-if="pickupTimingMode === 'scheduled'" class="space-y-4 pt-1 animate-in fade-in duration-150">
                <!-- 1. Day Switcher -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-xs font-bold text-stone-900 uppercase tracking-wider">
                    <span>1. Select Pickup Day</span>
                    <span class="text-xs font-normal text-stone-500">Prepared fresh before arrival</span>
                  </div>

                  <div class="grid grid-cols-2 gap-2.5">
                    <button
                      type="button"
                      :disabled="!isTodayAvailable"
                      @click="isTodayAvailable ? scheduledDay = 'today' : null"
                      class="p-3 rounded-xl border text-left transition-all relative flex flex-col justify-between cursor-pointer"
                      :class="[
                        !isTodayAvailable
                          ? 'bg-stone-100 border-stone-200 text-stone-400 cursor-not-allowed opacity-60'
                          : (scheduledDay === 'today'
                              ? 'bg-[#1a1a1a] text-white border-[#a47a3c]'
                              : 'bg-white hover:bg-stone-50 text-[#1d1d1f] border-[#e0d9cc] hover:border-stone-400')
                      ]"
                    >
                      <div class="flex items-center justify-between">
                        <span class="text-xs sm:text-sm font-bold">Today</span>
                        <span
                          v-if="!isTodayAvailable"
                          class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-stone-200 text-stone-700"
                        >Closed</span>
                        <div
                          v-else-if="scheduledDay === 'today'"
                          class="w-2 h-2 rounded-full bg-[#e4b97a]"
                        ></div>
                      </div>
                      <div class="text-[11px] font-semibold mt-1" :class="scheduledDay === 'today' ? 'text-[#e4b97a]' : 'text-stone-600'">
                        {{ todayFormatted }}
                      </div>
                    </button>

                    <button
                      type="button"
                      :disabled="!isTomorrowAvailable"
                      @click="isTomorrowAvailable ? scheduledDay = 'tomorrow' : null"
                      class="p-3 rounded-xl border text-left transition-all relative flex flex-col justify-between cursor-pointer"
                      :class="[
                        !isTomorrowAvailable
                          ? 'bg-stone-100 border-stone-200 text-stone-400 cursor-not-allowed opacity-60'
                          : (scheduledDay === 'tomorrow'
                              ? 'bg-[#1a1a1a] text-white border-[#a47a3c]'
                              : 'bg-white hover:bg-stone-50 text-[#1d1d1f] border-[#e0d9cc] hover:border-stone-400')
                      ]"
                    >
                      <div class="flex items-center justify-between">
                        <span class="text-xs sm:text-sm font-bold">Tomorrow</span>
                        <span
                          v-if="!isTomorrowAvailable"
                          class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-stone-200 text-stone-700"
                        >Closed</span>
                        <div
                          v-else-if="scheduledDay === 'tomorrow'"
                          class="w-2 h-2 rounded-full bg-[#e4b97a]"
                        ></div>
                      </div>
                      <div class="text-[11px] font-semibold mt-1" :class="scheduledDay === 'tomorrow' ? 'text-[#e4b97a]' : 'text-stone-600'">
                        {{ tomorrowFormatted }}
                      </div>
                    </button>
                  </div>
                </div>

                <!-- 2. Timing Preference Switcher -->
                <div class="flex items-center justify-between pt-2.5 border-t border-[#e0d9cc]">
                  <span class="text-xs font-bold text-stone-900 uppercase tracking-wider">2. Timing Preference</span>
                  <div class="inline-flex bg-[#f4efe6] p-1 rounded-xl border border-[#dfd6c8] text-xs">
                    <button
                      type="button"
                      @click="scheduledTimingType = 'slot'"
                      class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                      :class="scheduledTimingType === 'slot' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-stone-700 hover:text-stone-900'"
                    >
                      {{ pickupSlotWindowLabel }}
                    </button>
                    <button
                      type="button"
                      @click="scheduledTimingType = 'custom'"
                      class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                      :class="scheduledTimingType === 'custom' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-stone-700 hover:text-stone-900'"
                    >
                      <span>Custom Time</span>
                      <span class="w-1.5 h-1.5 rounded-full bg-[#a47a3c]"></span>
                    </button>
                  </div>
                </div>

                <!-- Closed on Day Notice -->
                <div v-if="!isDayOpen" class="p-3 bg-amber-50 rounded-xl border border-amber-300 text-xs text-amber-950 flex items-center gap-2.5">
                  <AlertCircle class="w-4 h-4 text-amber-700 shrink-0" />
                  <span>Store pickup is closed on this day. Please select another day.</span>
                </div>

                <!-- SUB-OPTION A: TIME SLOT GRID WITH PERIOD FILTERS -->
                <div v-if="scheduledTimingType === 'slot'" class="space-y-2.5 pt-0.5">
                  <!-- Period Filter Pills -->
                  <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <button
                        type="button"
                        @click="slotPeriodFilter = 'all'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 border"
                        :class="slotPeriodFilter === 'all'
                          ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]'
                          : 'bg-[#f4efe6] text-stone-800 hover:bg-[#ede6da] border-[#dfd6c8]'"
                      >
                        <LayoutGrid class="w-3 h-3" />
                        <span>All</span>
                        <span class="text-[10px] opacity-80">({{ slotCountsByPeriod.all }})</span>
                      </button>

                      <button
                        v-if="slotCountsByPeriod.morning > 0"
                        type="button"
                        @click="slotPeriodFilter = 'morning'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 border"
                        :class="slotPeriodFilter === 'morning'
                          ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]'
                          : 'bg-[#f4efe6] text-stone-800 hover:bg-[#ede6da] border-[#dfd6c8]'"
                      >
                        <Sunrise class="w-3 h-3 text-[#e4b97a]" />
                        <span>Morning</span>
                      </button>

                      <button
                        v-if="slotCountsByPeriod.afternoon > 0"
                        type="button"
                        @click="slotPeriodFilter = 'afternoon'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 border"
                        :class="slotPeriodFilter === 'afternoon'
                          ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]'
                          : 'bg-[#f4efe6] text-stone-800 hover:bg-[#ede6da] border-[#dfd6c8]'"
                      >
                        <Sun class="w-3 h-3 text-[#e4b97a]" />
                        <span>Afternoon</span>
                      </button>

                      <button
                        v-if="slotCountsByPeriod.evening > 0"
                        type="button"
                        @click="slotPeriodFilter = 'evening'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 border"
                        :class="slotPeriodFilter === 'evening'
                          ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]'
                          : 'bg-[#f4efe6] text-stone-800 hover:bg-[#ede6da] border-[#dfd6c8]'"
                      >
                        <Moon class="w-3 h-3 text-indigo-400" />
                        <span>Evening</span>
                      </button>
                    </div>

                    <button
                      type="button"
                      @click="scheduledTimingType = 'custom'"
                      class="text-xs text-[#a47a3c] hover:text-[#8a6b32] font-bold cursor-pointer flex items-center gap-1 ml-auto"
                    >
                      Exact minute ➔
                    </button>
                  </div>

                  <!-- Slot Grid: 2 or 3 columns with high contrast and compact max-height -->
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-56 overflow-y-auto pr-1">
                    <button
                      v-for="slot in filteredScheduledSlots"
                      :key="slot.label"
                      type="button"
                      :disabled="slot.disabled"
                      @click="!slot.disabled ? selectedScheduledTime = slot.label : null"
                      class="p-2.5 rounded-xl border text-center transition-all flex flex-col items-center justify-center min-h-[46px] relative"
                      :class="[
                        slot.disabled
                          ? 'bg-stone-100 text-stone-400 border-stone-200 cursor-not-allowed opacity-60'
                          : (selectedScheduledTime === slot.label
                              ? 'bg-[#1a1a1a] text-white font-bold border-[#a47a3c] cursor-pointer'
                              : 'bg-white hover:bg-stone-50 text-[#1d1d1f] font-bold border-[#e0d9cc] hover:border-[#1a1a1a] cursor-pointer')
                      ]"
                    >
                      <div class="flex items-center justify-center gap-1.5 w-full">
                        <span class="text-xs sm:text-sm font-bold tracking-tight">{{ slot.label }}</span>
                        <CheckCircle2 v-if="selectedScheduledTime === slot.label" class="w-3.5 h-3.5 text-[#e4b97a] shrink-0" />
                      </div>
                      <span v-if="slot.isFull" class="text-[9px] font-bold text-rose-700 mt-0.5 tracking-wider bg-rose-100 px-1.5 py-0.2 rounded-full border border-rose-300">
                        FULL
                      </span>
                      <span v-else-if="slot.disabled" class="text-[9px] text-stone-400 font-medium mt-0.5">
                        Unavailable
                      </span>
                    </button>
                  </div>

                  <!-- Confirmation Banner -->
                  <div
                    v-if="selectedScheduledTime && !availableScheduledSlots.find(s => s.label === selectedScheduledTime)?.disabled"
                    class="p-2.5 bg-emerald-50 border border-emerald-300 rounded-xl flex items-center gap-2.5 text-xs text-emerald-950 font-medium"
                  >
                    <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0 stroke-[2.2]" />
                    <div>
                      Pickup: <strong class="font-bold text-emerald-950">{{ scheduledDay === 'today' ? 'Today' : 'Tomorrow' }}, {{ selectedScheduledTime }}</strong>
                    </div>
                  </div>
                </div>

                <!-- SUB-OPTION B: CUSTOM EXACT TIME PICKER -->
                <div v-else class="space-y-4 p-4 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc]">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-[#f5eee2] text-[#7a5620] flex items-center justify-center border border-[#e0d9cc] shrink-0">
                        <Clock class="w-4.5 h-4.5 stroke-[2.2]" />
                      </div>
                      <div>
                        <div class="text-sm font-bold text-stone-950">Select Exact Pickup Minute</div>
                        <div class="text-xs text-stone-600 font-medium">Pick any available 15-minute slot</div>
                      </div>
                    </div>

                    <span class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-full text-xs font-mono font-bold bg-[#1a1a1a] text-[#e4b97a] border border-stone-800 shadow-xs">
                      {{ formattedCustomTime }}
                    </span>
                  </div>

                  <!-- 3-Column Custom Time Selector -->
                  <div class="grid grid-cols-3 gap-2.5">
                    <!-- Hour Select -->
                    <div class="space-y-1.5">
                      <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Hour</label>
                      <Select v-model="customHour">
                        <SelectTrigger class="h-11 w-full rounded-xl border border-[#e0d9cc] bg-white hover:border-[#1a1a1a] text-sm font-bold text-stone-900 focus:outline-none focus:border-[#1a1a1a]">
                          <SelectValue placeholder="Hour" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl border border-stone-200 bg-white shadow-xl max-h-56 z-50">
                          <SelectItem v-for="h in availableHours" :key="h" :value="h" class="text-sm font-bold cursor-pointer">
                            {{ h }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- Minute Select -->
                    <div class="space-y-1.5">
                      <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Minute</label>
                      <Select v-model="customMinute">
                        <SelectTrigger class="h-11 w-full rounded-xl border border-[#e0d9cc] bg-white hover:border-[#1a1a1a] text-sm font-bold text-stone-900 focus:outline-none focus:border-[#1a1a1a]">
                          <SelectValue placeholder="Minute" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl border border-stone-200 bg-white shadow-xl max-h-56 z-50">
                          <SelectItem v-for="m in availableMinutes" :key="m" :value="m" class="text-sm font-bold cursor-pointer">
                            :{{ m }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- AM/PM Segmented Switcher -->
                    <div class="space-y-1.5">
                      <label class="text-xs font-bold text-stone-800 uppercase tracking-wider">Period</label>
                      <div class="grid grid-cols-2 p-1 bg-[#f4efe6] rounded-xl h-11 border border-[#dfd6c8]">
                        <button
                          type="button"
                          :disabled="!isAmAvailable"
                          @click="isAmAvailable && (customPeriod = 'AM')"
                          class="rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                          :class="customPeriod === 'AM' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-stone-700 hover:text-stone-950'"
                        >
                          AM
                        </button>
                        <button
                          type="button"
                          :disabled="!isPmAvailable"
                          @click="isPmAvailable && (customPeriod = 'PM')"
                          class="rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                          :class="customPeriod === 'PM' ? 'bg-[#1a1a1a] text-white shadow-xs' : 'text-stone-700 hover:text-stone-950'"
                        >
                          PM
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Quick Preset Time Chips -->
                  <div class="space-y-2 pt-1">
                    <div class="text-xs font-bold text-stone-700 uppercase tracking-wider">Popular Times:</div>
                    <div class="flex flex-wrap gap-2">
                      <button
                        v-for="t in popularTimesList"
                        :key="t"
                        type="button"
                        @click="setCustomTime(t)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer border"
                        :class="formattedCustomTime === t ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]' : 'bg-white text-stone-900 border-[#e0d9cc] hover:border-stone-400'"
                      >
                        {{ t }}
                      </button>
                    </div>
                  </div>

                  <!-- Confirmation Banner -->
                  <div class="p-3 bg-emerald-50 border border-emerald-300 rounded-xl flex items-center gap-2.5 text-xs text-emerald-950 font-medium">
                    <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0 stroke-[2.2]" />
                    <div class="leading-snug">
                      Pickup set for <strong class="font-bold text-emerald-950">{{ scheduledDay === 'today' ? 'Today' : 'Tomorrow' }} at {{ formattedCustomTime }}</strong>.
                    </div>
                  </div>
                </div>

              </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 sm:p-5 border-t border-[#e0d9cc]/70 bg-white">
              <button
                type="button"
                @click="isScheduleModalOpen = false"
                class="w-full py-3.5 px-4 rounded-2xl bg-[#1a1a1a] hover:bg-stone-800 text-white font-bold text-sm shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
              >
                <span>Confirm Pickup Time</span>
                <span class="text-stone-400 text-xs">·</span>
                <span class="text-[#e4b97a] text-xs font-normal truncate max-w-[200px]">{{ effectiveFulfillmentSlotLabel }}</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </StoreLayout>
</template>
