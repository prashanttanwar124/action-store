<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Store, 
  MapPin, 
  Clock, 
  Phone, 
  Mail, 
  Sparkles, 
  Save, 
  Truck, 
  Calendar, 
  Check, 
  ArrowRight,
  Zap,
  Car
} from 'lucide-vue-next';

const props = defineProps({
  storeInfo: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  name: props.storeInfo.name || 'Masala Mart — Main St.',
  tagline: props.storeInfo.tagline || 'Authentic Indian Groceries & Fresh Click-and-Collect',
  hindi_tagline: props.storeInfo.hindi_tagline || 'किराना',
  address: props.storeInfo.address || '214 Main St.',
  city: props.storeInfo.city || 'Edison',
  state: props.storeInfo.state || 'NJ',
  zip: props.storeInfo.zip || '08817',
  phone: props.storeInfo.phone || '+1 (555) 345-6789',
  email: props.storeInfo.email || 'support@masalamart.com',
  opening_hours: props.storeInfo.opening_hours || 'Daily 9:00 AM – 9:00 PM',
  prep_time_minutes: props.storeInfo.prep_time_minutes ?? 15,
  busy_mode_extra_minutes: props.storeInfo.busy_mode_extra_minutes ?? 0,
  busy_mode_reason: props.storeInfo.busy_mode_reason || '',
  pickup_time: props.storeInfo.pickup_time || 'Ready in 15 mins',
  pickup_slot_window_label: props.storeInfo.pickup_slot_window_label || (props.storeInfo.pickup_slot_duration_minutes === 30 ? '30-min window' : (props.storeInfo.pickup_slot_duration_minutes === 120 ? '2-hour window' : '1-hour window')),
  pickup_slot_start_time: props.storeInfo.pickup_slot_start_time || '09:00',
  pickup_slot_end_time: props.storeInfo.pickup_slot_end_time || '21:00',
  pickup_slot_duration_minutes: props.storeInfo.pickup_slot_duration_minutes ?? 60,
  pickup_slots: Array.isArray(props.storeInfo.pickup_slots) 
    ? props.storeInfo.pickup_slots 
    : [],
  curbside_instructions: props.storeInfo.curbside_instructions || '',
  announcement: props.storeInfo.announcement || 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
  maps_url: props.storeInfo.maps_url || 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
  is_pickup_active: props.storeInfo.is_pickup_active !== undefined ? Boolean(props.storeInfo.is_pickup_active) : true,
  is_delivery_active: props.storeInfo.is_delivery_active !== undefined ? Boolean(props.storeInfo.is_delivery_active) : false,
  delivery_days: Array.isArray(props.storeInfo.delivery_days) && props.storeInfo.delivery_days.length > 0 
    ? [...props.storeInfo.delivery_days] 
    : ['friday', 'saturday', 'sunday'],
  delivery_fee: props.storeInfo.delivery_fee ?? 4.99,
  free_delivery_threshold: props.storeInfo.free_delivery_threshold ?? 50.00,
  delivery_estimated_time: props.storeInfo.delivery_estimated_time || 'Same Day 5:00 PM – 8:00 PM',
});

const weekDays = [
  { id: 'monday', label: 'Mon', full: 'Monday' },
  { id: 'tuesday', label: 'Tue', full: 'Tuesday' },
  { id: 'wednesday', label: 'Wed', full: 'Wednesday' },
  { id: 'thursday', label: 'Thu', full: 'Thursday' },
  { id: 'friday', label: 'Fri', full: 'Friday' },
  { id: 'saturday', label: 'Sat', full: 'Saturday' },
  { id: 'sunday', label: 'Sun', full: 'Sunday' },
];

const toggleDeliveryDay = (dayId) => {
  const index = form.delivery_days.indexOf(dayId);
  if (index > -1) {
    form.delivery_days.splice(index, 1);
  } else {
    form.delivery_days.push(dayId);
  }
};

