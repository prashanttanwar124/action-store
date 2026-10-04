<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="rounded border-[#e0d9cc] text-[#1a1a1a] shadow-xs focus:ring-2 focus:ring-[#1a1a1a]/15 focus:ring-offset-0 focus:outline-none accent-[#1a1a1a] cursor-pointer transition-colors"
    />
</template>
