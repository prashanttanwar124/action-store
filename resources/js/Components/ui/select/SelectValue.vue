<script setup>
import { SelectValue } from 'reka-ui';

const props = defineProps({
  placeholder: { type: String, default: '' },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'span' },
});
</script>

<template>
  <SelectValue v-bind="props" />
</template>