const computedTotalPrep = computed(() => {
  return Number(form.prep_time_minutes || 15) + Number(form.busy_mode_extra_minutes || 0);
});

const computedEffectiveEta = computed(() => {
  if (!form.is_pickup_active) {
    return 'Pickup Currently Paused';
  }
  const total = computedTotalPrep.value;
  if (form.busy_mode_extra_minutes > 0) {
    const reason = form.busy_mode_reason ? ` (${form.busy_mode_reason})` : ' (Store Rush)';
    return `Ready in ${total} mins${reason}`;
  }
  if (total >= 60) {
    const hours = Math.round((total / 60) * 10) / 10;
    return hours === 1 ? 'Ready in 1 hr' : `Ready in ${hours} hrs`;
  }
  return `Ready in ${total} mins`;
});

const submit = () => {
  form.put(route('admin.store-info.update'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Store Information & Delivery — Admin Console" />

  <AdminLayout title="Store Information & General Settings">
    <div class="space-y-6 max-w-6xl mx-auto pb-12">

      <!-- Header Section -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-[#a47a3c] uppercase tracking-wider">
            <Store class="w-4 h-4" />
            <span>Store Profile & Operations</span>
          </div>
          <h1 class="text-2xl font-serif font-bold text-stone-900 mt-1">
            Store Identity, Location & Delivery Schedule
          </h1>
          <p class="text-xs text-stone-500 mt-0.5">
            Manage public store branding, physical address, opening hours, weekly home delivery schedule, and public announcements.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('admin.pickup-settings.edit')"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-[#7a5620] border border-[#e0d9cc] text-xs font-semibold rounded-xl transition-all cursor-pointer"
          >
            <Clock class="w-4 h-4 text-[#a47a3c]" />
            <span>Pickup & Slot Settings</span>
            <ArrowRight class="w-3.5 h-3.5 text-[#a47a3c]" />
          </Link>

          <button
            type="button"
            @click="submit"
            :disabled="form.processing"
            class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl shadow-sm transition-all cursor-pointer disabled:opacity-50"
          >
            <Save class="w-4 h-4" />
            <span>{{ form.processing ? 'Saving Changes...' : 'Save Settings' }}</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Form Left Column: Settings Fields (8 cols) -->
        <form @submit.prevent="submit" class="lg:col-span-8 space-y-6">

          <!-- 1. Store Identity -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <Store class="w-4 h-4 text-[#a47a3c]" />
              <span>1. Store Identity & Branding</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Store Public Name *
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="e.g. Masala Mart — Main St."
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Regional / Devanagari Tag
                </label>
                <input
                  v-model="form.hindi_tagline"
                  type="text"
                  placeholder="e.g. किराना स्टोर"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.hindi_tagline" class="text-xs text-rose-500 mt-1">{{ form.errors.hindi_tagline }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Store Subtitle / Tagline
              </label>
              <input
                v-model="form.tagline"
                type="text"
                placeholder="e.g. Authentic Indian Groceries & Fresh Click-and-Collect"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
              />
              <p v-if="form.errors.tagline" class="text-xs text-rose-500 mt-1">{{ form.errors.tagline }}</p>
            </div>
          </div>

          <!-- 2. Store Location & Operating Hours -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <MapPin class="w-4 h-4 text-[#a47a3c]" />
              <span>2. Physical Store Location & Operating Hours</span>
            </h2>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Street Address *
              </label>
              <input
                v-model="form.address"
                type="text"
                required
                placeholder="e.g. 214 Main St."
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
              />
              <p v-if="form.errors.address" class="text-xs text-rose-500 mt-1">{{ form.errors.address }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  City *
                </label>
                <input
                  v-model="form.city"
                  type="text"
                  required
                  placeholder="e.g. Edison"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.city" class="text-xs text-rose-500 mt-1">{{ form.errors.city }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  State / Province *
                </label>
                <input
                  v-model="form.state"
                  type="text"
                  required
                  placeholder="e.g. NJ"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.state" class="text-xs text-rose-500 mt-1">{{ form.errors.state }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  ZIP / Postal Code *
                </label>
                <input
                  v-model="form.zip"
                  type="text"
                  required
                  placeholder="e.g. 08817"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.zip" class="text-xs text-rose-500 mt-1">{{ form.errors.zip }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Opening & Closing Hours *
                </label>
                <div class="relative">
                  <input
                    v-model="form.opening_hours"
                    type="text"
                    required
                    placeholder="e.g. Daily 9:00 AM – 9:00 PM"
                    class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                  />
                </div>
                <p v-if="form.errors.opening_hours" class="text-xs text-rose-500 mt-1">{{ form.errors.opening_hours }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Google Maps Directions URL
                </label>
                <input
                  v-model="form.maps_url"
                  type="url"
                  placeholder="https://maps.google.com/?q=..."
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.maps_url" class="text-xs text-rose-500 mt-1">{{ form.errors.maps_url }}</p>
              </div>
            </div>
          </div>

          <!-- DEDICATED PICKUP & SLOTS LINK CALLOUT BANNER -->
          <div class="p-6 bg-[#1a1a1a] text-white rounded-2xl shadow-sm border border-stone-800 flex flex-col sm:flex-row sm:items-center justify-between gap-5 relative overflow-hidden">
            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-[#a47a3c]/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="space-y-1.5 relative z-10">
              <div class="flex items-center gap-2">
                <span class="p-1.5 bg-[#a47a3c]/20 text-[#e4b97a] rounded-lg">
                  <Clock class="w-4 h-4" />
                </span>
                <h3 class="text-sm font-bold text-white tracking-wide">Dedicated Pickup & Slot Settings</h3>
                <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-[#a47a3c] text-white">
                  Separate Page
                </span>
              </div>
              <p class="text-xs text-stone-300 max-w-xl leading-relaxed">
                Base prep timings, rush buffer presets (+15m / +30m), checkout slot window durations (1-hour / 30-min), and custom slot generator are managed on the dedicated Pickup page.
              </p>
              <div class="flex items-center gap-3 pt-1 text-[11px] text-stone-400">
                <span class="flex items-center gap-1.5 text-stone-300">
                  <span class="w-2 h-2 rounded-full" :class="form.is_pickup_active ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                  <span>Status: {{ form.is_pickup_active ? 'Active' : 'Paused' }}</span>
                </span>
                <span>•</span>
                <span class="text-amber-300 font-medium">ETA: {{ computedEffectiveEta }}</span>
              </div>
            </div>

            <Link
              :href="route('admin.pickup-settings.edit')"
              class="inline-flex items-center gap-2 px-5 py-3 bg-[#a47a3c] hover:bg-[#8e6932] text-white text-xs font-semibold rounded-xl transition-all shrink-0 shadow-sm cursor-pointer relative z-10"
            >
              <span>Manage Pickup & Slots</span>
              <ArrowRight class="w-4 h-4" />
            </Link>
          </div>

          <!-- 3. Home Delivery Operations & Weekly Schedule -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
              <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2">
                <Truck class="w-4 h-4 text-[#a47a3c]" />
                <span>3. Home Delivery Operations & Weekly Days</span>
              </h2>
              <span 
                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                :class="form.is_delivery_active ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-stone-100 text-stone-600'"
              >
                {{ form.is_delivery_active ? 'Delivery Enabled' : 'Delivery Off' }}
              </span>
            </div>

            <!-- Delivery Master Toggle -->
            <div class="flex items-center justify-between p-3.5 bg-stone-50 rounded-xl border border-stone-200">
              <div>
                <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                  <span>Enable Home Delivery Feature</span>
                  <span class="text-[10px] font-normal text-stone-500">(Master Switch)</span>
                </div>
                <div class="text-[11px] text-stone-500 font-normal mt-0.5">
                  Turn this ON when drivers are available. Turn OFF if you only want Store Pickup.
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input 
                  type="checkbox" 
                  v-model="form.is_delivery_active" 
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1a1a1a]"></div>
              </label>
            </div>

            <!-- Weekly Days Selector -->
            <div class="space-y-2" :class="form.is_delivery_active ? 'opacity-100' : 'opacity-50 pointer-events-none'">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold text-stone-700">
                  Weekly Delivery Days (Select days when driver is active)
                </label>
                <span class="text-[10px] text-stone-400 font-mono">
                  {{ form.delivery_days.length }} days selected
                </span>
              </div>

              <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                <button
                  v-for="day in weekDays"
                  :key="day.id"
                  type="button"
                  @click="toggleDeliveryDay(day.id)"
                  class="p-2.5 rounded-xl border text-center transition-all cursor-pointer flex flex-col items-center justify-center min-h-[54px]"
                  :class="form.delivery_days.includes(day.id)
                    ? 'bg-[#1a1a1a] text-white border-black shadow-xs font-bold'
                    : 'bg-stone-50 text-stone-600 border-stone-200 hover:border-stone-300'"
                >
                  <span class="text-xs">{{ day.label }}</span>
                  <Check v-if="form.delivery_days.includes(day.id)" class="w-3 h-3 text-[#e4b97a] mt-0.5" />
                  <span v-else class="text-[9px] text-stone-400 mt-0.5 font-normal">Off</span>
                </button>
              </div>
              <p class="text-[11px] text-stone-500 mt-1">
                Customers checking out on inactive days will see when the next delivery run takes place.
              </p>
            </div>

            <!-- Delivery Pricing & Window -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" :class="form.is_delivery_active ? 'opacity-100' : 'opacity-50 pointer-events-none'">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Delivery Fee ($)
                </label>
                <input
                  v-model.number="form.delivery_fee"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="4.99"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Free Delivery Over ($)
                </label>
                <input
                  v-model.number="form.free_delivery_threshold"
                  type="number"
                  step="1"
                  min="0"
                  placeholder="50.00"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Delivery Time Window Note
                </label>
                <input
                  v-model="form.delivery_estimated_time"
                  type="text"
                  placeholder="e.g. Same Day 5:00 PM – 8:00 PM"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- 4. Contact & Announcements -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <Sparkles class="w-4 h-4 text-[#a47a3c]" />
              <span>4. Contact Information & Announcement Banner</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Customer Support Phone / WhatsApp *
                </label>
                <input
                  v-model="form.phone"
                  type="text"
                  required
                  placeholder="+1 (555) 345-6789"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.phone" class="text-xs text-rose-500 mt-1">{{ form.errors.phone }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Store Email *
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="support@masalamart.com"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
                />
                <p v-if="form.errors.email" class="text-xs text-rose-500 mt-1">{{ form.errors.email }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Top Announcement Bar Message
              </label>
              <input
                v-model="form.announcement"
                type="text"
                placeholder="e.g. Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
              />
              <p v-if="form.errors.announcement" class="text-xs text-rose-500 mt-1">{{ form.errors.announcement }}</p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="px-8 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50"
            >
              {{ form.processing ? 'Saving...' : 'Save Store Information' }}
            </button>
          </div>

        </form>

        <!-- Right Column: Live Storefront Header Preview (4 cols) -->
        <div class="lg:col-span-4 space-y-5 sticky top-24">
          <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
              <span class="text-xs font-bold uppercase tracking-wider text-stone-900 flex items-center gap-1.5">
                <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
                <span>Live Customer View Preview</span>
              </span>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                Live Preview
              </span>
            </div>

            <!-- Rendered Storefront Badge Preview -->
            <div class="p-4 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc] space-y-3">
              <div class="flex items-center gap-2">
                <span class="font-serif font-semibold text-base text-[#1d1d1f]">
                  {{ form.name.split('—')[0].trim() || 'Masala Mart' }}
                </span>
                <span v-if="form.hindi_tagline" class="font-devanagari text-[10px] text-[#7a5620] bg-[#f5eee2] px-1.5 py-0.5 rounded border border-[#e0d9cc]">
                  {{ form.hindi_tagline }}
                </span>
              </div>

              <!-- Pickup Pill with Link -->
              <Link 
                :href="route('admin.pickup-settings.edit')"
                class="flex items-center gap-2.5 px-3 py-2 border border-[#e0d9cc] bg-white rounded-xl shadow-2xs hover:border-[#a47a3c] transition-colors group cursor-pointer"
              >
                <div class="w-6 h-6 rounded-lg bg-[#f5eee2] group-hover:bg-[#a47a3c] group-hover:text-white flex items-center justify-center text-[#a47a3c] transition-colors shrink-0">
                  <MapPin class="w-3.5 h-3.5" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <span class="text-[9px] text-[#7a5620] font-bold uppercase tracking-wider">STORE PICKUP</span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="form.busy_mode_extra_minutes > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                    <span class="text-[9px] font-medium" :class="form.busy_mode_extra_minutes > 0 ? 'text-amber-700 font-bold' : 'text-stone-500'">
                      {{ computedEffectiveEta }}
                    </span>
                  </div>
                  <div class="font-bold text-xs text-[#1d1d1f] truncate mt-0.5">
                    {{ form.address }} · {{ form.city }}
                  </div>
                </div>
                <ArrowRight class="w-3.5 h-3.5 text-stone-300 group-hover:text-[#a47a3c] transition-colors shrink-0" />
              </Link>

              <!-- Home Delivery Preview Pill -->
              <div 
                v-if="form.is_delivery_active"
                class="p-2.5 rounded-xl border border-stone-200 bg-white text-xs space-y-1"
              >
                <div class="flex items-center justify-between text-[11px] font-semibold text-[#1d1d1f]">
                  <span class="flex items-center gap-1.5">
                    <Truck class="w-3.5 h-3.5 text-[#a47a3c]" />
                    <span>Home Delivery</span>
                  </span>
                  <span class="text-[10px] font-mono font-bold text-emerald-700">
                    {{ form.delivery_fee > 0 ? `$${form.delivery_fee.toFixed(2)}` : 'Free' }}
                  </span>
                </div>
                <div class="text-[10px] text-stone-500 flex items-center gap-1">
                  <Calendar class="w-3 h-3 text-stone-400" />
                  <span>Runs on: {{ form.delivery_days.map(d => d.slice(0,3).toUpperCase()).join(', ') || 'No days selected' }}</span>
                </div>
              </div>

              <!-- Extra Info Snippet -->
              <div class="text-[11px] text-stone-500 space-y-1 pt-1 border-t border-stone-200/60">
                <div class="flex items-center gap-1.5">
                  <Clock class="w-3 h-3 text-[#a47a3c]" />
                  <span>{{ form.opening_hours }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <Phone class="w-3 h-3 text-[#a47a3c]" />
                  <span>{{ form.phone }}</span>
                </div>
              </div>
            </div>

            <!-- Quick Jump Card to Pickup Settings -->
            <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 space-y-2">
              <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                <Clock class="w-4 h-4 text-[#a47a3c]" />
                <span>Pickup Prep & Time Windows</span>
              </div>
              <p class="text-[11px] text-stone-500 leading-snug">
                Need to add rush minutes (+15m/+30m) or activate new pickup time slots?
              </p>
              <Link
                :href="route('admin.pickup-settings.edit')"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#a47a3c] hover:text-[#7a5620] transition-colors pt-1"
              >
                <span>Go to Pickup Settings</span>
                <ArrowRight class="w-3 h-3" />
              </Link>
            </div>

          </div>
        </div>

      </div>

    </div>
  </AdminLayout>
</template>
