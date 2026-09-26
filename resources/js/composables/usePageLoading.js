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
const isPageLoading = ref(true); // Active on initial first-time load
const targetPageType = ref(detectPageType(initialPath));
let timer = null;

if (typeof window !== 'undefined') {
  // Smooth initial reveal after first load skeleton
  timer = setTimeout(() => {
    isPageLoading.value = false;
  }, 320);

  // Register Inertia router listeners for subsequent navigations
  router.on('start', (event) => {
    if (timer) clearTimeout(timer);
    
    const path = event?.detail?.visit?.url?.pathname || window.location.pathname || '';
    targetPageType.value = detectPageType(path);
    isPageLoading.value = true;
  });

  router.on('finish', () => {
    if (timer) clearTimeout(timer);
    // Smooth transition
    timer = setTimeout(() => {
      isPageLoading.value = false;
    }, 120);
  });
}

export function usePageLoading() {
  return {
    isPageLoading,
    targetPageType,
  };
}
