<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-xl border border-[#e0d9cc] bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-700 shadow-2xs transition duration-150 ease-in-out hover:bg-stone-50 focus:outline-none focus:ring-2 focus:ring-[#1a1a1a]/20 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
