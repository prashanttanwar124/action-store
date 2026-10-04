import { computed, ref, watch } from 'vue';

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

export function pickupOptions(info, day, now, timezone, bookedSlots = {}, maxCapacity = 0, pickupDays = null) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(now).map(part => [part.type, part.value]));
  const today = `${parts.year}-${parts.month}-${parts.day}`;
  const date = new Date(`${today}T12:00:00Z`);
  if (day === 'tomorrow') date.setUTCDate(date.getUTCDate() + 1);

  const dayOfWeek = new Intl.DateTimeFormat('en-US', { timeZone: timezone, weekday: 'long' })
    .format(date)
    .toLowerCase();
  const isDayOpen = !pickupDays || !Array.isArray(pickupDays) || pickupDays.length === 0 || pickupDays.includes(dayOfWeek);

  const start = timeMinutes(info.pickup_slot_start_time ?? '09:00');
  const end = timeMinutes(info.pickup_slot_end_time ?? '21:00');
  const currentMinutes = Number(parts.hour) * 60 + Number(parts.minute) + Number(parts.second) / 60;
  const earliest = currentMinutes + Number(info.effective_prep_time_minutes ?? 15) - (day === 'tomorrow' ? 1440 : 0);
  const isValid = minutes => info.is_pickup_active !== false && isDayOpen && minutes >= start && minutes <= end && minutes >= earliest;

  const dateKey = date.toISOString().slice(0, 10);
  const dayBookings = bookedSlots[dateKey] || {};
  const isSlotFull = (label) => {
    if (!maxCapacity || maxCapacity <= 0) return false;
    const count = dayBookings[label] || 0;
    return count >= maxCapacity;
  };

  const slots = (info.available_pickup_slots ?? info.pickup_slots ?? [])
    .filter(slot => slot.active !== false)
    .map(slot => {
      const full = isSlotFull(slot.label);
      const timeValid = isValid(timeMinutes(slot.label));
      return {
        ...slot,
        isFull: full,
        disabled: !isDayOpen || full || !timeValid,
      };
    });

  const times = [];
  if (isDayOpen) {
    for (let minutes = Math.ceil(start / 15) * 15; minutes <= end; minutes += 15) {
      if (isValid(minutes)) times.push(formatTime(minutes));
    }
  }
  return { date: date.toISOString().slice(0, 10), slots, times, isDayOpen };
}

