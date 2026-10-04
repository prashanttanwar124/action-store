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
        <div class="lg:col-span-5 space-y-4">
          <div class="bg-white rounded-3xl border border-[#e0d9cc] p-6 space-y-4 shadow-xs">
            <div class="border-b border-[#e0d9cc]/60 pb-3 flex items-center justify-between">
              <h2 class="text-base font-serif font-medium text-[#1d1d1f] tracking-tight leading-none">
                Order Review
              </h2>
              <span class="text-xs font-normal text-[#6e6e73]">
                {{ itemCount }} items
              </span>
            </div>

            <!-- Items Quick View -->
            <div class="max-h-60 overflow-y-auto divide-y divide-[#e0d9cc]/60 pr-1">
              <div
                v-for="item in items"
                :key="item.id"
                class="py-2.5 flex items-center justify-between gap-3 text-xs"
              >
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-[#f3efe7] border border-[#e0d9cc] overflow-hidden shrink-0 relative">
                    <img
                      :src="item.image || (item.name.toLowerCase().includes('atta') ? '/images/products/atta.jpg' : (item.name.toLowerCase().includes('ghee') ? '/images/products/ghee.jpg' : (item.name.toLowerCase().includes('paneer') ? '/images/products/paneer.jpg' : (item.name.toLowerCase().includes('rice') ? '/images/products/rice.jpg' : (item.name.toLowerCase().includes('biscuit') ? '/images/products/biscuits.jpg' : (item.name.toLowerCase().includes('masala') ? '/images/products/garam_masala.jpg' : '/images/products/sweets.jpg'))))))"
                      :alt="item.name"
                      class="w-full h-full object-cover object-center"
                    />
                  </div>

                  <div>
                    <div class="font-semibold text-[#1d1d1f] leading-snug">{{ item.name }}</div>
                    <div class="text-[11px] text-[#6e6e73] font-normal">{{ item.quantity }}x · {{ item.weight }}</div>
                  </div>
                </div>

                <div class="font-serif font-medium text-[#1d1d1f] shrink-0 text-right">
                  ${{ (item.price * item.quantity).toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Price Breakdown -->
            <div class="pt-3 border-t border-[#e0d9cc]/60 space-y-2 text-xs">
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Subtotal</span>
                <span class="font-serif font-medium text-[#1d1d1f]">${{ subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-[#6e6e73] font-normal">
                <span>Fulfillment ({{ fulfillmentMode === 'delivery' ? 'Home Delivery' : 'Store Pickup' }})</span>
                <span :class="deliveryFeeAmount === 0 ? 'text-[#7a5620] font-semibold' : 'text-[#1d1d1f] font-semibold'">
                  {{ deliveryFeeAmount === 0 ? 'FREE' : `$${deliveryFeeAmount.toFixed(2)}` }}
                </span>
              </div>

              <div class="pt-2.5 border-t border-[#e0d9cc]/60 flex justify-between items-baseline text-[#1d1d1f]">
                <span class="font-serif font-medium text-sm">Total Due</span>
                <span class="font-serif font-medium text-2xl text-[#1d1d1f]">
                  ${{ checkoutTotal }}
                </span>
              </div>
            </div>
          </div>
        </div>

</template>
