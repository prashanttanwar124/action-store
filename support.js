/**
 * Support.js for Masala Mart Click-and-Collect UI Prototype
 * Coordinates state, screen transitions, cart calculations, and UI interactions
 */

import { formatCurrency, calculateDeliveryProgress } from './_ds/modernist-7555aeb4-b620-4b84-a715-07a153cd6e8f/_ds_bundle.js';

// Initial reactive state matching the exact PDF specification
export const state = {
  currentScreen: 'home',
  cart: [
    {
      id: 'paneer',
      name: 'Amul Malai Paneer',
      weight: '400 g',
      price: 5.99,
      originalPrice: 5.99,
      quantity: 1,
      isSubscribed: false,
    },
    {
      id: 'atta',
      name: 'Aashirvaad Chakki Atta',
      weight: '20 lb',
      price: 18.04, // 5% subscribe & save discount applied from $18.99
      originalPrice: 18.99,
      quantity: 1,
      isSubscribed: true,
      subscriptionInterval: 'Monthly',
      nextDelivery: 'Oct 3',
    },
    {
      id: 'masala',
      name: 'Everest Garam Masala',
      weight: '100 g',
      price: 3.49,
      originalPrice: 3.49,
      quantity: 1,
      isSubscribed: false,
    }
  ],
  impulseItems: [
    { id: 'maggi', name: 'Maggi 2-Minute Masala Noodles', price: 1.49, unit: '70 g' },
    { id: 'curry-leaves', name: 'Fresh Curry Leaves', price: 0.99, unit: '1 bunch' },
    { id: 'lays', name: 'Lay\'s India\'s Magic Masala', price: 1.79, unit: '50 g' },
  ],
  quantities: {
    atta: 1,
    ghee: 0,
    biscuits: 0,
    paneer: 1,
  },
  deliveryMethod: 'pickup', // 'pickup' | 'delivery'
  selectedTimeSlot: 'today-4-5',
  rewardPoints: 1240,
  freeDeliveryThreshold: 40.00,
};

/**
 * Switch active screen
 */
export function setScreen(screenId) {
  state.currentScreen = screenId;

  // Toggle screens
  document.querySelectorAll('.screen').forEach(el => {
    el.classList.add('hidden');
  });
  const target = document.getElementById('screen-' + screenId);
  if (target) {
    target.classList.remove('hidden');
  }

  // Update top switcher active button
  document.querySelectorAll('[id^="btn-"]').forEach(el => {
    el.className = 'px-2 py-1 bg-zinc-800 text-zinc-300 hover:text-white cursor-pointer';
  });
  const activeBtn = document.getElementById('btn-' + screenId);
  if (activeBtn) {
    activeBtn.className = 'px-2 py-1 bg-[#E52E04] text-white font-bold cursor-pointer';
  }

  // Update bottom nav icons
  ['shop', 'search', 'reorder', 'account'].forEach(name => {
    const el = document.getElementById('nav-' + name);
    if (el) {
      el.className = 'w-full py-1 text-zinc-600 font-medium flex flex-col items-center';
    }
  });

  // Floating cart bar visibility: show on home and product detail pages, hide on cart/checkout
  const floatBar = document.getElementById('floating-cart-bar');
  if (floatBar) {
    if (screenId === 'home' || screenId === 'pdp-paneer' || screenId === 'pdp-rice') {
      floatBar.classList.remove('hidden');
    } else {
      floatBar.classList.add('hidden');
    }
  }

  // Highlight bottom navigation button
  if (screenId === 'home') {
    const shopNav = document.getElementById('nav-shop');
    if (shopNav) shopNav.className = 'w-full py-1 text-[#E52E04] font-black flex flex-col items-center';
  } else if (screenId === 'account') {
    const acctNav = document.getElementById('nav-account');
    if (acctNav) acctNav.className = 'w-full py-1 text-[#E52E04] font-black flex flex-col items-center';
  }

  window.scrollTo({ top: 0, behavior: 'instant' });
}

/**
 * Adjust quantity on home or PDP
 */