export function useCheckoutSchedule(storeInfo, timezone, scheduleConfig = {}) {
  const pickupTimingMode = ref('asap');
  const scheduledDay = ref('today');
  const scheduledTimingType = ref('slot');
  const selectedScheduledTime = ref('');
  const customHour = ref('');
  const customMinute = ref('');
  const customPeriod = ref('PM');
  const now = ref(new Date());

  const bookedSlots = computed(() => scheduleConfig.bookedSlots?.value ?? scheduleConfig.bookedSlots ?? {});
  const maxCapacity = computed(() => Number(scheduleConfig.maxOrdersPerSlot?.value ?? scheduleConfig.maxOrdersPerSlot ?? 0));
  const pickupDays = computed(() => scheduleConfig.pickupDays?.value ?? scheduleConfig.pickupDays ?? null);

  const options = computed(() => pickupOptions(
    storeInfo.value,
    scheduledDay.value,
    now.value,
    timezone.value,
    bookedSlots.value,
    maxCapacity.value,
    pickupDays.value
  ));
  const availableScheduledSlots = computed(() => options.value.slots);
  const formattedCustomTime = computed(() => customHour.value && customMinute.value
    ? `${customHour.value}:${customMinute.value} ${customPeriod.value}` : '');
  const periodTimes = computed(() => options.value.times.filter(time => time.endsWith(customPeriod.value)));
  const availableHours = computed(() => [...new Set(periodTimes.value.map(time => time.slice(0, 2)))]);
  const availableMinutes = computed(() => periodTimes.value.filter(time => time.startsWith(`${customHour.value}:`)).map(time => time.slice(3, 5)));
  const isAmAvailable = computed(() => options.value.times.some(time => time.endsWith('AM')));
  const isPmAvailable = computed(() => options.value.times.some(time => time.endsWith('PM')));
  const tz = computed(() => timezone?.value || 'America/Toronto');

  const todayOptions = computed(() => pickupOptions(
    storeInfo.value,
    'today',
    now.value,
    tz.value,
    bookedSlots.value,
    maxCapacity.value,
    pickupDays.value
  ));

  const tomorrowOptions = computed(() => pickupOptions(
    storeInfo.value,
    'tomorrow',
    now.value,
    tz.value,
    bookedSlots.value,
    maxCapacity.value,
    pickupDays.value
  ));

  const isTodayAvailable = computed(() => {
    return todayOptions.value.isDayOpen && (
      todayOptions.value.slots.some(s => !s.disabled) || todayOptions.value.times.length > 0
    );
  });

  const isTomorrowAvailable = computed(() => {
    return tomorrowOptions.value.isDayOpen && (
      tomorrowOptions.value.slots.some(s => !s.disabled) || tomorrowOptions.value.times.length > 0
    );
  });

  // If today has ended or has no slots left, automatically switch to tomorrow!
  watch([isTodayAvailable, isTomorrowAvailable], ([todayOk, tomorrowOk]) => {
    if (!todayOk && tomorrowOk && scheduledDay.value === 'today') {
      scheduledDay.value = 'tomorrow';
    }
  }, { immediate: true });

  const todayDateObj = computed(() => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
      timeZone: tz.value, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(now.value).map(part => [part.type, part.value]));
    return new Date(`${parts.year}-${parts.month}-${parts.day}T12:00:00Z`);
  });

  const tomorrowDateObj = computed(() => {
    const d = new Date(todayDateObj.value.getTime());
    d.setUTCDate(d.getUTCDate() + 1);
    return d;
  });

  const todayFormatted = computed(() => new Intl.DateTimeFormat('en-US', {
    timeZone: 'UTC',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  }).format(todayDateObj.value));

  const tomorrowFormatted = computed(() => new Intl.DateTimeFormat('en-US', {
    timeZone: 'UTC',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  }).format(tomorrowDateObj.value));

  const popularTimesList = computed(() => options.value.times.filter((_, index) => index % 6 === 0).slice(0, 6));

  const isAsapAvailable = computed(() => {
    if (storeInfo.value.is_pickup_active === false) return false;

    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
      timeZone: tz.value, year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).formatToParts(now.value).map(part => [part.type, part.value]));

    const todayDate = new Date(`${parts.year}-${parts.month}-${parts.day}T12:00:00Z`);
    const dayOfWeek = new Intl.DateTimeFormat('en-US', { timeZone: tz.value, weekday: 'long' })
      .format(todayDate)
      .toLowerCase();

    const allowedDays = pickupDays.value;
    if (allowedDays && Array.isArray(allowedDays) && allowedDays.length > 0 && !allowedDays.includes(dayOfWeek)) {
      return false;
    }

    const start = timeMinutes(storeInfo.value.pickup_slot_start_time ?? '09:00');
    const end = timeMinutes(storeInfo.value.pickup_slot_end_time ?? '21:00');
    const currentMinutes = Number(parts.hour) * 60 + Number(parts.minute) + Number(parts.second) / 60;
    const prepMinutes = Number(storeInfo.value.effective_prep_time_minutes ?? 15);
    const readyMinutes = currentMinutes + prepMinutes;

    return currentMinutes >= start && currentMinutes <= end && readyMinutes <= end;
  });

  const storeOpenTimeFormatted = computed(() => {
    const start = timeMinutes(storeInfo.value.pickup_slot_start_time ?? '09:00');
    return formatTime(start);
  });

  watch(isAsapAvailable, (available) => {
    if (!available && pickupTimingMode.value === 'asap') {
      pickupTimingMode.value = 'scheduled';
    }
  }, { immediate: true });

  function selectEarliestTime() {
    const earliest = options.value.times[0];
    if (earliest) {
      customHour.value = earliest.slice(0, 2);
      customMinute.value = earliest.slice(3, 5);
      customPeriod.value = earliest.slice(-2);
    } else {
      customHour.value = '';
      customMinute.value = '';
    }
  }

  function setCustomTime(time) {
    if (!options.value.times.includes(time)) return;
    customHour.value = time.slice(0, 2);
    customMinute.value = time.slice(3, 5);
    customPeriod.value = time.slice(-2);
  }

  // When scheduledDay changes (Today <-> Tomorrow):
  watch(scheduledDay, () => {
    selectEarliestTime();
    selectedScheduledTime.value = options.value.slots.find(slot => !slot.disabled)?.label ?? '';
  });

  // When options changes:
  watch(options, (newOpts) => {
    if (!newOpts.slots.some(slot => slot.label === selectedScheduledTime.value && !slot.disabled)) {
      selectedScheduledTime.value = newOpts.slots.find(slot => !slot.disabled)?.label ?? '';
    }
    if (!newOpts.times.includes(formattedCustomTime.value)) {
      selectEarliestTime();
    }
  }, { immediate: true });

  // When user toggles customPeriod (AM <-> PM):
  watch(customPeriod, (newPeriod) => {
    const timesInPeriod = options.value.times.filter(t => t.endsWith(newPeriod));
    if (timesInPeriod.length === 0) return;

    if (customHour.value) {
      const match = timesInPeriod.find(t => t.startsWith(`${customHour.value}:`));
      if (match) {
        setCustomTime(match);
        return;
      }
    }
    setCustomTime(timesInPeriod[0]);
  });

  // When user selects a different hour in dropdown:
  watch(customHour, (newHour) => {
    if (!newHour) return;
    const timesForHour = periodTimes.value.filter(t => t.startsWith(`${newHour}:`));
    if (timesForHour.length === 0) return;

    if (customMinute.value) {
      const match = timesForHour.find(t => t.slice(3, 5) === customMinute.value);
      if (match) {
        setCustomTime(match);
        return;
      }
    }
    setCustomTime(timesForHour[0]);
  });

  const scheduleError = computed(() => {
    if (storeInfo.value.is_pickup_active === false) return 'Store pickup is currently paused.';
    if (options.value.isDayOpen === false) return 'Store pickup is closed on this day. Please select another day.';
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

  return {
    pickupTimingMode, scheduledDay, scheduledTimingType, selectedScheduledTime,
    customHour, customMinute, customPeriod, availableHours, availableMinutes,
    isAmAvailable, isPmAvailable, popularTimesList, formattedCustomTime,
    availableScheduledSlots, setCustomTime, scheduleError, pickupPayload,
    todayFormatted, tomorrowFormatted, storeTimezone: tz,
    isDayOpen: computed(() => options.value.isDayOpen),
    isAsapAvailable,
    storeOpenTimeFormatted,
    isTodayAvailable,
    isTomorrowAvailable,
  };
}
