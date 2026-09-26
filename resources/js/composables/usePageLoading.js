import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const isPageLoading = ref(false);
const targetPageType = ref('auto');
let timer = null;

// Register Inertia router listeners
if (typeof window !== 'undefined') {
  router.on('start', (event) => {
    if (timer) clearTimeout(timer);
    
    // Detect target url
    const path = event?.detail?.visit?.url?.pathname || window.location.pathname || '';
    if (path.startsWith('/admin')) {
      targetPageType.value = 'admin';
    } else if (path.startsWith('/products')) {
      targetPageType.value = 'product';
    } else if (path.startsWith('/cart') || path.startsWith('/checkout')) {
      targetPageType.value = 'cart';
    } else if (path.startsWith('/account') || path.startsWith('/reorder')) {
      targetPageType.value = 'account';
    } else {
      targetPageType.value = 'home';
    }

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
