<script setup>
import { ref, computed, watch } from 'vue';
import { Popover, PopoverTrigger, PopoverContent } from '@/Components/ui/popover';
import { Clock, Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
  modelValue: {
    type: String,
    default: '09:00',
  },
  placeholder: {
    type: String,
    default: 'Select time...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  class: {
    type: null,
    default: '',
  },
});

const emits = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);

// Internal state
const selectedHour12 = ref(9);
const selectedMinute = ref(0);
const selectedPeriod = ref('AM');

// Parse incoming 24-hour string (e.g. "09:00" or "21:30")
function parseTime(val) {
  if (!val || typeof val !== 'string' || !val.includes(':')) {
    return;
  }
  const [hStr, mStr] = val.split(':');
  const h24 = parseInt(hStr, 10);
  const min = parseInt(mStr, 10);

  if (isNaN(h24) || isNaN(min)) return;

  selectedPeriod.value = h24 >= 12 ? 'PM' : 'AM';
  selectedHour12.value = h24 % 12 || 12;
  selectedMinute.value = min;
}

watch(
  () => props.modelValue,
  (newVal) => {
    parseTime(newVal);
  },
  { immediate: true },
);

// Format for input trigger display (e.g. "09:00 AM")
const formattedDisplay = computed(() => {
  if (!props.modelValue) return props.placeholder;
  const h = String(selectedHour12.value).padStart(2, '0');
  const m = String(selectedMinute.value).padStart(2, '0');
  return `${h}:${m} ${selectedPeriod.value}`;
});

// Update parent with HH:mm (24-hour)
function emitTimeChange() {
  let h24 = selectedHour12.value;
  if (selectedPeriod.value === 'PM') {
    h24 = h24 === 12 ? 12 : h24 + 12;
  } else {
    h24 = h24 === 12 ? 0 : h24;
  }

  const time24 = `${String(h24).padStart(2, '0')}:${String(selectedMinute.value).padStart(2, '0')}`;
  emits('update:modelValue', time24);
  emits('change', time24);
}

function selectHour(hour) {
  selectedHour12.value = hour;
  emitTimeChange();
}

function selectMinute(min) {
  selectedMinute.value = min;
  emitTimeChange();
}

function selectPeriod(period) {
  selectedPeriod.value = period;
  emitTimeChange();
}

// Quick common store hours presets
const quickPresets = [
  { label: '08:00 AM', time: '08:00' },
  { label: '09:00 AM', time: '09:00' },
  { label: '10:00 AM', time: '10:00' },
  { label: '12:00 PM', time: '12:00' },
  { label: '06:00 PM', time: '18:00' },
  { label: '08:00 PM', time: '20:00' },
  { label: '09:00 PM', time: '21:00' },
  { label: '10:00 PM', time: '22:00' },
];

function applyPreset(time24) {
  parseTime(time24);
  emitTimeChange();
}

