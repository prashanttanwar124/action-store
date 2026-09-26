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

let isInitialVisit = true;

if (typeof window !== 'undefined') {
  // Smooth initial reveal after first load skeleton
  timer = setTimeout(() => {
    isPageLoading.value = false;
    isInitialVisit = false;
  }, 280);

  // When Inertia starts navigating to a new URL, show top progress indicator
  // (Keep current page intact without premature skeleton)
  router.on('start', () => {
    isNavigating.value = true;
    isInitialVisit = false;
  });

  // When the new page is mounted and navigated to, show skeleton for the incoming page
  router.on('navigate', (event) => {
    if (isInitialVisit) {
      return;
    }

    const path = event?.detail?.page?.url || window.location.pathname || '';
    targetPageType.value = detectPageType(path);
    isPageLoading.value = true;

    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      isPageLoading.value = false;
    }, 240);
  });

  // When navigation completes or cancels, deactivate top indicator
  router.on('finish', () => {
    isNavigating.value = false;
  });

  router.on('cancel', () => {
    isNavigating.value = false;
    isPageLoading.value = false;
  });

  router.on('error', () => {
    isNavigating.value = false;
    isPageLoading.value = false;
  });
}

export function usePageLoading() {
  return {
    isPageLoading,
    isNavigating,
    targetPageType,
  };
}
