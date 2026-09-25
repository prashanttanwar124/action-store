/**
 * Modernist Design System Bundle
 * ID: modernist-7555aeb4-b620-4b84-a715-07a153cd6e8f
 */

export const tokens = {
  id: 'modernist-7555aeb4-b620-4b84-a715-07a153cd6e8f',
  name: 'Modernist',
  version: '1.0.0',
  colors: {
    background: '#EBEBEB',
    surface: '#FFFFFF',
    ink: '#18181B',
    inkMuted: '#71717A',
    border: '#D4D4D8',
    subtle: '#EAEAEA',
    primary: '#E52E04',
    primaryHover: '#CC2500',
    primaryDark: '#B82200',
    primaryTint: '#FEF2F0',
    gold: '#EAB308',
  },
  typography: {
    fontSans: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
    fontMono: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
  },
  radii: {
    none: '0px',
    sm: '0px',
    md: '0px',
    lg: '0px',
    full: '0px', // Strict Modernist: 0px everywhere
  },
  spacing: {
    frameWidth: '420px',
    deliveryThreshold: 40.00,
  }
};

/**
 * Format currency with 2 decimals
 */
export function formatCurrency(amount) {
  return `$${Number(amount).toFixed(2)}`;
}

/**
 * Compute progress percentage to free delivery threshold
 */
export function calculateDeliveryProgress(subtotal, threshold = 40.00) {
  const current = Math.min(threshold, Math.max(0, subtotal));
  const percent = Math.min(100, Math.round((current / threshold) * 100));
  const remaining = Math.max(0, threshold - current);
  return {
    percent,
    remaining: Number(remaining.toFixed(2)),
    unlocked: remaining <= 0,
  };
}

// Attach to window for non-module compatibility
if (typeof window !== 'undefined') {
  window.ModernistDS = {
    tokens,
    formatCurrency,
    calculateDeliveryProgress,
  };
}

export default {
  tokens,
  formatCurrency,
  calculateDeliveryProgress,
};
