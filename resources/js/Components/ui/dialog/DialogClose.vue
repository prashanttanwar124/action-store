<script setup>
import { DialogClose } from 'reka-ui';

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'button' },
});
</script>

<template>
  <DialogClose v-bind="props">
    <slot />
  </DialogClose>
</template>