const hours = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const minutes = [0, 15, 30, 45];
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'w-full h-9 flex items-center justify-between px-3 py-1.5 bg-card hover:bg-zinc-50 border border-input rounded-lg text-xs font-medium text-foreground shadow-2xs transition-colors cursor-pointer focus:bg-white focus:border-primary focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed text-left',
            isOpen && 'border-primary bg-white',
            props.class
          )
        "
      >
        <div class="flex items-center gap-2">
          <Clock class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
          <span class="tracking-wide font-semibold">{{ formattedDisplay }}</span>
        </div>
        <span class="text-[10px] font-mono text-zinc-400 uppercase font-medium">Edit</span>
      </button>
    </PopoverTrigger>

    <PopoverContent class="w-80 p-4 rounded-lg bg-white border border-zinc-200 shadow-xl z-50">
      <!-- Header Digital Clock Display & AM/PM Selector -->
      <div class="flex items-center justify-between border-b border-zinc-100 pb-3 mb-3">
        <div>
          <div class="text-[10px] font-mono uppercase tracking-wider text-zinc-400 font-semibold">
            Selected Time
          </div>
          <div class="text-lg font-mono font-bold text-zinc-900 tracking-wide mt-0.5">
            {{ String(selectedHour12).padStart(2, '0') }}:{{ String(selectedMinute).padStart(2, '0') }}
            <span class="text-xs font-sans text-zinc-600 ml-1">{{ selectedPeriod }}</span>
          </div>
        </div>

        <!-- AM/PM Toggle Pill -->
        <div class="flex items-center p-1 bg-zinc-100 rounded-md border border-zinc-200/80">
          <button
            type="button"
            @click="selectPeriod('AM')"
            class="px-2.5 py-1 text-xs font-semibold rounded transition-colors cursor-pointer"
            :class="selectedPeriod === 'AM' ? 'bg-zinc-900 text-white shadow-2xs' : 'text-zinc-600 hover:text-zinc-900'"
          >
            AM
          </button>
          <button
            type="button"
            @click="selectPeriod('PM')"
            class="px-2.5 py-1 text-xs font-semibold rounded transition-colors cursor-pointer"
            :class="selectedPeriod === 'PM' ? 'bg-zinc-900 text-white shadow-2xs' : 'text-zinc-600 hover:text-zinc-900'"
          >
            PM
          </button>
        </div>
      </div>

      <!-- Hours & Minutes Grid Selection -->
      <div class="grid grid-cols-2 gap-3">
        <!-- Hours Column -->
        <div class="space-y-1.5">
          <div class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
            Hour
          </div>
          <div class="grid grid-cols-3 gap-1 max-h-36 overflow-y-auto pr-0.5">
            <button
              v-for="h in hours"
              :key="h"
              type="button"
              @click="selectHour(h)"
              class="py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer text-center"
              :class="
                selectedHour12 === h
                  ? 'bg-zinc-900 text-white shadow-2xs font-bold'
                  : 'bg-zinc-50 hover:bg-zinc-100 text-zinc-800'
              "
            >
              {{ String(h).padStart(2, '0') }}
            </button>
          </div>
        </div>

        <!-- Minutes Column -->
        <div class="space-y-1.5">
          <div class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
            Minute
          </div>
          <div class="grid grid-cols-2 gap-1">
            <button
              v-for="m in minutes"
              :key="m"
              type="button"
              @click="selectMinute(m)"
              class="py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer text-center"
              :class="
                selectedMinute === m
                  ? 'bg-zinc-900 text-white shadow-2xs font-bold'
                  : 'bg-zinc-50 hover:bg-zinc-100 text-zinc-800'
              "
            >
              :{{ String(m).padStart(2, '0') }}
            </button>
          </div>

          <!-- Quick fine minutes (e.g. 05, 10, 20...) if needed -->
          <div class="pt-1">
            <div class="text-[9px] text-zinc-400 font-medium">Fine step:</div>
            <div class="grid grid-cols-4 gap-1 mt-1">
              <button
                v-for="fm in [5, 10, 20, 25, 35, 40, 50, 55]"
                :key="fm"
                type="button"
                @click="selectMinute(fm)"
                class="py-0.5 text-[10px] font-mono rounded text-zinc-600 hover:bg-zinc-100 transition-colors"
                :class="selectedMinute === fm ? 'bg-zinc-900 text-white font-semibold' : ''"
              >
                :{{ String(fm).padStart(2, '0') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Presets -->
      <div class="mt-3 pt-3 border-t border-zinc-100 space-y-1.5">
        <div class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
          Quick Presets
        </div>
        <div class="grid grid-cols-4 gap-1">
          <button
            v-for="preset in quickPresets"
            :key="preset.time"
            type="button"
            @click="applyPreset(preset.time)"
            class="px-1.5 py-1 text-[10px] font-medium rounded border text-center transition-colors cursor-pointer truncate"
            :class="
              props.modelValue === preset.time
                ? 'bg-zinc-900 text-white border-zinc-900 font-semibold'
                : 'bg-zinc-50 text-zinc-600 border-zinc-200 hover:border-zinc-300 hover:bg-zinc-100'
            "
          >
            {{ preset.label }}
          </button>
        </div>
      </div>

      <!-- Done Button -->
      <div class="mt-3 pt-2 border-t border-zinc-100 flex items-center justify-end">
        <button
          type="button"
          @click="isOpen = false"
          class="w-full py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold rounded-md transition-colors cursor-pointer shadow-2xs text-center"
        >
          Done
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
