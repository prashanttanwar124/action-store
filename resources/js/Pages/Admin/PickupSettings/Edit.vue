<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Clock, 
  Flame, 
  Calendar, 
  Zap, 
  Car, 
  Save, 
  CheckCircle2, 
  RotateCcw, 
  Plus, 
  Trash2, 
  ArrowRight, 
  Store,
  Info,
  Check
} from 'lucide-vue-next';
import { TimePicker } from '@/Components/ui/time-picker';

const props = defineProps({
  storeInfo: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  is_pickup_active: props.storeInfo.is_pickup_active !== undefined ? Boolean(props.storeInfo.is_pickup_active) : true,
  prep_time_minutes: props.storeInfo.prep_time_minutes ?? 15,
  busy_mode_extra_minutes: props.storeInfo.busy_mode_extra_minutes ?? 0,
  busy_mode_reason: props.storeInfo.busy_mode_reason || '',
  pickup_time: props.storeInfo.pickup_time || 'Ready in 15 mins',
  pickup_slot_window_label: props.storeInfo.pickup_slot_window_label || (props.storeInfo.pickup_slot_duration_minutes === 30 ? '30-min window' : (props.storeInfo.pickup_slot_duration_minutes === 120 ? '2-hour window' : '1-hour window')),
  pickup_slot_start_time: props.storeInfo.pickup_slot_start_time || '09:00',
  pickup_slot_end_time: props.storeInfo.pickup_slot_end_time || '21:00',
  pickup_slot_duration_minutes: props.storeInfo.pickup_slot_duration_minutes ?? 60,
  pickup_slots: Array.isArray(props.storeInfo.pickup_slots) && props.storeInfo.pickup_slots.length > 0 
    ? JSON.parse(JSON.stringify(props.storeInfo.pickup_slots)) 
    : (Array.isArray(props.storeInfo.available_pickup_slots) && props.storeInfo.available_pickup_slots.length > 0 
        ? JSON.parse(JSON.stringify(props.storeInfo.available_pickup_slots)) 
        : []),
  curbside_instructions: props.storeInfo.curbside_instructions || 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
});

