<template>
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    :width="size || 16" 
    :height="size || 16" 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    :stroke-width="strokeWidth || 2" 
    stroke-linecap="square" 
    stroke-linejoin="miter"
    class="inline-block flex-shrink-0"
  >
    <!-- Sharp Modernist Cold Chain / Snowflake Icon -->
    <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M4.93 19.07l14.14-14.14" />
    <path d="M10 4l2-2 2 2M10 20l2 2 2-2M4 10l-2 2 2 2M20 10l2 2-2 2" />
  </svg>
</template>

<script setup>
defineProps({
  size: {
    type: [Number, String],
    default: 16
  },
  strokeWidth: {
    type: [Number, String],
    default: 2
  }
});
</script>
