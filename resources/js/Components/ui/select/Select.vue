<script setup>
import { SelectRoot, useForwardPropsEmits } from 'reka-ui';

const props = defineProps({
  open: { type: Boolean, default: undefined },
  defaultOpen: { type: Boolean, default: undefined },
  defaultValue: { type: null, default: undefined },
  modelValue: { type: null, default: undefined },
  dir: { type: String, default: undefined },
  name: { type: String, default: undefined },
  autocomplete: { type: String, default: undefined },
  disabled: { type: Boolean, default: undefined },
  required: { type: Boolean, default: undefined },
});

const emits = defineEmits(['update:modelValue', 'update:open']);
const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <SelectRoot v-bind="forwarded">
    <slot />
  </SelectRoot>
</template>
