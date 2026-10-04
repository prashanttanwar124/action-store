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
        'flex h-10 w-full rounded-xl border border-input bg-background px-3.5 py-2 text-xs transition-all file:border-0 file:bg-transparent file:text-foreground file:text-xs file:font-medium placeholder:text-muted-foreground focus:outline-none focus:border-[#1a1a1a] focus:bg-white focus-visible:outline-none focus-visible:border-[#1a1a1a] focus-visible:bg-white disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
