<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import {
  PopoverRoot,
  PopoverTrigger,
  PopoverPortal,
  PopoverContent,
} from 'reka-ui';
import { ChevronDown, Check, Search, X, Loader2 } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: 'Select an option...',
  },
  searchPlaceholder: {
    type: String,
    default: 'Search...',
  },
  emptyText: {
    type: String,
    default: 'No matching results found.',
  },
  limit: {
    type: Number,
    default: 8,
  },
  searchUrl: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  triggerClass: {
    type: String,
    default: '',
  },
  contentClass: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'select']);

const isOpen = ref(false);
const searchQuery = ref('');
const searchInputRef = ref(null);
const highlightedIndex = ref(0);
const isSearchingRemote = ref(false);
const remoteOptions = ref(null);
const cachedSelectedOption = ref(null);

let debounceTimer = null;

// Normalize incoming options to { value, label, sublabel, badge, raw }
const normalizedLocalOptions = computed(() => {
  return (props.options || []).map((opt) => ({
    value: String(opt.value ?? ''),
    label: String(opt.label ?? ''),
    sublabel: opt.sublabel ? String(opt.sublabel) : '',
    badge: opt.badge ? String(opt.badge) : '',
    raw: opt.raw ?? opt,
  }));
});

// Active pool of options (merges local + remote when remote search is active)
const activePool = computed(() => {
  if (remoteOptions.value !== null && searchQuery.value.trim() !== '') {
    return remoteOptions.value;
  }
  return normalizedLocalOptions.value;
});

// Filter options by search query
const filteredOptions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) {
    return activePool.value;
  }

  return activePool.value.filter((opt) => {
    return (
      opt.label.toLowerCase().includes(q) ||
      opt.sublabel.toLowerCase().includes(q) ||
      opt.value.toLowerCase().includes(q)
    );
  });
});

// Apply display limit
const visibleOptions = computed(() => {
  return filteredOptions.value.slice(0, props.limit);
});

const hasMoreThanLimit = computed(() => {
  return filteredOptions.value.length > props.limit;
});

// Resolve currently selected option for the trigger display
const selectedOption = computed(() => {
  const currentVal = props.modelValue !== null && props.modelValue !== undefined ? String(props.modelValue) : '';
  if (!currentVal) return null;

  const fromLocal = normalizedLocalOptions.value.find((o) => o.value === currentVal);
  if (fromLocal) return fromLocal;

  if (remoteOptions.value) {
    const fromRemote = remoteOptions.value.find((o) => o.value === currentVal);
    if (fromRemote) return fromRemote;
  }

  if (cachedSelectedOption.value && cachedSelectedOption.value.value === currentVal) {
    return cachedSelectedOption.value;
  }

  return null;
});

// Watch searchQuery for remote search if searchUrl is configured
watch(searchQuery, (newQuery) => {
  highlightedIndex.value = 0;

  if (!props.searchUrl) return;

  const trimmed = newQuery.trim();
  clearTimeout(debounceTimer);

  if (!trimmed) {
    remoteOptions.value = null;
    isSearchingRemote.value = false;
    return;
  }

  debounceTimer = setTimeout(async () => {
    isSearchingRemote.value = true;
    try {
      const url = new URL(props.searchUrl, window.location.origin);
      url.searchParams.set('q', trimmed);
      url.searchParams.set('limit', String(props.limit));

      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (response.ok) {
        const data = await response.json();
        const items = Array.isArray(data.items) ? data.items : [];
        // Keep any special static option like 'none' if it matches query
        const staticMatches = normalizedLocalOptions.value.filter(
          (o) => o.value === 'none' && o.label.toLowerCase().includes(trimmed.toLowerCase()),
        );
        const mappedRemote = items.map((opt) => ({
          value: String(opt.value ?? opt.id ?? ''),
          label: String(opt.label ?? opt.name ?? ''),
          sublabel: opt.sublabel ? String(opt.sublabel) : '',
          badge: opt.badge ? String(opt.badge) : '',
          raw: opt.raw ?? opt,
        }));
        remoteOptions.value = [...staticMatches, ...mappedRemote];
      }
    } catch {
      // Fallback silently to local filtering
    } finally {
      isSearchingRemote.value = false;
    }
  }, 220);
});

watch(isOpen, async (open) => {
  if (open) {
    searchQuery.value = '';
    remoteOptions.value = null;
    highlightedIndex.value = 0;
    await nextTick();
    setTimeout(() => {
      searchInputRef.value?.focus();
    }, 15);
  }
});

const selectItem = (option) => {
  cachedSelectedOption.value = option;
  emit('update:modelValue', option.value);
  emit('select', option);
  isOpen.value = false;
};

