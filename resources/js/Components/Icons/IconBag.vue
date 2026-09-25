<template>
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    :width="size || 20" 
    :height="size || 20" 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    :stroke-width="strokeWidth || 2" 
    stroke-linecap="square" 
    stroke-linejoin="miter"
    class="inline-block flex-shrink-0"
  >
    <!-- Geometric Modernist Shopping Bag (Zero rounded corners) -->
    <path d="M4 7h16v14H4z" />
    <path d="M8 7V4h8v3" />
    <line x1="4" y1="10" x2="20" y2="10" stroke-dasharray="1 1" />
  </svg>
</template>

<script setup>
defineProps({
  size: {
    type: [Number, String],
    default: 20
  },
  strokeWidth: {
    type: [Number, String],
    default: 2
  }
});
</script>