const setRushPreset = (extraMins, reason = '') => {
  form.busy_mode_extra_minutes = extraMins;
  if (reason) form.busy_mode_reason = reason;
  else if (extraMins === 0) form.busy_mode_reason = '';
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

// Slot Management Functions
const newCustomSlotLabel = ref('');

function generateSlotsFromSettings() {
  const startParts = String(form.pickup_slot_start_time || '09:00').split(':');
  const endParts = String(form.pickup_slot_end_time || '21:00').split(':');
  const startH = parseInt(startParts[0], 10) || 9;
  const startM = parseInt(startParts[1], 10) || 0;
  const endH = parseInt(endParts[0], 10) || 21;
  const endM = parseInt(endParts[1], 10) || 0;
  const duration = parseInt(form.pickup_slot_duration_minutes || '60', 10) || 60;

  const slots = [];
  let current = startH * 60 + startM;
  const end = endH * 60 + endM;

  while (current + duration <= end) {
    const sH = Math.floor(current / 60);
    const sM = current % 60;
    const eH = Math.floor((current + duration) / 60);
    const eM = (current + duration) % 60;

    const sP = sH >= 12 ? 'PM' : 'AM';
    const eP = eH >= 12 ? 'PM' : 'AM';
    const sH12 = sH % 12 || 12;
    const eH12 = eH % 12 || 12;

    const label = `${sH12}:${String(sM).padStart(2, '0')} ${sP} – ${eH12}:${String(eM).padStart(2, '0')} ${eP}`;
    slots.push({
      id: `${String(sH).padStart(2, '0')}-${String(eH).padStart(2, '0')}`,
      startHour: sH,
      label,
      active: true,
    });
    current += duration;
  }
  form.pickup_slots = slots;
}

watch(
  [() => form.pickup_slot_start_time, () => form.pickup_slot_end_time, () => form.pickup_slot_duration_minutes],
  () => {
    generateSlotsFromSettings();
  }
);

if (!form.pickup_slots || form.pickup_slots.length === 0) {
  generateSlotsFromSettings();
}

function addCustomSlot() {
  if (!newCustomSlotLabel.value.trim()) return;
  if (!Array.isArray(form.pickup_slots)) {
    form.pickup_slots = [];
  }
  const id = `custom-${Date.now()}`;
  form.pickup_slots.push({
    id,
    startHour: 12,
    label: newCustomSlotLabel.value.trim(),
    active: true,
  });
  newCustomSlotLabel.value = '';
}

function removeSlot(index) {
  form.pickup_slots.splice(index, 1);
}

function toggleSlotActive(slot) {
  slot.active = !slot.active;
}

const submit = () => {
  form.pickup_time = computedEffectiveEta.value;
  form.put(route('admin.pickup-settings.update'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Pickup Timings, Rush Buffer & Slots — Admin Console" />

  <AdminLayout title="Pickup & Slot Settings">
    <div class="space-y-6 max-w-6xl mx-auto pb-12">

      <!-- TOP HERO BANNER -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-[#a47a3c] uppercase tracking-wider">
            <Clock class="w-4 h-4" />
            <span>Store Pickup & Scheduling Console</span>
          </div>
          <h1 class="text-2xl font-serif font-bold text-stone-900 mt-1">
            Pickup Timings, Rush Buffer & Slot Scheduling
          </h1>
          <p class="text-xs text-stone-500 mt-0.5">
            Manage live packing preparation speed, instant busy buffer, custom scheduled time slots, and curbside pickup instructions.
          </p>
        </div>

        <div class="flex items-center gap-3 self-end sm:self-auto">
          <Link
            :href="route('admin.store-info.edit')"
            class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl transition-colors flex items-center gap-1.5"
          >
            <Store class="w-3.5 h-3.5" />
            <span>Store Info</span>
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

      <!-- STATUS SUMMARY CARDS -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Card 1: Service Status -->
        <div class="p-4 rounded-2xl bg-white border border-stone-200 shadow-2xs flex items-center justify-between">
          <div>
            <div class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Pickup Service</div>
            <div class="text-sm font-bold text-stone-900 mt-0.5">
              {{ form.is_pickup_active ? 'Active & Accepting Orders' : 'Paused / Paused Orders' }}
            </div>
          </div>
          <span 
            class="w-3 h-3 rounded-full shrink-0"
            :class="form.is_pickup_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-400'"
          ></span>
        </div>

        <!-- Card 2: Live Customer ETA -->
        <div class="p-4 rounded-2xl bg-white border border-stone-200 shadow-2xs flex items-center justify-between">
          <div>
            <div class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Customer Live ETA</div>
            <div class="text-sm font-bold text-stone-900 mt-0.5 flex items-center gap-1.5">
              <Zap class="w-4 h-4 text-amber-500" />
              <span>{{ computedEffectiveEta }}</span>
            </div>
          </div>
          <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-stone-100 text-stone-700">
            {{ computedTotalPrep }}m total
          </span>
        </div>

        <!-- Card 3: Active Slots Count -->
        <div class="p-4 rounded-2xl bg-white border border-stone-200 shadow-2xs flex items-center justify-between">
          <div>
            <div class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Scheduled Slots</div>
            <div class="text-sm font-bold text-stone-900 mt-0.5">
              {{ form.pickup_slots?.filter(s => s.active !== false).length || 0 }} Active Windows
            </div>
          </div>
          <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200">
            {{ form.pickup_slot_window_label || (form.pickup_slot_duration_minutes === 30 ? '30-min window' : (form.pickup_slot_duration_minutes === 120 ? '2-hour window' : '1-hour window')) }}
          </span>
        </div>
      </div>

      <!-- MAIN FORM SECTIONS -->
      <form @submit.prevent="submit" class="space-y-6">

        <!-- SECTION 1: MASTER STATUS & PREPARATION SPEED -->
        <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-5">
          <div class="flex items-center justify-between border-b border-stone-100 pb-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2">
              <Zap class="w-4 h-4 text-[#a47a3c]" />
              <span>1. Express Preparation Speed & Service Status</span>
            </h2>
            <span 
              class="text-[10px] font-bold px-2 py-0.5 rounded-full"
              :class="form.is_pickup_active ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
            >
              {{ form.is_pickup_active ? 'Pickup Online' : 'Pickup Paused' }}
            </span>
          </div>

          <!-- Service Master Toggle -->
          <div class="flex items-center justify-between p-4 bg-stone-50 rounded-xl border border-stone-200">
            <div>
              <div class="text-xs font-bold text-stone-900">Store Pickup Service Status</div>
              <div class="text-[11px] text-stone-500 font-normal mt-0.5">
                When paused, customers will not see Store Pickup as an available fulfillment option at checkout.
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input 
                type="checkbox" 
                v-model="form.is_pickup_active" 
                class="sr-only peer"
              />
              <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1a1a1a]"></div>
            </label>
          </div>

          <!-- Base Prep Time -->
          <div class="space-y-2">
            <label class="block text-xs font-semibold text-stone-700">
              Standard Base Preparation Time (Normal Hours)
            </label>
            <div class="flex items-center gap-3">
              <div class="relative w-40">
                <input
                  v-model.number="form.prep_time_minutes"
                  type="number"
                  min="5"
                  max="180"
                  step="5"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs font-bold text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:outline-none pr-12"
                />
                <span class="absolute right-3.5 top-2.5 text-xs text-stone-500 font-mono">mins</span>
              </div>

              <!-- Quick chips for base prep -->
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  v-for="mins in [10, 15, 20, 30, 45]"
                  :key="mins"
                  type="button"
                  @click="form.prep_time_minutes = mins"
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer border"
                  :class="form.prep_time_minutes === mins ? 'bg-[#1a1a1a] text-white border-black' : 'bg-stone-50 text-stone-700 border-stone-200 hover:bg-stone-100'"
                >
                  {{ mins }}m
                </button>
              </div>
            </div>
            <p class="text-[11px] text-stone-400">
              This is the normal speed displayed to customers on banners and headers when store is running normally.
            </p>
          </div>
        </div>

        <!-- SECTION 2: LIVE STORE RUSH BUFFER (+MINUTES) -->
        <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-stone-100 pb-3">
            <div>
              <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2">
                <Flame class="w-4 h-4 text-amber-600" />
                <span>2. Store Rush Buffer (+Extra Minutes When Busy)</span>
              </h2>
              <p class="text-xs text-stone-500 mt-0.5">
                When the store gets crowded, add buffer minutes here. Customer order ETA updates across the storefront in real-time.
              </p>
            </div>
            <span 
              class="text-[10px] font-bold px-2 py-0.5 rounded-full"
              :class="form.busy_mode_extra_minutes > 0 ? 'bg-amber-100 text-amber-800 border border-amber-300 animate-pulse' : 'bg-stone-100 text-stone-600'"
            >
              {{ form.busy_mode_extra_minutes > 0 ? `+${form.busy_mode_extra_minutes}m Rush Active` : 'Normal Speed' }}
            </span>
          </div>

          <!-- Rush Buffer Presets -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <button
              type="button"
              @click="setRushPreset(0)"
              class="p-3.5 rounded-xl border text-center transition-all cursor-pointer"
              :class="form.busy_mode_extra_minutes === 0 
                ? 'bg-emerald-600 text-white font-bold border-emerald-600 shadow-xs' 
                : 'bg-stone-50 text-stone-700 border-stone-200 hover:border-stone-300'"
            >
              <div class="text-[10px] uppercase font-mono tracking-wider opacity-80">Normal Speed</div>
              <div class="text-sm font-bold mt-0.5">+0 mins</div>
              <div class="text-[10px] opacity-75 mt-0.5">{{ form.prep_time_minutes }}m total</div>
            </button>

            <button
              type="button"
              @click="setRushPreset(15, 'Store Busy')"
              class="p-3.5 rounded-xl border text-center transition-all cursor-pointer"
              :class="form.busy_mode_extra_minutes === 15 
                ? 'bg-amber-600 text-white font-bold border-amber-600 shadow-xs' 
                : 'bg-stone-50 text-stone-700 border-stone-200 hover:border-stone-300'"
            >
              <div class="text-[10px] uppercase font-mono tracking-wider opacity-80">Busy Mode</div>
              <div class="text-sm font-bold mt-0.5">+15 mins</div>
              <div class="text-[10px] opacity-75 mt-0.5">{{ Number(form.prep_time_minutes) + 15 }}m total</div>
            </button>

            <button
              type="button"
              @click="setRushPreset(30, 'Peak Rush')"
              class="p-3.5 rounded-xl border text-center transition-all cursor-pointer"
              :class="form.busy_mode_extra_minutes === 30 
                ? 'bg-orange-600 text-white font-bold border-orange-600 shadow-xs' 
                : 'bg-stone-50 text-stone-700 border-stone-200 hover:border-stone-300'"
            >
              <div class="text-[10px] uppercase font-mono tracking-wider opacity-80">Peak Rush</div>
              <div class="text-sm font-bold mt-0.5">+30 mins</div>
              <div class="text-[10px] opacity-75 mt-0.5">{{ Number(form.prep_time_minutes) + 30 }}m total</div>
            </button>

            <button
              type="button"
              @click="setRushPreset(45, 'Heavy Rush')"
              class="p-3.5 rounded-xl border text-center transition-all cursor-pointer"
              :class="form.busy_mode_extra_minutes === 45 
                ? 'bg-rose-600 text-white font-bold border-rose-600 shadow-xs' 
                : 'bg-stone-50 text-stone-700 border-stone-200 hover:border-stone-300'"
            >
              <div class="text-[10px] uppercase font-mono tracking-wider opacity-80">Heavy Rush</div>
              <div class="text-sm font-bold mt-0.5">+45 mins</div>
              <div class="text-[10px] opacity-75 mt-0.5">{{ Number(form.prep_time_minutes) + 45 }}m total</div>
            </button>
          </div>

          <!-- Reason & Preview Box -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Rush Reason / Customer Note (Optional)
              </label>
              <input
                v-model="form.busy_mode_reason"
                type="text"
                placeholder="e.g. High store traffic, Diwali weekend"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:outline-none"
              />
            </div>

            <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-200 flex items-center justify-between">
              <div>
                <div class="text-[10px] font-mono text-stone-500 uppercase tracking-wider">Live Customer Facing ETA</div>
                <div class="text-sm font-bold text-stone-900 mt-0.5 flex items-center gap-1.5">
                  <Zap class="w-4 h-4 text-amber-500" />
                  <span>{{ computedEffectiveEta }}</span>
                </div>
              </div>
              <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-white border border-stone-200 text-stone-700">
                {{ computedTotalPrep }} mins total
              </span>
            </div>
          </div>
        </div>

        <!-- SECTION 3: SCHEDULED TIME SLOTS & BOOKING WINDOWS -->
        <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-5">
          <div class="flex items-center justify-between border-b border-stone-100 pb-3">
            <div>
              <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2">
                <Calendar class="w-4 h-4 text-[#a47a3c]" />
                <span>3. Scheduled Time Slots & Booking Windows</span>
              </h2>
              <p class="text-xs text-stone-500 mt-0.5">
                Configure what customers see when selecting <strong>"Schedule for Later"</strong> at checkout.
              </p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
              Checkout Slot Grid
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Window Title Input -->
            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Slot Window Title / Label
              </label>
              <input
                v-model="form.pickup_slot_window_label"
                type="text"
                placeholder="e.g. 1-hour window, 30-min slot, express slot"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
              />
              <p class="text-[11px] text-stone-400 mt-1">
                Customer sees: <em>"Select {{ form.pickup_slot_window_label || '1-hour window' }} for Today / Tomorrow"</em>
              </p>
            </div>

            <!-- Slot Duration -->
            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Slot Interval Duration
              </label>
              <div class="grid grid-cols-3 gap-2">
                <button
                  v-for="dur in [
                    { val: 30, label: '30 Mins', defaultTitle: '30-min window' },
                    { val: 60, label: '1 Hour', defaultTitle: '1-hour window' },
                    { val: 120, label: '2 Hours', defaultTitle: '2-hour window' },
                  ]"
                  :key="dur.val"
                  type="button"
                  @click="
                    form.pickup_slot_duration_minutes = dur.val;
                    if (!form.pickup_slot_window_label || ['1-hour window', '30-min window', '2-hour window', '1-hour slot', '30-min slot', '2-hour slot'].includes(form.pickup_slot_window_label.trim().toLowerCase())) {
                      form.pickup_slot_window_label = dur.defaultTitle;
                    }
                    generateSlotsFromSettings();
                  "
                  class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all cursor-pointer text-center"
                  :class="form.pickup_slot_duration_minutes === dur.val 
                    ? 'bg-[#1a1a1a] text-white border-black shadow-xs' 
                    : 'bg-stone-50 text-stone-700 border-stone-200 hover:bg-stone-100'"
                >
                  {{ dur.label }}
                </button>
              </div>
            </div>

            <!-- Start Time -->
            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                First Slot Start Hour
              </label>
              <TimePicker
                v-model="form.pickup_slot_start_time"
                @change="generateSlotsFromSettings"
              />
            </div>

            <!-- End Time -->
            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Last Slot End Hour
              </label>
              <TimePicker
                v-model="form.pickup_slot_end_time"
                @change="generateSlotsFromSettings"
              />
            </div>
          </div>

          <!-- Active Slots Grid & Controls -->
          <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                  <span>Active Pickup Slots ({{ form.pickup_slots?.filter(s => s.active !== false).length || 0 }} of {{ form.pickup_slots?.length || 0 }} active)</span>
                </div>
                <div class="text-[11px] text-stone-500 mt-0.5">
                  Click any slot to pause/resume it. Inactive slots are hidden from customers at checkout.
                </div>
              </div>

              <button
                type="button"
                @click="generateSlotsFromSettings"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-[#a47a3c] bg-white border border-stone-200 hover:bg-stone-100 shadow-2xs cursor-pointer self-start sm:self-auto"
              >
                <RotateCcw class="w-3.5 h-3.5" />
                <span>Reset All Slots</span>
              </button>
            </div>

            <!-- Slots Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 pt-1">
              <div
                v-for="(slot, idx) in form.pickup_slots"
                :key="slot.id || idx"
                class="p-2.5 bg-white rounded-xl border transition-all flex items-center justify-between gap-1 shadow-2xs"
                :class="slot.active !== false ? 'border-stone-200 text-stone-900' : 'border-stone-200/60 bg-stone-100 text-stone-400 opacity-60'"
              >
                <button
                  type="button"
                  @click="toggleSlotActive(slot)"
                  class="text-left flex-1 cursor-pointer truncate"
                >
                  <div class="text-[11px] font-semibold truncate">{{ slot.label }}</div>
                  <div class="text-[9px]" :class="slot.active !== false ? 'text-emerald-600 font-bold' : 'text-stone-400'">
                    {{ slot.active !== false ? '● Active' : '○ Paused' }}
                  </div>
                </button>

                <button
                  type="button"
                  @click="removeSlot(idx)"
                  class="p-1 text-stone-400 hover:text-rose-500 rounded-md transition-colors cursor-pointer shrink-0"
                  title="Remove slot"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Add Custom Slot Input -->
            <div class="flex items-center gap-2 pt-2 border-t border-stone-200/60">
              <input
                v-model="newCustomSlotLabel"
                type="text"
                placeholder="Add custom slot (e.g. 5:30 PM – 6:30 PM, Evening Rush Special)"
                class="flex-1 px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs text-stone-900 focus:outline-none focus:border-[#1a1a1a]"
                @keydown.enter.prevent="addCustomSlot"
              />
              <button
                type="button"
                @click="addCustomSlot"
                class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl flex items-center gap-1.5 transition-colors cursor-pointer shrink-0"
              >
                <Plus class="w-3.5 h-3.5" />
                <span>Add Custom Slot</span>
              </button>
            </div>
          </div>
        </div>

        <!-- SECTION 4: CURBSIDE BAY & COUNTER INSTRUCTIONS -->
        <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-stone-100 pb-3">
            <div>
              <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2">
                <Car class="w-4 h-4 text-[#a47a3c]" />
                <span>4. Curbside Bay & Counter Instructions</span>
              </h2>
              <p class="text-xs text-stone-500 mt-0.5">
                Instructions shown on customer checkout summary and live order tracking page.
              </p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1">
              Curbside & Counter Pickup Instructions
            </label>
            <textarea
              v-model="form.curbside_instructions"
              rows="3"
              placeholder="e.g. Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS."
              class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none"
            ></textarea>
            <p v-if="form.errors.curbside_instructions" class="text-xs text-rose-500 mt-1">{{ form.errors.curbside_instructions }}</p>
          </div>
        </div>

        <!-- SAVE BUTTON BAR -->
        <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-stone-200 shadow-xs">
          <div class="flex items-center gap-2 text-xs text-stone-500">
            <Info class="w-4 h-4 text-[#a47a3c]" />
            <span>Changes reflect immediately across all customer checkout screens and live order tracking.</span>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center gap-2 px-8 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50"
          >
            <Save class="w-4 h-4" />
            <span>{{ form.processing ? 'Saving...' : 'Save Pickup & Slot Settings' }}</span>
          </button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