const handleKeydown = (e) => {
  const maxIdx = visibleOptions.value.length - 1;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlightedIndex.value = maxIdx >= 0 ? (highlightedIndex.value < maxIdx ? highlightedIndex.value + 1 : 0) : 0;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedIndex.value = maxIdx >= 0 ? (highlightedIndex.value > 0 ? highlightedIndex.value - 1 : maxIdx) : 0;
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (visibleOptions.value[highlightedIndex.value]) {
      selectItem(visibleOptions.value[highlightedIndex.value]);
    }
  } else if (e.key === 'Escape') {
    isOpen.value = false;
  }
};
</script>

<template>
  <PopoverRoot v-model:open="isOpen">
    <PopoverTrigger as-child :disabled="disabled">
      <button
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'flex h-9 w-full items-center justify-between rounded-md border border-input bg-[#fafafa] px-3 py-1.5 text-xs text-foreground shadow-2xs transition-colors focus:outline-none focus:border-primary disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer text-left',
            triggerClass,
          )
        "
      >
        <span
          class="line-clamp-1 truncate"
          :class="!selectedOption ? 'text-muted-foreground' : 'text-[#09090b]'"
        >
          <template v-if="selectedOption">
            {{ selectedOption.label }}
            <span v-if="selectedOption.sublabel" class="text-[#71717a] ml-1">
              {{ selectedOption.sublabel }}
            </span>
          </template>
          <template v-else>
            {{ placeholder }}
          </template>
        </span>
        <ChevronDown
          class="h-3.5 w-3.5 opacity-50 shrink-0 ml-1.5 transition-transform duration-150"
          :class="{ 'rotate-180': isOpen }"
        />
      </button>
    </PopoverTrigger>

    <PopoverPortal>
      <PopoverContent
        align="start"
        :side-offset="4"
        :collision-padding="8"
        :class="
          cn(
            'z-50 w-[var(--reka-popover-trigger-width)] min-w-[230px] overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2',
            contentClass,
          )
        "
        @open-auto-focus.prevent
      >
        <!-- Sticky Search Input Bar -->
        <div class="flex items-center gap-2 border-b border-[#e4e4e7] bg-[#fafafa] px-2.5 py-2">
          <Search class="w-3.5 h-3.5 text-[#71717a] shrink-0" />
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full bg-transparent text-xs text-[#09090b] placeholder:text-[#71717a] focus:outline-none"
            @keydown="handleKeydown"
          />
          <Loader2
            v-if="isSearchingRemote"
            class="w-3.5 h-3.5 text-[#71717a] animate-spin shrink-0"
          />
          <button
            v-else-if="searchQuery"
            type="button"
            @click="searchQuery = ''; searchInputRef?.focus()"
            class="text-[#71717a] hover:text-[#09090b] p-0.5 rounded cursor-pointer"
            title="Clear search"
          >
            <X class="w-3 h-3" />
          </button>
        </div>

        <!-- Options Viewport -->
        <div class="max-h-60 overflow-y-auto p-1">
          <div
            v-if="visibleOptions.length === 0"
            class="py-6 px-3 text-center text-xs text-[#71717a]"
          >
            {{ emptyText }}
          </div>

          <button
            v-for="(opt, idx) in visibleOptions"
            :key="opt.value"
            type="button"
            @click="selectItem(opt)"
            @mouseenter="highlightedIndex = idx"
            :class="
              cn(
                'relative flex w-full cursor-pointer select-none items-center justify-between rounded-md py-1.5 pl-8 pr-2.5 text-xs outline-none transition-colors text-left',
                idx === highlightedIndex
                  ? 'bg-secondary text-secondary-foreground'
                  : 'text-foreground hover:bg-secondary/60',
              )
            "
          >
            <span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
              <Check
                v-if="String(modelValue) === opt.value"
                class="h-3.5 w-3.5 text-primary"
              />
            </span>

            <span class="truncate">
              <span class="font-medium">{{ opt.label }}</span>
              <span v-if="opt.sublabel" class="text-[#71717a] ml-1">{{ opt.sublabel }}</span>
            </span>

            <span
              v-if="opt.badge"
              class="ml-2 shrink-0 text-[10px] font-mono bg-[#f4f4f5] text-[#52525b] px-1.5 py-0.5 rounded border border-[#e4e4e7]"
            >
              {{ opt.badge }}
            </span>
          </button>
        </div>

        <!-- Limit & Search Hint Footer -->
        <div
          v-if="hasMoreThanLimit || (searchUrl && !searchQuery)"
          class="border-t border-[#e4e4e7] bg-[#fafafa] px-2.5 py-1.5 flex items-center justify-between text-[10px] text-[#71717a]"
        >
          <span v-if="hasMoreThanLimit">
            Showing top {{ limit }} of {{ filteredOptions.length }}
          </span>
          <span v-else>
            Showing {{ visibleOptions.length }} (limit {{ limit }})
          </span>
          <span class="font-medium text-[#52525b]">Type to search more…</span>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

