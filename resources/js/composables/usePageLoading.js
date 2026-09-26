import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

function detectPageType(path) {
  if (!path) return 'home';
  if (path.startsWith('/admin')) return 'admin';
  if (path.startsWith('/products')) return 'product';
  if (path.startsWith('/cart') || path.startsWith('/checkout')) return 'cart';
  if (path.startsWith('/account') || path.startsWith('/reorder')) return 'account';
  return 'home';
}

const initialPath = typeof window !== 'undefined' ? window.location.pathname : '';
const isPageLoading = ref(true); // Active on initial first-time load / refresh
const isNavigating = ref(false); // Active during in-flight page navigation
const targetPageType = ref(detectPageType(initialPath));
let timer = null;

if (typeof window !== 'undefined') {
  // Smooth initial reveal after first load skeleton
  timer = setTimeout(() => {
    isPageLoading.value = false;
  }, 280);

  // When Inertia starts navigating to a new URL, activate top progress indicator
  router.on('start', (event) => {
    isNavigating.value = true;
    const path = event?.detail?.visit?.url?.pathname || window.location.pathname || '';
    targetPageType.value = detectPageType(path);
  });

  // When navigation finishes or cancels, deactivate top indicator
  router.on('finish', () => {
    isNavigating.value = false;
  });

  router.on('cancel', () => {
    isNavigating.value = false;
  });

  router.on('error', () => {
    isNavigating.value = false;
  });
}

export function usePageLoading() {
  return {
    isPageLoading,
    isNavigating,
    targetPageType,
  };
}
