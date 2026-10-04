import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

export function timeMinutes(time) {
  const match = String(time).match(/^(\d{1,2}):(\d{2})(?:\s*(AM|PM))?/i);
  if (!match) return NaN;
  let hour = Number(match[1]);
  if (match[3]) hour = hour % 12 + (match[3].toUpperCase() === 'PM' ? 12 : 0);
  return hour * 60 + Number(match[2]);
}

function formatTime(minutes) {
  const hour = Math.floor(minutes / 60);
  return `${String(hour % 12 || 12).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')} ${hour >= 12 ? 'PM' : 'AM'}`;
}

export function pickupOptions(info, day, now, timezone) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(now).map(part => [part.type, part.value]));
  const today = `${parts.year}-${parts.month}-${parts.day}`;
  const date = new Date(`${today}T12:00:00Z`);
  if (day === 'tomorrow') date.setUTCDate(date.getUTCDate() + 1);
  const start = timeMinutes(info.pickup_slot_start_time ?? '09:00');
  const end = timeMinutes(info.pickup_slot_end_time ?? '21:00');
  const currentMinutes = Number(parts.hour) * 60 + Number(parts.minute) + Number(parts.second) / 60;
  const earliest = currentMinutes + Number(info.effective_prep_time_minutes ?? 15) - (day === 'tomorrow' ? 1440 : 0);
  const isValid = minutes => info.is_pickup_active !== false && minutes >= start && minutes <= end && minutes >= earliest;
  const slots = (info.available_pickup_slots ?? info.pickup_slots ?? [])
    .filter(slot => slot.active !== false)
    .map(slot => ({ ...slot, disabled: !isValid(timeMinutes(slot.label)) }));
  const times = [];
  for (let minutes = Math.ceil(start / 15) * 15; minutes <= end; minutes += 15) {
    if (isValid(minutes)) times.push(formatTime(minutes));
  }
  return { date: date.toISOString().slice(0, 10), slots, times };
}

export function useCheckoutSchedule(storeInfo, timezone) {
  const pickupTimingMode = ref('asap');
  const scheduledDay = ref('today');
  const scheduledTimingType = ref('slot');
  const selectedScheduledTime = ref('');
  const customHour = ref('');
  const customMinute = ref('');
  const customPeriod = ref('PM');
  const now = ref(new Date());
  let timer;
  onMounted(() => { timer = setInterval(() => { now.value = new Date(); }, 1000); });
  onUnmounted(() => clearInterval(timer));
  const options = computed(() => pickupOptions(storeInfo.value, scheduledDay.value, now.value, timezone.value));
  const availableScheduledSlots = computed(() => options.value.slots);
  const formattedCustomTime = computed(() => customHour.value && customMinute.value
    ? `${customHour.value}:${customMinute.value} ${customPeriod.value}` : '');
  const periodTimes = computed(() => options.value.times.filter(time => time.endsWith(customPeriod.value)));
  const availableHours = computed(() => [...new Set(periodTimes.value.map(time => time.slice(0, 2)))]);
  const availableMinutes = computed(() => periodTimes.value.filter(time => time.startsWith(`${customHour.value}:`)).map(time => time.slice(3, 5)));
  const isAmAvailable = computed(() => options.value.times.some(time => time.endsWith('AM')));
  const isPmAvailable = computed(() => options.value.times.some(time => time.endsWith('PM')));
  const popularTimesList = computed(() => options.value.times.filter((_, index) => index % 6 === 0).slice(0, 6));

  function setCustomTime(time) {
    if (!options.value.times.includes(time)) return;
    customHour.value = time.slice(0, 2);
    customMinute.value = time.slice(3, 5);
    customPeriod.value = time.slice(-2);
  }

  watch([options, customPeriod, customHour], () => {
    if (!options.value.slots.some(slot => slot.label === selectedScheduledTime.value && !slot.disabled)) {
      selectedScheduledTime.value = options.value.slots.find(slot => !slot.disabled)?.label ?? '';
    }
    if (!options.value.times.includes(formattedCustomTime.value)) {
      const next = periodTimes.value.find(time => time.startsWith(`${customHour.value}:`))
        ?? periodTimes.value[0] ?? options.value.times[0];
      if (next) setCustomTime(next);
      else { customHour.value = ''; customMinute.value = ''; }
    }
  }, { immediate: true });

  const scheduleError = computed(() => {
    if (storeInfo.value.is_pickup_active === false) return 'Store pickup is currently paused.';
    if (pickupTimingMode.value !== 'scheduled') return '';
    const valid = scheduledTimingType.value === 'slot'
      ? options.value.slots.some(slot => slot.label === selectedScheduledTime.value && !slot.disabled)
      : options.value.times.includes(formattedCustomTime.value);
    return valid ? '' : 'No pickup times available. Choose another day or timing option.';
  });

  function pickupPayload() {
    now.value = new Date();
    if (scheduleError.value) throw new Error(scheduleError.value);
    const minutes = timeMinutes(formattedCustomTime.value);
    return {
      pickup_timing_mode: pickupTimingMode.value,
      ...(pickupTimingMode.value === 'scheduled' ? {
        pickup_timing_type: scheduledTimingType.value,
        pickup_date: options.value.date,
        pickup_slot: scheduledTimingType.value === 'slot' ? selectedScheduledTime.value : null,
        pickup_time: scheduledTimingType.value === 'custom'
          ? `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}` : null,
      } : {}),
    };
  }

  return { pickupTimingMode, scheduledDay, scheduledTimingType, selectedScheduledTime,
    customHour, customMinute, customPeriod, availableHours, availableMinutes,
    isAmAvailable, isPmAvailable, popularTimesList, formattedCustomTime,
    availableScheduledSlots, setCustomTime, scheduleError, pickupPayload };
}
