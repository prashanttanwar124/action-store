<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EChart from '@/Components/Charts/EChart.vue';
import {
  escapeHtml,
  formatCompactMoney,
  formatMoney as money,
  formatSignedMoney as signedMoney,
} from '@/lib/chartFormatters';
import {
  AlertTriangle,
  ArrowDownLeft,
  ArrowRight,
  ArrowUpRight,
  Banknote,
  BarChart3,
  Calendar,
  CheckCircle2,
  Clock,
  CreditCard,
  Download,
  FileText,
  History,
  Lock,
  Printer,
  RotateCcw,
  Search,
  ShieldCheck,
  Store,
  User,
  Wallet,
  X,
} from 'lucide-vue-next';

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  summary: {
    type: Object,
    required: true,
  },
  openShift: {
    type: Object,
    default: null,
  },
  tills: {
    type: Object,
    required: true,
  },
  staffList: {
    type: Array,
    default: () => [],
  },
});

const filterState = reactive({
  preset: props.filters.preset || '30d',
  from: props.filters.from || '',
  to: props.filters.to || '',
  status: props.filters.status || 'all',
  admin_id: props.filters.admin_id ? String(props.filters.admin_id) : '',
  search: props.filters.search || '',
});

watch(
  () => props.filters,
  (next) => {
    filterState.preset = next.preset || '30d';
    filterState.from = next.from || '';
    filterState.to = next.to || '';
    filterState.status = next.status || 'all';
    filterState.admin_id = next.admin_id ? String(next.admin_id) : '';
    filterState.search = next.search || '';
  },
  { deep: true }
);

const presets = [
  { key: 'today', label: 'Today' },
  { key: 'yesterday', label: 'Yesterday' },
  { key: '7d', label: 'Last 7 Days' },
  { key: '30d', label: 'Last 30 Days' },
  { key: 'this_month', label: 'This Month' },
  { key: 'last_month', label: 'Last Month' },
  { key: 'all', label: 'All Time' },
];

const statusTabs = [
  { key: 'all', label: 'All Tills' },
  { key: 'open', label: 'Open Now' },
  { key: 'closed', label: 'Closed' },
  { key: 'variance', label: 'With Variance' },
];

const buildQueryParams = (overrides = {}) => {
  const merged = {
    preset: filterState.preset,
    from: filterState.from,
    to: filterState.to,
    status: filterState.status,
    admin_id: filterState.admin_id || undefined,
    search: filterState.search.trim() || undefined,
    ...overrides,
  };

  if (merged.preset && merged.preset !== 'custom') {
    delete merged.from;
    delete merged.to;
  }

  return Object.fromEntries(
    Object.entries(merged).filter(([, v]) => v !== '' && v !== null && v !== undefined)
  );
};

