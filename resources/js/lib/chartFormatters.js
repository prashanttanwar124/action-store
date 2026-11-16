export const formatMoney = (value) => {
  const num = Number(value || 0);
  const abs = Math.abs(num).toFixed(2);
  const withCommas = abs.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return num < 0 ? `-$${withCommas}` : `$${withCommas}`;
};

export const formatCompactMoney = (value) => {
  const num = Number(value || 0);
  const sign = num < 0 ? '-' : '';
  const abs = Math.abs(num);
  if (abs >= 1000) {
    const k = abs / 1000;
    const formatted = k >= 10 ? k.toFixed(0) : String(Number(k.toFixed(1)));
    return `${sign}$${formatted}k`;
  }
  return `${sign}$${Math.round(abs)}`;
};

export const formatSignedMoney = (value) => {
  const num = Number(value || 0);
  if (Math.abs(num) < 0.005) {
    return '$0.00';
  }
  return `${num > 0 ? '+' : ''}${formatMoney(num)}`;
};

export const escapeHtml = (value) => {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
};

