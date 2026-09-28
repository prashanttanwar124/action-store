import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

function detectPageType(path) {
  if (!path) return 'home';
  if (path.startsWith('/admin')) return 'admin';
  if (path.startsWith('/products') || path.startsWith('/recipe-kits')) return 'product';
  if (path.startsWith('/cart') || path.startsWith('/checkout')) return 'cart';
  if (path.startsWith('/account') || path.startsWith('/reorder')) return 'account';
  return 'home';
}

// Shared across layouts so remounting does not start another loading state.
const isNavigating = ref(false);
const targetPageType = ref('home');
const activeVisits = new Set();

if (typeof window !== 'undefined') {
  router.on('start', (event) => {
    const visit = event.detail.visit;

    if (visit.async || visit.prefetch) return;

    activeVisits.add(visit);
    const path = typeof visit.url === 'string' ? visit.url : (visit.url?.pathname || '');
    targetPageType.value = detectPageType(path);
    isNavigating.value = true;
  });

  router.on('finish', (event) => {
    const visit = event.detail.visit;

    activeVisits.delete(visit);
    isNavigating.value = activeVisits.size > 0;
  });

  router.on('cancel', (event) => {
    const visit = event.detail.visit;

    activeVisits.delete(visit);
    isNavigating.value = activeVisits.size > 0;
  });
}

export function usePageLoading() {
  return {
    isPageLoading: isNavigating,
    isNavigating,
    targetPageType,
  };
}
