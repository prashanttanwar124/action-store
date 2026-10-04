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
  <textarea
    v-model="modelValue"
    :class="
      cn(
        'flex min-h-[80px] w-full rounded-2xl border border-input bg-background px-3.5 py-2.5 text-xs transition-all placeholder:text-muted-foreground focus:outline-none focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:bg-white focus-visible:outline-none focus-visible:border-[#1a1a1a] focus-visible:ring-3 focus-visible:ring-[#1a1a1a]/10 focus-visible:bg-white disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  />
</template>
