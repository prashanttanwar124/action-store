<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-xl border-[#e0d9cc] bg-white text-xs px-3.5 py-2 shadow-2xs focus:border-[#1a1a1a] focus:ring-3 focus:ring-[#1a1a1a]/10 focus:outline-none transition-all"
        v-model="model"
        ref="input"
    />
</template>
