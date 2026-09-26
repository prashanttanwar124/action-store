<script setup>
import { SelectItem, SelectItemIndicator, SelectItemText, useForwardProps } from 'reka-ui';
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
  value: { type: null, required: true },
  disabled: { type: Boolean, default: false },
  textValue: { type: String, default: undefined },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
  class: { type: null, default: '' },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
  <SelectItem
    v-bind="forwardedProps"
    :class="
      cn(
        'relative flex w-full cursor-pointer select-none items-center rounded-xl py-2 pl-8 pr-2.5 text-xs outline-none focus:bg-[#f5eee2] focus:text-[#7a5620] data-[disabled]:pointer-events-none data-[disabled]:opacity-50 transition-colors',
        props.class,
      )
    "
  >
    <span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
      <SelectItemIndicator>
        <Check class="h-4 w-4 text-[#a47a3c]" />
      </SelectItemIndicator>
    </span>

    <SelectItemText>
      <slot />
    </SelectItemText>
  </SelectItem>
</template>