const navigateWithFilters = (overrides = {}) => {
  router.get(route('admin.tills.index'), buildQueryParams(overrides), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const selectPreset = (presetKey) => {
  filterState.preset = presetKey;
  navigateWithFilters({ preset: presetKey, from: undefined, to: undefined });
};

const applyCustomRange = () => {
  filterState.preset = 'custom';
  navigateWithFilters({
    preset: 'custom',
    from: filterState.from,
    to: filterState.to,
  });
};

const selectStatus = (statusKey) => {
  filterState.status = statusKey;
  navigateWithFilters({ status: statusKey });
};

const applyDropdownOrSearch = () => {
  navigateWithFilters();
};

const resetFilters = () => {
  filterState.preset = '30d';
  filterState.status = 'all';
  filterState.admin_id = '';
  filterState.search = '';
  router.get(route('admin.tills.index'), { preset: '30d', status: 'all' }, { preserveState: false });
};

const hasActiveSecondaryFilters = computed(() => {
  return (
    filterState.status !== 'all' ||
    filterState.admin_id !== '' ||
    filterState.search.trim() !== '' ||
    filterState.preset !== '30d'
  );
});

const exportUrl = computed(() => {
  return route('admin.tills.export', buildQueryParams());
});

const posRegisterHref = computed(() => {
  try {
    if (typeof route === 'function' && route().has('pos.register')) {
      return route('pos.register');
    }
  } catch {
    // fallback
  }
  return '/pos';
});

const tillChartMode = ref('variance'); // 'variance' | 'comparison'

const chartShiftsChronological = computed(() => {
  return [...(props.tills?.data || [])].reverse();
});

const closedShiftsForVarianceChart = computed(() => {
  return chartShiftsChronological.value.filter((s) => s.status === 'closed');
});

const tillChartOption = computed(() => {
  const isVariance = tillChartMode.value === 'variance';
  const rows = isVariance ? closedShiftsForVarianceChart.value : chartShiftsChronological.value;
  const labels = rows.map((s) => `#${s.id} · ${s.opened_date_label || ''}`);

  if (isVariance) {
    const maxAbsVariance = Math.max(0, ...rows.map((s) => Math.abs(Number(s.cash_difference || 0))));
    const allZeroVariance = maxAbsVariance < 0.01;

    return {
      animationDuration: 320,
      tooltip: {
        trigger: 'axis',
        confine: true,
        axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(113, 113, 122, 0.06)' } },
        backgroundColor: '#09090b',
        borderColor: '#27272a',
        borderWidth: 1,
        padding: [10, 12],
        textStyle: { color: '#fafafa', fontSize: 12 },
        formatter: (params) => {
          const item = Array.isArray(params) ? params[0] : params;
          const shift = rows[item?.dataIndex ?? 0];
          if (!shift) {
            return '';
          }
          const diff = Number(shift.cash_difference || 0);
          const diffColor = Math.abs(diff) < 0.01 ? '#6ee7b7' : diff > 0 ? '#7dd3fc' : '#fda4af';
          return `
            <div style="min-width:210px;">
              <div style="font-weight:700;padding-bottom:6px;margin-bottom:6px;border-bottom:1px solid #27272a;">
                Till #${Number(shift.id)} · ${escapeHtml(shift.opened_date_label)}
              </div>
              <div style="display:flex;justify-content:space-between;gap:14px;">
                <span style="color:#a1a1aa;">Closed By</span>
                <span>${escapeHtml(shift.closed_by?.name || 'Staff')}</span>
              </div>
              <div style="display:flex;justify-content:space-between;gap:14px;margin-top:2px;">
                <span style="color:#a1a1aa;">Expected Cash</span>
                <span style="font-family:monospace;">${escapeHtml(money(shift.expected_cash))}</span>
              </div>
              <div style="display:flex;justify-content:space-between;gap:14px;margin-top:2px;">
                <span style="color:#a1a1aa;">Counted Entered</span>
                <span style="font-family:monospace;">${escapeHtml(money(shift.counted_cash))}</span>
              </div>
              <div style="display:flex;justify-content:space-between;gap:14px;padding-top:6px;margin-top:6px;border-top:1px solid #27272a;font-weight:700;color:${diffColor};">
                <span>Cash Variance</span>
                <span style="font-family:monospace;">${escapeHtml(signedMoney(diff))}</span>
              </div>
            </div>
          `;
        },
      },
      legend: {
        top: 0,
        left: 0,
        icon: 'circle',
        itemWidth: 9,
        itemHeight: 9,
        itemGap: 16,
        textStyle: { color: '#52525b', fontSize: 12, fontWeight: 500 },
      },
      grid: { left: 8, right: 12, top: 36, bottom: 6, containLabel: true },
      xAxis: {
        type: 'category',
        data: labels,
        axisLine: { lineStyle: { color: '#e4e4e7' } },
        axisTick: { show: false },
        axisLabel: { color: '#71717a', fontSize: 11, fontFamily: 'monospace', margin: 10 },
      },
      yAxis: {
        type: 'value',
        min: allZeroVariance ? -10 : undefined,
        max: allZeroVariance ? 10 : undefined,
        splitNumber: 4,
        axisLabel: {
          color: '#71717a',
          fontSize: 11,
          fontFamily: 'monospace',
          formatter: (val) => formatCompactMoney(val),
        },
        splitLine: { lineStyle: { type: 'dashed', color: '#f4f4f5' } },
      },
      series: [
        {
          name: 'Over / Short ($)',
          type: 'bar',
          barMaxWidth: 32,
          markLine: {
            silent: true,
            symbol: ['none', 'none'],
            lineStyle: { color: '#a1a1aa', type: 'dashed', width: 1 },
            label: { show: false },
            data: [{ yAxis: 0 }],
          },
          data: rows.map((shift) => {
            const diff = Number(shift.cash_difference || 0);
            const isBalanced = Math.abs(diff) < 0.01;
            const color = diff > 0 ? '#0ea5e9' : '#f43f5e';
            return {
              value: isBalanced ? 0 : diff,
              shiftId: shift.id,
              itemStyle: {
                color,
                borderRadius: diff >= 0 ? [5, 5, 0, 0] : [0, 0, 5, 5],
              },
            };
          }),
        },
        {
          name: 'Exact Balanced ($0.00)',
          type: 'scatter',
          symbolSize: 11,
          itemStyle: {
            color: '#10b981',
            borderColor: '#ffffff',
            borderWidth: 2,
          },
          data: rows.map((shift) => {
            const diff = Number(shift.cash_difference || 0);
            const isBalanced = Math.abs(diff) < 0.01;
            return {
              value: isBalanced ? 0 : null,
              shiftId: shift.id,
            };
          }),
        },
      ],
    };
  }

  return {
    animationDuration: 320,
    tooltip: {
      trigger: 'axis',
      confine: true,
      axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(113, 113, 122, 0.06)' } },
      backgroundColor: '#09090b',
      borderColor: '#27272a',
      borderWidth: 1,
      padding: [10, 12],
      textStyle: { color: '#fafafa', fontSize: 12 },
      formatter: (params) => {
        const list = Array.isArray(params) ? params : [params];
        const shift = rows[list[0]?.dataIndex ?? 0];
        if (!shift) {
          return '';
        }
        return `
          <div style="min-width:210px;">
            <div style="font-weight:700;padding-bottom:6px;margin-bottom:6px;border-bottom:1px solid #27272a;">
              Till #${Number(shift.id)} · ${escapeHtml(shift.opened_by?.name || 'Staff')}
            </div>
            <div style="display:flex;justify-content:space-between;gap:14px;color:#6ee7b7;">
              <span>Expected Cash</span>
              <span style="font-family:monospace;">${escapeHtml(money(shift.expected_cash))}</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:14px;margin-top:2px;color:#7dd3fc;">
              <span>Physical Entered</span>
              <span style="font-family:monospace;">${
                shift.status === 'closed' ? escapeHtml(money(shift.counted_cash)) : 'Open'
              }</span>
            </div>
          </div>
        `;
      },
    },
    legend: {
      top: 0,
      left: 0,
      icon: 'roundRect',
      itemWidth: 10,
      itemHeight: 10,
      itemGap: 18,
      textStyle: { color: '#52525b', fontSize: 12, fontWeight: 500 },
    },
    grid: { left: 8, right: 12, top: 36, bottom: 6, containLabel: true },
    xAxis: {
      type: 'category',
      data: labels,
      axisLine: { lineStyle: { color: '#e4e4e7' } },
      axisTick: { show: false },
      axisLabel: { color: '#71717a', fontSize: 11, fontFamily: 'monospace', margin: 10 },
    },
    yAxis: {
      type: 'value',
      splitNumber: 4,
      axisLabel: {
        color: '#71717a',
        fontSize: 11,
        fontFamily: 'monospace',
        formatter: (val) => formatCompactMoney(val),
      },
      splitLine: { lineStyle: { type: 'dashed', color: '#f4f4f5' } },
    },
    series: [
      {
        name: 'Expected Drawer Cash',
        type: 'bar',
        barMaxWidth: 26,
        itemStyle: { color: '#10b981', borderRadius: [5, 5, 0, 0] },
        data: rows.map((shift) => ({ value: Number(shift.expected_cash || 0), shiftId: shift.id })),
      },
      {
        name: 'Counted Cash Entered',
        type: 'bar',
        barMaxWidth: 26,
        itemStyle: { color: '#0ea5e9', borderRadius: [5, 5, 0, 0] },
        data: rows.map((shift) => ({
          value: shift.status === 'closed' ? Number(shift.counted_cash || 0) : 0,
          shiftId: shift.id,
        })),
      },
    ],
  };
});

const handleTillChartClick = (params) => {
  const shiftId = params?.data?.shiftId;
  if (!shiftId) {
    return;
  }
  const found = (props.tills?.data || []).find((s) => s.id === shiftId);
  if (found) {
    openTillDrawer(found);
  }
};

const varianceBadgeClass = (diff) => {
  if (diff === null || diff === undefined) {
    return 'bg-zinc-100 text-zinc-600 border-zinc-200';
  }
  const n = Number(diff);
  if (Math.abs(n) < 0.01) {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  }
  return n > 0
    ? 'bg-sky-50 text-sky-700 border-sky-200'
    : 'bg-rose-50 text-rose-700 border-rose-200';
};

const varianceLabel = (diff) => {
  if (diff === null || diff === undefined) {
    return 'Not checked';
  }
  const n = Number(diff);
  if (Math.abs(n) < 0.01) {
    return 'Exact Match';
  }
  return n > 0 ? `Over ${signedMoney(n)}` : `Short ${signedMoney(n)}`;
};

const hasShiftVariance = (shift) => {
  if (!shift || shift.status !== 'closed') {
    return false;
  }
  const cashDiff = Math.abs(Number(shift.cash_difference || 0));
  const termDiff =
    shift.counted_terminal !== null && shift.counted_terminal !== undefined
      ? Math.abs(Number(shift.terminal_difference || 0))
      : 0;
  return cashDiff >= 0.01 || termDiff >= 0.01;
};

const paymentBadgeClass = (method) => {
  switch (method) {
    case 'cash':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    case 'terminal':
    case 'card':
      return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    case 'split':
      return 'bg-amber-50 text-amber-800 border-amber-200';
    default:
      return 'bg-zinc-100 text-zinc-700 border-zinc-200';
  }
};

// Slide-Over Till Timeline & Z-Report Drawer State
const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerError = ref('');
const drawerTab = ref('timeline'); // 'timeline' | 'orders'
const selectedTill = ref(null);
const selectedTimeline = ref([]);
const selectedOrders = ref([]);

const openTillDrawer = async (shift) => {
  if (!shift?.id) {
    return;
  }
  drawerOpen.value = true;
  drawerLoading.value = true;
  drawerError.value = '';
  drawerTab.value = 'timeline';
  selectedTill.value = shift;
  selectedTimeline.value = [];
  selectedOrders.value = [];

  try {
    const response = await fetch(route('admin.tills.show', shift.id), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    selectedTill.value = payload.till;
    selectedTimeline.value = payload.timeline || [];
    selectedOrders.value = payload.orders || [];
  } catch (error) {
    drawerError.value = 'Unable to load full till activity log. Please try again.';
  } finally {
    drawerLoading.value = false;
  }
};

const closeDrawer = () => {
  drawerOpen.value = false;
};

// Manager Force Close Modal State
const closeModalOpen = ref(false);
const closingTarget = ref(null);
const closeForm = reactive({
  counted_cash: '',
  counted_terminal: '',
  notes: '',
});
const closeErrors = ref({});
const closeSubmitting = ref(false);

const openForceCloseModal = (shift) => {
  if (!shift || shift.status !== 'open') {
    return;
  }
  closingTarget.value = shift;
  closeForm.counted_cash = '';
  closeForm.counted_terminal = '';
  closeForm.notes = '';
  closeErrors.value = {};
  closeModalOpen.value = true;
};

const fillExpectedCash = () => {
  if (!closingTarget.value) {
    return;
  }
  closeForm.counted_cash = Number(closingTarget.value.expected_cash || 0).toFixed(2);
};

const fillExpectedTerminal = () => {
  if (!closingTarget.value) {
    return;
  }
  closeForm.counted_terminal = Number(closingTarget.value.net_terminal || 0).toFixed(2);
};

const previewCashVariance = computed(() => {
  if (!closingTarget.value || closeForm.counted_cash === '') {
    return null;
  }
  const entered = Number(closeForm.counted_cash);
  if (Number.isNaN(entered)) {
    return null;
  }
  return Number((entered - Number(closingTarget.value.expected_cash || 0)).toFixed(2));
});

const previewTerminalVariance = computed(() => {
  if (!closingTarget.value || closeForm.counted_terminal === '') {
    return null;
  }
  const entered = Number(closeForm.counted_terminal);
  if (Number.isNaN(entered)) {
    return null;
  }
  return Number((entered - Number(closingTarget.value.net_terminal || 0)).toFixed(2));
});

const submitForceClose = () => {
  if (!closingTarget.value || closeSubmitting.value) {
    return;
  }

  closeSubmitting.value = true;
  closeErrors.value = {};

  router.post(
    route('admin.tills.close', closingTarget.value.id),
    {
      counted_cash: closeForm.counted_cash,
      counted_terminal: closeForm.counted_terminal === '' ? null : closeForm.counted_terminal,
      notes: closeForm.notes,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        const closedId = closingTarget.value?.id;
        closeModalOpen.value = false;
        closingTarget.value = null;
        if (drawerOpen.value && selectedTill.value?.id === closedId) {
          openTillDrawer({ id: closedId });
        }
      },
      onError: (errors) => {
        closeErrors.value = errors || {};
      },
      onFinish: () => {
        closeSubmitting.value = false;
      },
    }
  );
};

const printZReport = () => {
  if (!selectedTill.value) {
    return;
  }
  const till = selectedTill.value;
  const eventsHtml = (selectedTimeline.value || [])
    .map(
      (ev) => `
        <tr>
          <td style="padding:4px 0;color:#52525b;font-size:11px;white-space:nowrap;">${escapeHtml(ev.time_formatted || '')}</td>
          <td style="padding:4px 8px;font-size:11px;">
            <strong>${escapeHtml(ev.title)}</strong><br/>
            <span style="color:#71717a;">By ${escapeHtml(ev.actor_name)}${ev.subtitle ? ' · ' + escapeHtml(ev.subtitle) : ''}</span>
          </td>
          <td style="padding:4px 0;text-align:right;font-family:monospace;font-size:11px;white-space:nowrap;">${escapeHtml(money(ev.amount))}</td>
        </tr>
      `
    )
    .join('');

  const html = `<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Till #${escapeHtml(till.id)} Z-Report</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #18181b; margin: 24px; max-width: 680px; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .meta { font-size: 12px; color: #52525b; margin-bottom: 16px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .box { border: 1px solid #e4e4e7; border-radius: 8px; padding: 12px; }
    .box h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #52525b; margin: 0 0 8px; }
    .row { display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; }
    .row.total { border-top: 1px solid #e4e4e7; margin-top: 6px; padding-top: 6px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { text-align: left; font-size: 11px; color: #71717a; border-bottom: 1px solid #e4e4e7; padding-bottom: 4px; }
  </style>
</head>
<body>
  <h1>Masala Mart · Till #${escapeHtml(till.id)} Shift Audit Report</h1>
  <div class="meta">
    Status: <strong>${escapeHtml(String(till.status || '').toUpperCase())}</strong> · Duration: ${escapeHtml(till.duration_label || 'Active')}<br/>
    Opened: ${escapeHtml(till.opened_at_formatted)} by <strong>${escapeHtml(till.opened_by?.name || 'Staff')}</strong><br/>
    ${
      till.status === 'closed'
        ? `Closed: ${escapeHtml(till.closed_at_formatted)} by <strong>${escapeHtml(till.closed_by?.name || 'Staff')}</strong>`
        : 'Till currently active'
    }
  </div>
  <div class="grid">
    <div class="box">
      <h2>Cash Drawer Reconciliation</h2>
      <div class="row"><span>Opening Float Entered</span><span>${escapeHtml(money(till.opening_cash))}</span></div>
      <div class="row"><span>+ Cash Sales</span><span>${escapeHtml(money(till.cash_sales))}</span></div>
      <div class="row"><span>+ Coins / Cash Deposits</span><span>${escapeHtml(money(till.cash_deposits))}</span></div>
      <div class="row"><span>- Cash Refunds</span><span>-${escapeHtml(money(till.cash_refunds))}</span></div>
      <div class="row"><span>- Safe Drops / Withdrawals</span><span>-${escapeHtml(money(till.cash_withdrawals))}</span></div>
      <div class="row total"><span>Expected Drawer Cash</span><span>${escapeHtml(money(till.expected_cash))}</span></div>
      ${
        till.status === 'closed'
          ? `<div class="row"><span>Physical Cash Counted</span><span>${escapeHtml(money(till.counted_cash))}</span></div>
             <div class="row total"><span>Cash Variance</span><span>${escapeHtml(signedMoney(till.cash_difference))}</span></div>`
          : ''
      }
    </div>
    <div class="box">
      <h2>Card Terminal & Orders</h2>
      <div class="row"><span>Card Terminal Sales</span><span>${escapeHtml(money(till.terminal_sales))}</span></div>
      <div class="row"><span>- Card Refunds</span><span>-${escapeHtml(money(till.terminal_refunds))}</span></div>
      <div class="row total"><span>Net Terminal Expected</span><span>${escapeHtml(money(till.net_terminal))}</span></div>
      ${
        till.counted_terminal !== null && till.counted_terminal !== undefined
          ? `<div class="row"><span>Terminal Batch Slip Entered</span><span>${escapeHtml(money(till.counted_terminal))}</span></div>
             <div class="row total"><span>Terminal Variance</span><span>${escapeHtml(signedMoney(till.terminal_difference))}</span></div>`
          : '<div class="row"><span>Terminal Batch Slip</span><span>Not entered</span></div>'
      }
      <div class="row total"><span>Total Net Sales (Cash + Card)</span><span>${escapeHtml(money(till.net_total_sales))}</span></div>
      <div class="row"><span>Orders Count (Split)</span><span>${escapeHtml(till.orders_count)} (${escapeHtml(till.split_orders_count || 0)})</span></div>
    </div>
  </div>
  ${till.notes ? `<div class="box" style="margin-bottom:16px;"><h2>Shift Notes</h2><div style="font-size:12px;">${escapeHtml(till.notes)}</div></div>` : ''}
  <div class="box">
    <h2>Chronological Event Log</h2>
    <table>
      <thead><tr><th>Time</th><th>Event</th><th style="text-align:right;">Amount</th></tr></thead>
      <tbody>${eventsHtml}</tbody>
    </table>
  </div>
</body>
</html>`;

  const iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  const doc = iframe.contentDocument || iframe.contentWindow?.document;
  if (!doc) {
    document.body.removeChild(iframe);
    return;
  }
  doc.open();
  doc.write(html);
  doc.close();

  setTimeout(() => {
    iframe.contentWindow?.focus();
    iframe.contentWindow?.print();
    setTimeout(() => {
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    }, 1000);
  }, 150);
};
</script>

<template>
  <AdminLayout title="Till & Shift Logs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-medium text-zinc-500">
            <Link :href="route('admin.dashboard')" class="hover:text-zinc-800 transition-colors">
              Dashboard
            </Link>
            <span>/</span>
            <Link :href="route('admin.analytics.index')" class="hover:text-zinc-800 transition-colors">
              Analytics &amp; Payments
            </Link>
            <span>/</span>
            <span class="text-zinc-800 font-semibold">Till &amp; Shift Logs</span>
          </div>
          <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900">
            Till &amp; Shift Audit Console
          </h1>
          <p class="mt-1 text-sm text-zinc-500">
            Verify exact till open/close timestamps, cash floats entered by staff, mid-shift coin deposits, safe drops, and card terminal batch slips.
            <span class="font-mono text-xs text-zinc-400 ml-1">({{ filters.timezone }})</span>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <Link
            :href="route('admin.analytics.index')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-colors shadow-2xs"
          >
            <BarChart3 class="w-3.5 h-3.5 text-emerald-600" />
            <span>Payment Analytics</span>
          </Link>

          <a
            :href="posRegisterHref"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-colors shadow-2xs"
          >
            <Store class="w-3.5 h-3.5 text-zinc-500" />
            <span>POS Register</span>
            <ArrowUpRight class="w-3.5 h-3.5 text-zinc-400" />
          </a>

          <a
            :href="exportUrl"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-white bg-zinc-900 hover:bg-zinc-800 transition-colors shadow-2xs"
          >
            <Download class="w-3.5 h-3.5" />
            <span>Export Till CSV</span>
          </a>
        </div>
      </div>

      <!-- Live Open Till Spotlight Banner -->
      <section
        v-if="openShift"
        class="bg-white border-2 border-emerald-200 rounded-xl shadow-xs overflow-hidden"
      >
        <div class="bg-emerald-50/80 border-b border-emerald-200/80 px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2.5">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75" />
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-600" />
            </span>
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">
                Currently Open Till · #{{ openShift.id }}
              </span>
              <p class="text-xs text-emerald-700 mt-0.5">
                Opened <strong>{{ openShift.opened_date_label }}</strong> at <strong>{{ openShift.opened_time_label }}</strong>
                by <strong>{{ openShift.opened_by?.name || 'Staff' }}</strong>
                <span class="mx-1.5 text-emerald-400">•</span>
                Running {{ openShift.duration_label }}
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              @click="openTillDrawer(openShift)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-900 bg-white border border-emerald-300 hover:bg-emerald-50 transition-colors"
            >
              <History class="w-3.5 h-3.5 text-emerald-600" />
              <span>Inspect Live Timeline</span>
            </button>
            <button
              type="button"
              @click="openForceCloseModal(openShift)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 transition-colors shadow-2xs"
            >
              <Lock class="w-3.5 h-3.5" />
              <span>Manager Close Till</span>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-zinc-100 px-5 py-3.5 gap-y-3">
          <div class="lg:pr-4">
            <div class="text-[11px] font-medium text-zinc-500">Opening Float Entered</div>
            <div class="text-lg font-bold font-mono tabular-nums text-zinc-900 mt-0.5">
              {{ money(openShift.opening_cash) }}
            </div>
            <div class="text-[11px] text-zinc-500">By {{ openShift.opened_by?.name || 'Staff' }}</div>
          </div>

          <div class="lg:px-4">
            <div class="text-[11px] font-medium text-zinc-500">Net Cash Sales</div>
            <div class="text-lg font-bold font-mono tabular-nums text-zinc-900 mt-0.5">
              {{ money(openShift.net_cash_sales) }}
            </div>
            <div class="text-[11px] text-zinc-500 font-mono tabular-nums">
              Gross {{ money(openShift.cash_sales) }} · Refunds -{{ money(openShift.cash_refunds) }}
            </div>
          </div>

          <div class="lg:px-4">
            <div class="text-[11px] font-medium text-zinc-500">Coins / Safe Drops</div>
            <div class="text-sm font-bold font-mono tabular-nums text-zinc-900 mt-1 flex items-center gap-2">
              <span class="text-emerald-700">+{{ money(openShift.cash_deposits) }}</span>
              <span class="text-zinc-300">/</span>
              <span class="text-amber-700">-{{ money(openShift.cash_withdrawals) }}</span>
            </div>
            <div class="text-[11px] text-zinc-500 mt-0.5">
              {{ openShift.deposits_count || 0 }} deposits · {{ openShift.withdrawals_count || 0 }} drops
            </div>
          </div>

          <div class="lg:px-4">
            <div class="text-[11px] font-medium text-zinc-500">Expected Drawer Cash</div>
            <div class="text-lg font-bold font-mono tabular-nums text-emerald-700 mt-0.5">
              {{ money(openShift.expected_cash) }}
            </div>
            <div class="text-[11px] text-zinc-500">Live calculated drawer</div>
          </div>

          <div class="lg:pl-4 col-span-2 lg:col-span-1">
            <div class="text-[11px] font-medium text-zinc-500">POS Card Terminal (Net)</div>
            <div class="text-lg font-bold font-mono tabular-nums text-indigo-700 mt-0.5">
              {{ money(openShift.net_terminal) }}
            </div>
            <div class="text-[11px] text-zinc-500">
              {{ openShift.orders_count }} orders rung so far
            </div>
          </div>
        </div>
      </section>

      <!-- Filter Bar Card -->
      <section class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs space-y-3.5">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3">
          <!-- Date Presets -->
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="preset in presets"
              :key="preset.key"
              type="button"
              @click="selectPreset(preset.key)"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                filterState.preset === preset.key
                  ? 'bg-zinc-900 text-white border-zinc-900 shadow-2xs'
                  : 'bg-zinc-50 text-zinc-700 border-zinc-200 hover:bg-zinc-100',
              ]"
            >
              {{ preset.label }}
            </button>
          </div>

          <!-- Custom From / To Dates -->
          <form @submit.prevent="applyCustomRange" class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1.5 bg-zinc-50 border border-zinc-200 rounded-lg px-2.5 py-1">
              <Calendar class="w-3.5 h-3.5 text-zinc-400" />
              <label class="text-[11px] font-medium text-zinc-500">From</label>
              <input
                v-model="filterState.from"
                type="date"
                class="bg-transparent border-0 text-xs font-mono text-zinc-800 focus:ring-0 p-0"
              />
            </div>
            <span class="text-xs text-zinc-400">to</span>
            <div class="flex items-center gap-1.5 bg-zinc-50 border border-zinc-200 rounded-lg px-2.5 py-1">
              <label class="text-[11px] font-medium text-zinc-500">To</label>
              <input
                v-model="filterState.to"
                type="date"
                class="bg-transparent border-0 text-xs font-mono text-zinc-800 focus:ring-0 p-0"
              />
            </div>
            <button
              type="submit"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
            >
              Apply
            </button>
          </form>
        </div>

        <div class="pt-3 border-t border-zinc-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <!-- Status Filter Pills -->
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="tab in statusTabs"
              :key="tab.key"
              type="button"
              @click="selectStatus(tab.key)"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors inline-flex items-center gap-1.5 border',
                filterState.status === tab.key
                  ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                  : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-50',
              ]"
            >
              <span>{{ tab.label }}</span>
              <span
                v-if="tab.key === 'variance' && summary.variance_tills > 0"
                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700"
              >
                {{ summary.variance_tills }}
              </span>
            </button>
          </div>

          <!-- Staff Filter + Search -->
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
              <User class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <select
                v-model="filterState.admin_id"
                @change="applyDropdownOrSearch"
                class="pl-8 pr-7 py-1.5 rounded-lg border border-zinc-200 bg-white text-xs font-medium text-zinc-700 focus:border-emerald-500 focus:ring-emerald-500"
              >
                <option value="">All Staff Members</option>
                <option v-for="staff in staffList" :key="staff.id" :value="String(staff.id)">
                  {{ staff.name }}
                </option>
              </select>
            </div>

            <form @submit.prevent="applyDropdownOrSearch" class="relative flex items-center">
              <Search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 pointer-events-none" />
              <input
                v-model="filterState.search"
                type="text"
                placeholder="Till #, staff, or note..."
                class="pl-8 pr-3 py-1.5 w-52 rounded-lg border border-zinc-200 text-xs text-zinc-800 placeholder-zinc-400 focus:border-emerald-500 focus:ring-emerald-500"
              />
            </form>

            <button
              v-if="hasActiveSecondaryFilters"
              type="button"
              @click="resetFilters"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-zinc-600 hover:text-zinc-900 bg-zinc-100 hover:bg-zinc-200/70 transition-colors"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              <span>Reset</span>
            </button>
          </div>
        </div>
      </section>

      <!-- 5 Summary KPI Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3.5">
        <div class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs">
          <div class="flex items-center justify-between text-xs font-medium text-zinc-500">
            <span>Tills Audited</span>
            <Wallet class="w-4 h-4 text-zinc-400" />
          </div>
          <div class="mt-2 text-2xl font-bold font-mono tabular-nums text-zinc-900">
            {{ summary.total_tills }}
          </div>
          <div class="mt-1 text-xs text-zinc-500">
            {{ summary.open_tills }} open · {{ summary.closed_tills }} closed
          </div>
        </div>

        <div class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs">
          <div class="flex items-center justify-between text-xs font-medium text-zinc-500">
            <span>Opening Floats</span>
            <Banknote class="w-4 h-4 text-emerald-600" />
          </div>
          <div class="mt-2 text-2xl font-bold font-mono tabular-nums text-zinc-900">
            {{ money(summary.opening_cash_total) }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 font-mono tabular-nums">
            Expected end: {{ money(summary.expected_cash_total) }}
          </div>
        </div>

        <div class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs">
          <div class="flex items-center justify-between text-xs font-medium text-zinc-500">
            <span>Drawer Adjustments</span>
            <ArrowDownLeft class="w-4 h-4 text-amber-600" />
          </div>
          <div class="mt-2 flex items-baseline gap-2 font-mono tabular-nums">
            <span class="text-lg font-bold text-emerald-700">+{{ money(summary.cash_deposits_total) }}</span>
            <span class="text-zinc-300">/</span>
            <span class="text-lg font-bold text-amber-700">-{{ money(summary.cash_withdrawals_total) }}</span>
          </div>
          <div class="mt-1 text-xs text-zinc-500">
            Coins/float in vs Safe drops
          </div>
        </div>

        <div class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs">
          <div class="flex items-center justify-between text-xs font-medium text-zinc-500">
            <span>Cash Count Variance</span>
            <AlertTriangle
              :class="Math.abs(summary.cash_variance_total) >= 0.01 ? 'text-rose-500' : 'text-emerald-600'"
              class="w-4 h-4"
            />
          </div>
          <div
            :class="[
              'mt-2 text-2xl font-bold font-mono tabular-nums',
              Math.abs(summary.cash_variance_total) < 0.01
                ? 'text-emerald-700'
                : summary.cash_variance_total > 0
                  ? 'text-sky-700'
                  : 'text-rose-700',
            ]"
          >
            {{ signedMoney(summary.cash_variance_total) }}
          </div>
          <div class="mt-1 text-xs text-zinc-500">
            {{ summary.variance_tills }} flagged shifts in range
          </div>
        </div>

        <div class="bg-white border border-zinc-200 rounded-xl p-4 shadow-2xs">
          <div class="flex items-center justify-between text-xs font-medium text-zinc-500">
            <span>POS Card Batch Net</span>
            <CreditCard class="w-4 h-4 text-indigo-600" />
          </div>
          <div class="mt-2 text-2xl font-bold font-mono tabular-nums text-zinc-900">
            {{ money(summary.net_terminal_total) }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 font-mono tabular-nums">
            {{ summary.batch_checked_tills }} batch slips · Diff {{ signedMoney(summary.terminal_variance_total) }}
          </div>
        </div>
      </section>

      <!-- Compact Cash Variance & Expected vs Entered Chart -->
      <section
        v-if="tills.data.length > 0"
        class="bg-white border border-zinc-200 rounded-xl p-5 shadow-2xs space-y-3"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-bold text-zinc-900">
              {{ tillChartMode === 'variance' ? 'Till Cash Variance (Over / Short)' : 'Expected Drawer Cash vs Physical Count Entered' }}
            </h2>
            <p class="text-xs text-zinc-500">
              Click any bar to open that till's chronological Open / Adjustments / Close timeline.
            </p>
          </div>

          <div class="inline-flex items-center gap-1 bg-zinc-100 p-1 rounded-xl border border-zinc-200 self-start">
            <button
              type="button"
              @click="tillChartMode = 'variance'"
              class="px-3 py-1 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer"
              :class="
                tillChartMode === 'variance'
                  ? 'bg-white text-zinc-950 shadow-2xs'
                  : 'text-zinc-600 hover:text-zinc-900'
              "
            >
              Cash Variance ($ Diff)
            </button>
            <button
              type="button"
              @click="tillChartMode = 'comparison'"
              class="px-3 py-1 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer"
              :class="
                tillChartMode === 'comparison'
                  ? 'bg-white text-zinc-950 shadow-2xs'
                  : 'text-zinc-600 hover:text-zinc-900'
              "
            >
              Expected vs Counted
            </button>
          </div>
        </div>

        <EChart
          v-if="tillChartMode === 'comparison' || closedShiftsForVarianceChart.length > 0"
          :option="tillChartOption"
          height-class="h-60"
          @chart-click="handleTillChartClick"
        />

        <div v-else class="py-8 text-center text-xs text-zinc-500 border border-dashed border-zinc-200 rounded-xl">
          No closed tills in the current view yet — switch to <button type="button" @click="tillChartMode = 'comparison'" class="font-semibold text-emerald-700 underline">Expected vs Counted</button> to view open till drawer totals.
        </div>
      </section>

      <!-- Till & Shift Audit Table -->
      <section class="bg-white border border-zinc-200 rounded-xl shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-zinc-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-zinc-50/70">
          <div>
            <h2 class="text-sm font-semibold text-zinc-900">
              Shift Open / Close &amp; Cash Entry Audit Log
            </h2>
            <p class="text-xs text-zinc-500">
              Showing {{ filters.from }} to {{ filters.to }} · Click any row to inspect the full timeline, drawer deposits/drops, and orders.
            </p>
          </div>
          <div class="text-xs font-medium text-zinc-500 font-mono tabular-nums">
            {{ tills.total }} total {{ tills.total === 1 ? 'till' : 'tills' }}
          </div>
        </div>

        <div v-if="tills.data.length === 0" class="px-6 py-14 text-center">
          <Wallet class="w-10 h-10 text-zinc-300 mx-auto" />
          <h3 class="mt-3 text-sm font-semibold text-zinc-800">No till shifts match these filters</h3>
          <p class="mt-1 text-xs text-zinc-500 max-w-md mx-auto">
            Try expanding your date range, clearing staff/search filters, or switching the status tab.
          </p>
          <button
            v-if="hasActiveSecondaryFilters"
            type="button"
            @click="resetFilters"
            class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition-colors"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span>Reset All Filters</span>
          </button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-zinc-200 text-left">
            <thead class="bg-zinc-50/80 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
              <tr>
                <th class="px-4 py-3">Till # / Status</th>
                <th class="px-4 py-3">Till Opened (Time, Staff &amp; Float)</th>
                <th class="px-4 py-3">Mid-Shift Drawer (+/-)</th>
                <th class="px-4 py-3">POS Sales &amp; Expected Cash</th>
                <th class="px-4 py-3">Till Closed (Time, Staff &amp; Count Entered)</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 text-xs">
              <tr
                v-for="shift in tills.data"
                :key="shift.id"
                @click="openTillDrawer(shift)"
                class="hover:bg-zinc-50/90 transition-colors cursor-pointer group"
              >
                <!-- Till # & Status -->
                <td class="px-4 py-3.5 align-top whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-sm text-zinc-900">#{{ shift.id }}</span>
                    <span
                      v-if="shift.status === 'open'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                      OPEN
                    </span>
                    <span
                      v-else-if="hasShiftVariance(shift)"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                    >
                      <AlertTriangle class="w-3 h-3" />
                      VARIANCE
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-700 border border-zinc-200"
                    >
                      <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                      BALANCED
                    </span>
                  </div>
                  <div class="mt-1.5 flex items-center gap-1 text-[11px] text-zinc-500">
                    <Clock class="w-3 h-3 text-zinc-400" />
                    <span>{{ shift.duration_label || 'Active' }}</span>
                  </div>
                  <div class="mt-0.5 text-[11px] text-zinc-500 font-mono tabular-nums">
                    {{ shift.orders_count }} orders<span v-if="shift.split_orders_count"> · {{ shift.split_orders_count }} split</span>
                  </div>
                </td>

                <!-- Opened By, Timestamp & Float -->
                <td class="px-4 py-3.5 align-top">
                  <div class="font-semibold text-zinc-900">
                    {{ shift.opened_by?.name || 'Staff' }}
                  </div>
                  <div class="text-[11px] text-zinc-500 mt-0.5">
                    {{ shift.opened_date_label }} · <span class="font-mono text-zinc-700">{{ shift.opened_time_label }}</span>
                  </div>
                  <div class="mt-1.5 inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-50/70 border border-emerald-200/70 text-[11px]">
                    <span class="text-emerald-700 font-medium">Float:</span>
                    <span class="font-mono font-bold text-emerald-900 tabular-nums">{{ money(shift.opening_cash) }}</span>
                  </div>
                </td>

                <!-- Mid-Shift Coins / Safe Drops -->
                <td class="px-4 py-3.5 align-top whitespace-nowrap">
                  <div v-if="(shift.movements_count || 0) > 0" class="space-y-1 font-mono tabular-nums">
                    <div v-if="shift.cash_deposits > 0" class="text-emerald-700 font-semibold">
                      +{{ money(shift.cash_deposits) }}
                      <span class="font-sans font-normal text-[11px] text-zinc-500">({{ shift.deposits_count }}x in)</span>
                    </div>
                    <div v-if="shift.cash_withdrawals > 0" class="text-amber-700 font-semibold">
                      -{{ money(shift.cash_withdrawals) }}
                      <span class="font-sans font-normal text-[11px] text-zinc-500">({{ shift.withdrawals_count }}x drop)</span>
                    </div>
                  </div>
                  <div v-else class="text-[11px] text-zinc-400">
                    No cash adj.
                  </div>
                </td>

                <!-- POS Sales & Expected Cash -->
                <td class="px-4 py-3.5 align-top whitespace-nowrap">
                  <div class="font-mono tabular-nums space-y-0.5">
                    <div class="flex items-center justify-between gap-4">
                      <span class="font-sans text-[11px] text-zinc-500">Expected Cash:</span>
                      <span class="font-bold text-zinc-900">{{ money(shift.expected_cash) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 text-[11px]">
                      <span class="font-sans text-zinc-500">Cash Sales (Net):</span>
                      <span class="text-emerald-700 font-semibold">{{ money(shift.net_cash_sales) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 text-[11px]">
                      <span class="font-sans text-zinc-500">Card Net:</span>
                      <span class="text-indigo-700 font-semibold">{{ money(shift.net_terminal) }}</span>
                    </div>
                    <div
                      v-if="shift.total_refunds > 0"
                      class="flex items-center justify-between gap-4 text-[11px] text-rose-600"
                    >
                      <span class="font-sans">Refunds:</span>
                      <span>-{{ money(shift.total_refunds) }}</span>
                    </div>
                  </div>
                </td>

                <!-- Till Closed (Time, Staff, Counted Cash & Card Batch Entered) -->
                <td class="px-4 py-3.5 align-top">
                  <div v-if="shift.status === 'open'" class="space-y-1">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                      Till currently active
                    </span>
                    <div class="text-[11px] text-zinc-500">Awaiting close count</div>
                  </div>
                  <div v-else class="space-y-1">
                    <div class="flex items-center gap-1.5">
                      <span class="font-semibold text-zinc-900">{{ shift.closed_by?.name || 'Staff' }}</span>
                      <span class="text-[11px] text-zinc-500">
                        · {{ shift.closed_date_label }} <span class="font-mono text-zinc-700">{{ shift.closed_time_label }}</span>
                      </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 font-mono tabular-nums text-[11px]">
                      <span class="text-zinc-600 font-sans">Cash Entered:</span>
                      <span class="font-bold text-zinc-900">{{ money(shift.counted_cash) }}</span>
                      <span
                        :class="varianceBadgeClass(shift.cash_difference)"
                        class="px-1.5 py-0.5 rounded border text-[10px] font-semibold font-sans"
                      >
                        {{ varianceLabel(shift.cash_difference) }}
                      </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 font-mono tabular-nums text-[11px]">
                      <span class="text-zinc-600 font-sans">Card Batch:</span>
                      <template v-if="shift.counted_terminal !== null && shift.counted_terminal !== undefined">
                        <span class="font-bold text-indigo-900">{{ money(shift.counted_terminal) }}</span>
                        <span
                          :class="varianceBadgeClass(shift.terminal_difference)"
                          class="px-1.5 py-0.5 rounded border text-[10px] font-semibold font-sans"
                        >
                          {{ varianceLabel(shift.terminal_difference) }}
                        </span>
                      </template>
                      <span v-else class="text-zinc-400 font-sans">Not entered</span>
                    </div>
                  </div>
                </td>

                <!-- Actions -->
                <td class="px-4 py-3.5 align-top text-right whitespace-nowrap" @click.stop>
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openTillDrawer(shift)"
                      class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-100 transition-colors"
                    >
                      <FileText class="w-3.5 h-3.5 text-zinc-500" />
                      <span>Timeline</span>
                    </button>

                    <button
                      v-if="shift.status === 'open'"
                      type="button"
                      @click="openForceCloseModal(shift)"
                      class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors"
                    >
                      <Lock class="w-3 h-3" />
                      <span>Close</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="tills.last_page > 1"
          class="px-5 py-3 border-t border-zinc-200 bg-zinc-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
          <div class="text-xs text-zinc-500">
            Showing <span class="font-semibold text-zinc-800">{{ tills.from }}</span> to
            <span class="font-semibold text-zinc-800">{{ tills.to }}</span> of
            <span class="font-semibold text-zinc-800">{{ tills.total }}</span> shifts
          </div>
          <div class="flex flex-wrap items-center gap-1">
            <template v-for="(link, idx) in tills.links" :key="idx">
              <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
                :class="[
                  'px-2.5 py-1 rounded-md text-xs font-medium border transition-colors',
                  link.active
                    ? 'bg-zinc-900 text-white border-zinc-900'
                    : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-100',
                ]"
                v-html="link.label"
              />
              <span
                v-else
                class="px-2.5 py-1 rounded-md text-xs text-zinc-400 border border-transparent"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </section>
    </div>

    <!-- Slide-Over Chronological Till Audit & Z-Report Drawer -->
    <div v-if="drawerOpen" class="fixed inset-0 z-50 flex justify-end">
      <div class="fixed inset-0 bg-zinc-950/45 backdrop-blur-[1px]" @click="closeDrawer" />

      <div class="relative w-full max-w-2xl bg-white h-full shadow-2xl border-l border-zinc-200 flex flex-col z-10">
        <!-- Drawer Header -->
        <div class="px-6 py-4 border-b border-zinc-200 flex items-start justify-between gap-4 bg-zinc-50/80">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-bold text-zinc-900 font-mono">
                Till #{{ selectedTill?.id }}
              </h2>
              <span
                v-if="selectedTill?.status === 'open'"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                OPEN
              </span>
              <span
                v-else-if="selectedTill"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-zinc-200 text-zinc-800"
              >
                CLOSED
              </span>
              <span v-if="selectedTill?.duration_label" class="text-xs text-zinc-500 font-medium">
                · {{ selectedTill.duration_label }}
              </span>
            </div>

            <div v-if="selectedTill" class="mt-1 text-xs text-zinc-600 space-y-0.5">
              <div>
                Opened <strong>{{ selectedTill.opened_at_formatted }}</strong> by
                <strong>{{ selectedTill.opened_by?.name || 'Staff' }}</strong>
              </div>
              <div v-if="selectedTill.status === 'closed'">
                Closed <strong>{{ selectedTill.closed_at_formatted }}</strong> by
                <strong>{{ selectedTill.closed_by?.name || 'Staff' }}</strong>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <button
              v-if="selectedTill?.status === 'open'"
              type="button"
              @click="openForceCloseModal(selectedTill)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors"
            >
              <Lock class="w-3.5 h-3.5" />
              <span>Force Close</span>
            </button>

            <button
              v-if="selectedTill"
              type="button"
              @click="printZReport"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-100 transition-colors"
            >
              <Printer class="w-3.5 h-3.5 text-zinc-500" />
              <span>Z-Report</span>
            </button>

            <button
              type="button"
              @click="closeDrawer"
              class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200/60 transition-colors"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Drawer Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div v-if="drawerLoading" class="space-y-4 animate-pulse">
            <div class="grid grid-cols-2 gap-4">
              <div class="h-40 bg-zinc-100 rounded-xl" />
              <div class="h-40 bg-zinc-100 rounded-xl" />
            </div>
            <div class="h-56 bg-zinc-100 rounded-xl" />
          </div>

          <div v-else-if="drawerError" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ drawerError }}
          </div>

          <template v-else-if="selectedTill">
            <!-- Cash Drawer & Card Terminal Reconciliation Scorecard -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="border border-zinc-200 rounded-xl p-4 bg-zinc-50/50 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-zinc-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <Banknote class="w-3.5 h-3.5 text-emerald-600" />
                    Cash Drawer Equation
                  </span>
                </div>
                <div class="space-y-1.5 font-mono tabular-nums pt-1">
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">Opening Float Entered</span>
                    <span class="font-semibold text-zinc-900">{{ money(selectedTill.opening_cash) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">+ Cash Sales</span>
                    <span class="text-emerald-700">+{{ money(selectedTill.cash_sales) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">+ Coins / Cash In</span>
                    <span class="text-emerald-700">+{{ money(selectedTill.cash_deposits) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">- Cash Refunds</span>
                    <span class="text-rose-600">-{{ money(selectedTill.cash_refunds) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">- Safe Drops / Withdrawals</span>
                    <span class="text-amber-700">-{{ money(selectedTill.cash_withdrawals) }}</span>
                  </div>
                  <div class="pt-2 border-t border-zinc-200 flex justify-between font-bold text-sm">
                    <span class="font-sans text-zinc-900">Expected Cash</span>
                    <span class="text-zinc-900">{{ money(selectedTill.expected_cash) }}</span>
                  </div>
                  <template v-if="selectedTill.status === 'closed'">
                    <div class="flex justify-between">
                      <span class="font-sans text-zinc-600">Physical Cash Entered</span>
                      <span class="font-bold text-zinc-900">{{ money(selectedTill.counted_cash) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                      <span class="font-sans font-semibold text-zinc-700">Cash Variance</span>
                      <span
                        :class="varianceBadgeClass(selectedTill.cash_difference)"
                        class="px-2 py-0.5 rounded border text-[11px] font-bold"
                      >
                        {{ varianceLabel(selectedTill.cash_difference) }}
                      </span>
                    </div>
                  </template>
                </div>
              </div>

              <div class="border border-zinc-200 rounded-xl p-4 bg-zinc-50/50 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-zinc-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <CreditCard class="w-3.5 h-3.5 text-indigo-600" />
                    Card Terminal &amp; Shift Totals
                  </span>
                </div>
                <div class="space-y-1.5 font-mono tabular-nums pt-1">
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">POS Card Sales</span>
                    <span class="text-indigo-700">{{ money(selectedTill.terminal_sales) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-sans text-zinc-600">- Card Refunds</span>
                    <span class="text-rose-600">-{{ money(selectedTill.terminal_refunds) }}</span>
                  </div>
                  <div class="pt-2 border-t border-zinc-200 flex justify-between font-bold text-sm">
                    <span class="font-sans text-zinc-900">Expected Card Net</span>
                    <span class="text-indigo-900">{{ money(selectedTill.net_terminal) }}</span>
                  </div>
                  <div v-if="selectedTill.status === 'closed'" class="space-y-1.5">
                    <div class="flex justify-between">
                      <span class="font-sans text-zinc-600">Terminal Batch Entered</span>
                      <span v-if="selectedTill.counted_terminal !== null" class="font-bold text-zinc-900">
                        {{ money(selectedTill.counted_terminal) }}
                      </span>
                      <span v-else class="font-sans text-zinc-400">Not entered</span>
                    </div>
                    <div v-if="selectedTill.counted_terminal !== null" class="flex justify-between items-center">
                      <span class="font-sans font-semibold text-zinc-700">Batch Variance</span>
                      <span
                        :class="varianceBadgeClass(selectedTill.terminal_difference)"
                        class="px-2 py-0.5 rounded border text-[11px] font-bold"
                      >
                        {{ varianceLabel(selectedTill.terminal_difference) }}
                      </span>
                    </div>
                  </div>
                  <div class="pt-2 border-t border-zinc-200 flex justify-between">
                    <span class="font-sans text-zinc-600">Total Net POS Sales</span>
                    <span class="font-bold text-zinc-900">{{ money(selectedTill.net_total_sales) }}</span>
                  </div>
                  <div class="flex justify-between text-zinc-500">
                    <span class="font-sans">Orders / Split Orders</span>
                    <span>{{ selectedTill.orders_count }} / {{ selectedTill.split_orders_count || 0 }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes Callout -->
            <div
              v-if="selectedTill.notes"
              class="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-xs text-amber-950"
            >
              <span class="font-bold uppercase tracking-wider text-[10px] text-amber-700 block mb-0.5">
                Shift Closing / Audit Note
              </span>
              {{ selectedTill.notes }}
            </div>

            <!-- Drawer Sub-Navigation -->
            <div>
              <div class="flex items-center gap-2 border-b border-zinc-200 mb-4">
                <button
                  type="button"
                  @click="drawerTab = 'timeline'"
                  :class="[
                    'pb-2.5 px-1 text-xs font-semibold border-b-2 transition-colors',
                    drawerTab === 'timeline'
                      ? 'border-zinc-900 text-zinc-900'
                      : 'border-transparent text-zinc-500 hover:text-zinc-800',
                  ]"
                >
                  Open / Drawer / Close Timeline ({{ selectedTimeline.length }})
                </button>
                <button
                  type="button"
                  @click="drawerTab = 'orders'"
                  :class="[
                    'pb-2.5 px-1 text-xs font-semibold border-b-2 transition-colors',
                    drawerTab === 'orders'
                      ? 'border-zinc-900 text-zinc-900'
                      : 'border-transparent text-zinc-500 hover:text-zinc-800',
                  ]"
                >
                  POS Orders Rung ({{ selectedOrders.length }})
                </button>
              </div>

              <!-- Chronological Timeline -->
              <div v-if="drawerTab === 'timeline'" class="space-y-3">
                <div
                  v-for="(event, index) in selectedTimeline"
                  :key="event.id"
                  class="relative flex gap-3.5"
                >
                  <div class="flex flex-col items-center">
                    <div
                      :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center border shrink-0',
                        event.kind === 'opened'
                          ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                          : event.kind === 'deposit'
                            ? 'bg-sky-50 border-sky-200 text-sky-700'
                            : event.kind === 'withdrawal'
                              ? 'bg-amber-50 border-amber-200 text-amber-700'
                              : 'bg-zinc-900 border-zinc-900 text-white',
                      ]"
                    >
                      <ShieldCheck v-if="event.kind === 'opened'" class="w-4 h-4" />
                      <ArrowDownLeft v-else-if="event.kind === 'deposit'" class="w-4 h-4" />
                      <ArrowUpRight v-else-if="event.kind === 'withdrawal'" class="w-4 h-4" />
                      <Lock v-else class="w-3.5 h-3.5" />
                    </div>
                    <div
                      v-if="index < selectedTimeline.length - 1"
                      class="w-px flex-1 bg-zinc-200 my-1"
                    />
                  </div>

                  <div class="flex-1 pb-4">
                    <div class="border border-zinc-200 rounded-xl p-3.5 bg-white shadow-2xs">
                      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <span class="text-xs font-bold text-zinc-900">{{ event.title }}</span>
                        <span class="text-[11px] font-mono text-zinc-500">{{ event.date_time_formatted }}</span>
                      </div>

                      <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="text-zinc-600">
                          Staff: <strong class="text-zinc-900">{{ event.actor_name }}</strong>
                          <span v-if="event.subtitle" class="text-zinc-500"> · {{ event.subtitle }}</span>
                        </div>
                        <div class="font-mono font-bold tabular-nums text-zinc-900">
                          {{
                            event.kind === 'withdrawal'
                              ? '-' + money(event.amount)
                              : event.kind === 'deposit'
                                ? '+' + money(event.amount)
                                : money(event.amount)
                          }}
                        </div>
                      </div>

                      <div class="mt-2 pt-2 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-2 text-[11px] text-zinc-500">
                        <span class="font-mono tabular-nums">
                          {{ event.kind === 'closed' ? 'Expected at close:' : 'Drawer balance after:' }}
                          <strong class="text-zinc-800">{{ money(event.balance_after) }}</strong>
                        </span>

                        <span
                          v-if="event.kind === 'closed' && event.variance !== null"
                          :class="varianceBadgeClass(event.variance)"
                          class="px-2 py-0.5 rounded border text-[10px] font-bold"
                        >
                          Cash: {{ varianceLabel(event.variance) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Orders Rung in Till -->
              <div v-else>
                <div v-if="selectedOrders.length === 0" class="py-10 text-center text-xs text-zinc-500">
                  No completed orders were rung inside this till shift.
                </div>
                <div v-else class="border border-zinc-200 rounded-xl overflow-hidden">
                  <table class="min-w-full divide-y divide-zinc-200 text-xs">
                    <thead class="bg-zinc-50 text-[11px] uppercase text-zinc-500 font-semibold">
                      <tr>
                        <th class="px-3 py-2 text-left">Order</th>
                        <th class="px-3 py-2 text-left">Method</th>
                        <th class="px-3 py-2 text-right">Cash / Card</th>
                        <th class="px-3 py-2 text-right">Net Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                      <tr v-for="order in selectedOrders" :key="order.id" class="hover:bg-zinc-50">
                        <td class="px-3 py-2.5">
                          <div class="font-mono font-bold text-zinc-900">#{{ order.order_number }}</div>
                          <div class="text-[11px] text-zinc-500">{{ order.time_label }} · {{ order.customer_name }}</div>
                        </td>
                        <td class="px-3 py-2.5">
                          <span
                            :class="paymentBadgeClass(order.payment_method)"
                            class="inline-flex px-2 py-0.5 rounded border text-[10px] font-bold uppercase"
                          >
                            {{ order.payment_method }}
                          </span>
                          <div v-if="order.refunded_total > 0" class="text-[10px] text-rose-600 mt-0.5 font-mono">
                            Refund -{{ money(order.refunded_total) }}
                          </div>
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-[11px]">
                          <div v-if="order.cash_amount > 0" class="text-emerald-700">Cash {{ money(order.cash_amount) }}</div>
                          <div v-if="order.terminal_amount > 0" class="text-indigo-700">Card {{ money(order.terminal_amount) }}</div>
                          <div v-if="order.change_due > 0" class="text-zinc-400">Chg {{ money(order.change_due) }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono font-bold tabular-nums text-zinc-900">
                          {{ money(order.net_amount) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Manager Force Close Modal -->
    <div v-if="closeModalOpen && closingTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-zinc-950/50 backdrop-blur-[1px]" @click="closeModalOpen = false" />

      <div class="relative w-full max-w-md bg-white rounded-2xl border border-zinc-200 shadow-2xl overflow-hidden z-10">
        <div class="px-5 py-4 bg-zinc-50 border-b border-zinc-200 flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-zinc-900">
              Manager Close Till #{{ closingTarget.id }}
            </h3>
            <p class="text-xs text-zinc-500 mt-0.5">
              Opened by {{ closingTarget.opened_by?.name || 'Staff' }} · {{ closingTarget.opened_at_formatted }}
            </p>
          </div>
          <button
            type="button"
            @click="closeModalOpen = false"
            class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200/70"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForceClose" class="p-5 space-y-4">
          <div class="rounded-xl bg-zinc-50 border border-zinc-200 p-3 grid grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-zinc-500 block">Expected Drawer Cash</span>
              <span class="font-mono font-bold text-base text-emerald-700 tabular-nums">
                {{ money(closingTarget.expected_cash) }}
              </span>
            </div>
            <div>
              <span class="text-zinc-500 block">Expected Card Net</span>
              <span class="font-mono font-bold text-base text-indigo-700 tabular-nums">
                {{ money(closingTarget.net_terminal) }}
              </span>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs font-semibold text-zinc-800">Physical Cash Counted ($) *</label>
              <button
                type="button"
                @click="fillExpectedCash"
                class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800"
              >
                Match expected ({{ money(closingTarget.expected_cash) }})
              </button>
            </div>
            <input
              v-model="closeForm.counted_cash"
              type="number"
              step="0.01"
              min="0"
              required
              placeholder="0.00"
              class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono font-semibold text-zinc-900 focus:border-emerald-500 focus:ring-emerald-500"
            />
            <div v-if="closeErrors.counted_cash" class="text-xs text-rose-600 mt-1">
              {{ closeErrors.counted_cash }}
            </div>
            <div
              v-if="previewCashVariance !== null"
              :class="varianceBadgeClass(previewCashVariance)"
              class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded border text-[11px] font-semibold"
            >
              Cash Difference: {{ varianceLabel(previewCashVariance) }}
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs font-semibold text-zinc-800">Card Terminal Batch Slip ($ optional)</label>
              <button
                type="button"
                @click="fillExpectedTerminal"
                class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-800"
              >
                Match expected ({{ money(closingTarget.net_terminal) }})
              </button>
            </div>
            <input
              v-model="closeForm.counted_terminal"
              type="number"
              step="0.01"
              min="0"
              placeholder="Leave blank if unchecked"
              class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono font-semibold text-zinc-900 focus:border-indigo-500 focus:ring-indigo-500"
            />
            <div v-if="closeErrors.counted_terminal" class="text-xs text-rose-600 mt-1">
              {{ closeErrors.counted_terminal }}
            </div>
            <div
              v-if="previewTerminalVariance !== null"
              :class="varianceBadgeClass(previewTerminalVariance)"
              class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded border text-[11px] font-semibold"
            >
              Terminal Difference: {{ varianceLabel(previewTerminalVariance) }}
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-zinc-800 mb-1">Closing Note (Optional)</label>
            <textarea
              v-model="closeForm.notes"
              rows="2"
              placeholder="Reason for admin close or variance explanation..."
              class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-xs text-zinc-900 focus:border-emerald-500 focus:ring-emerald-500"
            />
            <div v-if="closeErrors.notes" class="text-xs text-rose-600 mt-1">
              {{ closeErrors.notes }}
            </div>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="closeModalOpen = false"
              class="px-4 py-2 rounded-lg text-xs font-semibold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="closeSubmitting"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 transition-colors"
            >
              <Lock class="w-3.5 h-3.5" />
              <span>{{ closeSubmitting ? 'Closing Till...' : 'Confirm & Close Till' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

