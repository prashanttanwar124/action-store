import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

function extractPathname(url) {
  if (!url) return '';
  if (typeof url === 'string') {
    try {
      return new URL(url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost').pathname;
    } catch {
      return url.split('?')[0];
    }
  }
  return url.pathname || '';
}

function normalizeRouteGroup(pathname) {
  const clean = (pathname || '/').replace(/\/+$/, '') || '/';
  // Both /search and /categories/* render the Search & Catalogue page with category filters
  if (clean === '/search' || clean.startsWith('/categories/')) {
    return '/search';
  }
  return clean;
}

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
const isPageLoading = ref(false);
const targetPageType = ref('home');
const activeVisits = new Set();
const fullPageVisits = new Set();

if (typeof window !== 'undefined') {
  router.on('start', (event) => {
    const visit = event.detail.visit;

    if (visit.async || visit.prefetch) return;

    activeVisits.add(visit);
    isNavigating.value = true;

    const method = String(visit.method || 'get').toLowerCase();
    const currentGroup = normalizeRouteGroup(window.location.pathname);
    const targetPath = extractPathname(visit.url);
    const targetGroup = normalizeRouteGroup(targetPath);

    // Never show full-page skeleton for filter changes, search queries, sorting, pagination,
    // partial reloads, or any visit staying on the same page/component.
    const isFilterOrInPlace = Boolean(
      method !== 'get' ||
      currentGroup === targetGroup ||
      visit.preserveScroll ||
      visit.preserveState ||
      (Array.isArray(visit.only) && visit.only.length > 0)
    );

    if (!isFilterOrInPlace) {
      fullPageVisits.add(visit);
      targetPageType.value = detectPageType(targetPath);
      isPageLoading.value = true;
    }
  });

  router.on('finish', (event) => {
    const visit = event.detail.visit;

    activeVisits.delete(visit);
    fullPageVisits.delete(visit);
    isNavigating.value = activeVisits.size > 0;
    isPageLoading.value = fullPageVisits.size > 0;
  });
}

export function usePageLoading() {
  return {
    isPageLoading,
    isNavigating,
    targetPageType,
  };
}

