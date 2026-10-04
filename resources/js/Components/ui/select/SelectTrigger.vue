<script setup>
import { SelectIcon, SelectTrigger, useForwardProps } from 'reka-ui';
import { ChevronDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'button' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <SelectTrigger
    v-bind="forwardedProps"
    :class="
      cn(
        'flex h-10 w-full items-center justify-between rounded-xl border border-input bg-background px-3.5 py-2 text-xs transition-all placeholder:text-muted-foreground focus:outline-none focus:border-[#1a1a1a] disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1 cursor-pointer',
        props.class,
      )
    "
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="h-4 w-4 opacity-50 shrink-0" />
    </SelectIcon>
  </SelectTrigger>
</template>
