<script setup>
import { SelectGroup } from 'reka-ui';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'div' },
});
</script>

<template>
  <SelectGroup v-bind="props">
    <slot />
  </SelectGroup>
</template>
