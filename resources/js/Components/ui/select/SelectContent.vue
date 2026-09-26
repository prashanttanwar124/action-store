<script setup>
import { SelectContent, SelectPortal, SelectViewport, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  forceMount: { type: Boolean, default: undefined },
  position: { type: String, default: 'popper' },
  bodyLock: { type: Boolean, default: undefined },
  side: { type: null, default: undefined },
  sideOffset: { type: Number, default: 4 },
  align: { type: null, default: 'start' },
  alignOffset: { type: Number, default: 0 },
  avoidCollisions: { type: Boolean, default: true },
  class: { type: null, default: '' },
});

const emits = defineEmits(['closeAutoFocus']);
const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <SelectPortal>
    <SelectContent
      v-bind="{ ...forwarded, ...$attrs }"
      :class="
        cn(
          'relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-2xl border border-[#e0d9cc] bg-white text-stone-900 shadow-xl data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
          position === 'popper' &&
            'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
          props.class,
        )
      "
    >
      <SelectViewport
        :class="
          cn(
            'p-1.5',
            position === 'popper' &&
              'w-full min-w-[var(--reka-select-trigger-width)]',
          )
        "
      >
        <slot />
      </SelectViewport>
    </SelectContent>
  </SelectPortal>
</template>
