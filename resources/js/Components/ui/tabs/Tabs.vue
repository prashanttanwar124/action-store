<script setup>
import { TabsRoot, useForwardPropsEmits } from 'reka-ui';

const props = defineProps({
  defaultValue: { type: [String, Number], default: undefined },
  modelValue: { type: [String, Number], default: undefined },
  orientation: { type: String, default: 'horizontal' },
  dir: { type: String, default: 'ltr' },
  activationMode: { type: String, default: 'automatic' },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
});

const emits = defineEmits(['update:modelValue']);
const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <TabsRoot v-bind="forwarded">
    <slot />
  </TabsRoot>
</template>
