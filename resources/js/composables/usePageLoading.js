import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

function detectPageType(path) {
  if (!path) return 'home';
  if (path.startsWith('/admin')) return 'admin';
  if (path.startsWith('/products')) return 'product';
  if (path.startsWith('/cart') || path.startsWith('/checkout')) return 'cart';
  if (path.startsWith('/account') || path.startsWith('/reorder')) return 'account';
  return 'home';
}

// Global navigation indicator for active network requests
const isNavigating = ref(false);

if (typeof window !== 'undefined') {
  router.on('start', () => {
    isNavigating.value = true;
  });

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
  // Each layout instance starts in loading state on initial render (frame 0)
  // This guarantees the skeleton renders FIRST, preventing any flash of product content.
  const isPageLoading = ref(true);

  let pageUrl = '';
  try {
    const page = usePage();
    pageUrl = page?.url || '';
  } catch (e) {
    pageUrl = typeof window !== 'undefined' ? window.location.pathname : '';
  }

  const targetPageType = ref(detectPageType(pageUrl));

  if (typeof window !== 'undefined') {
    // Smooth transition from skeleton to actual content
    setTimeout(() => {
      isPageLoading.value = false;
    }, 220);
  }

  return {
    isPageLoading,
    isNavigating,
    targetPageType,
  };
}
