<script setup>
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps({
  defaultValue: { type: [String, Number], default: undefined },
  modelValue: { type: [String, Number], default: undefined },
  class: { type: null, default: '' },
});

const emits = defineEmits(['update:modelValue']);

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
});
</script>

<template>
  <input
    v-model="modelValue"
    :class="
      cn(
        'flex h-9 w-full rounded-lg border border-input bg-card px-3 py-1.5 text-xs text-foreground shadow-2xs transition-colors file:border-0 file:bg-transparent file:text-foreground file:text-xs file:font-medium placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:bg-white focus-visible:outline-none focus-visible:border-primary focus-visible:bg-white disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
