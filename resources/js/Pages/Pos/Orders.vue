<script setup>
import Register from './Register.vue';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    catalogSummary: {
        type: Object,
        default: () => ({ total_products: 0, weighed_products: 0 }),
    },
    currentShift: {
        type: Object,
        default: null,
    },
    cashiers: {
        type: Array,
        default: () => [],
    },
    loyaltyConfig: {
        type: Object,
        default: () => ({ points_per_block: 500, discount_per_block: 10 }),
    },
    drawerConfig: {
        type: Object,
        default: () => ({ safe_drop_alert_threshold: 1000 }),
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    initialTab: {
        type: String,
        default: 'orders',
    },
    initialOrderSearch: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Register
        :categories="categories"
        :catalog-summary="catalogSummary"
        :current-shift="currentShift"
        :cashiers="cashiers"
        :loyalty-config="loyaltyConfig"
        :drawer-config="drawerConfig"
        :recent-orders="recentOrders"
        initial-tab="orders"
        :initial-order-search="initialOrderSearch"
    />
</template>