export function changeQty(itemId, delta) {
  state.quantities[itemId] = Math.max(0, (state.quantities[itemId] || 0) + delta);
  const qtyEl = document.getElementById(`qty-${itemId}-root`);
  if (qtyEl) {
    qtyEl.innerText = state.quantities[itemId];
  }

  // Also sync with cart if present
  const cartItem = state.cart.find(i => i.id === itemId);
  if (cartItem) {
    cartItem.quantity = Math.max(1, cartItem.quantity + delta);
    updateCartUI();
  }
}

/**
 * Add impulse or quick item to cart
 */
export function addImpulseItem(itemId) {
  const impulse = state.impulseItems.find(i => i.id === itemId);
  if (impulse) {
    const existing = state.cart.find(i => i.id === itemId);
    if (existing) {
      existing.quantity += 1;
    } else {
      state.cart.push({
        id: impulse.id,
        name: impulse.name,
        weight: impulse.unit,
        price: impulse.price,
        originalPrice: impulse.price,
        quantity: 1,
        isSubscribed: false,
      });
    }
    showToast(`Added ${impulse.name} to cart`);
    updateCartUI();
  }
}

/**
 * Recalculate totals and update all cart indicators
 */
export function updateCartUI() {
  const subtotal = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const totalCount = state.cart.reduce((sum, item) => sum + item.quantity, 0);
  const progress = calculateDeliveryProgress(subtotal, state.freeDeliveryThreshold);

  // Update floating bar
  const floatBarCount = document.querySelector('#floating-cart-bar .cart-count-text');
  const floatBarSubtotal = document.querySelector('#floating-cart-bar .cart-subtotal-text');
  const floatBarMeter = document.querySelector('#floating-cart-bar .cart-progress-meter');
  const floatBarRemaining = document.querySelector('#floating-cart-bar .cart-remaining-text');

  if (floatBarCount) floatBarCount.textContent = `View cart · ${totalCount} items`;
  if (floatBarSubtotal) floatBarSubtotal.textContent = formatCurrency(subtotal);
  if (floatBarRemaining) {
    floatBarRemaining.textContent = progress.unlocked
      ? '🎉 Free delivery unlocked!'
      : `${formatCurrency(progress.remaining)} to free delivery`;
  }
  if (floatBarMeter) {
    floatBarMeter.style.width = `${progress.percent}%`;
  }

  // Update cart badge icon in header
  document.querySelectorAll('.cart-badge-count').forEach(el => {
    el.textContent = totalCount;
  });

  // Update Cart Screen Elements
  const cartSubtotalEl = document.getElementById('cart-subtotal-val');
  const cartTotalEl = document.getElementById('cart-total-val');
  const checkoutBtnText = document.getElementById('cart-checkout-btn-text');
  const cartDeliveryNotice = document.getElementById('cart-delivery-notice');
  const cartDeliveryBar = document.getElementById('cart-delivery-bar');

  if (cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(subtotal);
  if (cartTotalEl) cartTotalEl.textContent = formatCurrency(subtotal);
  if (checkoutBtnText) checkoutBtnText.textContent = `Go to checkout ${formatCurrency(subtotal)}`;
  if (cartDeliveryNotice) {
    cartDeliveryNotice.textContent = progress.unlocked
      ? '🎉 You unlocked free delivery!'
      : `Add ${formatCurrency(progress.remaining)} more to unlock free delivery`;
  }
  if (cartDeliveryBar) {
    cartDeliveryBar.style.width = `${progress.percent}%`;
  }
}

/**
 * Toast Notification Helper
 */
export function showToast(message) {
  let toast = document.getElementById('ds-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'ds-toast';
    toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-zinc-950 text-white px-4 py-2 border border-zinc-700 shadow-xl text-xs font-bold transition-opacity duration-300 opacity-0 pointer-events-none';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.remove('opacity-0');
  toast.classList.add('opacity-100');
  setTimeout(() => {
    toast.classList.remove('opacity-100');
    toast.classList.add('opacity-0');
  }, 2200);
}

// Bind to window for HTML event handlers (e.g. onclick="setScreen('pdp-paneer')")
if (typeof window !== 'undefined') {
  window.setScreen = setScreen;
  window.changeQty = changeQty;
  window.addImpulseItem = addImpulseItem;
  window.updateCartUI = updateCartUI;
  window.showToast = showToast;
  window.state = state;
}

export default {
  state,
  setScreen,
  changeQty,
  addImpulseItem,
  updateCartUI,
  showToast,
};
