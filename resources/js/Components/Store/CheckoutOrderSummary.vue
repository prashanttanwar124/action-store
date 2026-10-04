<script setup>
defineProps({
  items: { type: Array, required: true },
  itemCount: { type: Number, required: true },
  subtotal: { type: Number, required: true },
  fulfillmentMode: { type: String, required: true },
  deliveryFeeAmount: { type: Number, required: true },
  checkoutTotal: { type: String, required: true },
});
</script>

<template>
  <div class="hidden lg:block lg:col-span-5 lg:sticky lg:top-6 self-start space-y-4">
    <div class="bg-white rounded-3xl border border-[#e0d9cc] p-6 space-y-4 shadow-xs">
      <div class="border-b border-[#e0d9cc] pb-3.5 flex items-center justify-between">
        <h2 class="text-base font-serif font-semibold text-[#1d1d1f] tracking-tight leading-none">
          Order Review
        </h2>
        <span class="text-xs font-semibold text-stone-700 bg-[#f4efe6] border border-[#dfd6c8] px-2.5 py-0.5 rounded-full">
          {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}
        </span>
      </div>

      <!-- Items Quick View -->
      <div class="max-h-72 overflow-y-auto divide-y divide-[#e0d9cc]/60 pr-1">
        <div
          v-for="item in items"
          :key="item.id"
          class="py-3 flex items-center justify-between gap-3 text-xs"
        >
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0 relative">
              <img
                :src="item.image || (item.name.toLowerCase().includes('atta') ? '/images/products/atta.jpg' : (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : (item.name.toLowerCase().includes('paneer') ? '/images/products/paneer.jpg' : (item.name.toLowerCase().includes('rice') ? '/images/products/rice.jpg' : (item.name.toLowerCase().includes('biscuit') ? '/images/products/biscuits.jpg' : (item.name.toLowerCase().includes('masala') ? '/images/products/garam_masala.jpg' : '/images/products/sweets.jpg'))))))"
                :alt="item.name"
                class="w-full h-full object-cover object-center"
              />
            </div>

            <div>
              <div class="font-bold text-[#1d1d1f] leading-snug">{{ item.name }}</div>
              <div class="text-[11px] text-stone-600 font-medium mt-0.5">{{ item.quantity }}x · {{ item.weight }}</div>
            </div>
          </div>

          <div class="font-serif font-bold text-[#1d1d1f] shrink-0 text-right text-sm">
            ${{ (item.price * item.quantity).toFixed(2) }}
          </div>
        </div>
      </div>

      <!-- Price Breakdown -->
      <div class="pt-3 border-t border-[#e0d9cc] space-y-2.5 text-xs">
        <div class="flex justify-between text-stone-700 font-medium">
          <span>Subtotal</span>
          <span class="font-serif font-bold text-[#1d1d1f] text-sm">${{ subtotal.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-stone-700 font-medium">
          <span>Fulfillment ({{ fulfillmentMode === 'delivery' ? 'Home Delivery' : 'Store Pickup' }})</span>
          <span :class="deliveryFeeAmount === 0 ? 'text-[#8a6b32] font-bold tracking-wide' : 'text-[#1d1d1f] font-bold'">
            {{ deliveryFeeAmount === 0 ? 'FREE' : `$${deliveryFeeAmount.toFixed(2)}` }}
          </span>
        </div>

        <div class="pt-3 border-t border-[#e0d9cc] flex justify-between items-baseline text-[#1d1d1f]">
          <span class="font-serif font-semibold text-sm">Total Due</span>
          <span class="font-serif font-bold text-2xl text-[#1d1d1f]">
            ${{ checkoutTotal }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
