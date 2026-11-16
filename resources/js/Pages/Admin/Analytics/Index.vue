<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EChart from '@/Components/Charts/EChart.vue';
import { escapeHtml, formatCompactMoney, formatMoney, formatSignedMoney } from '@/lib/chartFormatters';
import {
  AlertTriangle,
  ArrowUpRight,
  Banknote,
  BarChart3,
  Calendar,
  CheckCircle2,
  CreditCard,
  Download,
  Globe,
  Layers,
  Receipt,
  Search,
  ShieldCheck,
  Split,
  TrendingDown,
  TrendingUp,
  Wallet,
} from 'lucide-vue-next';

const props = defineProps({
  report: {
    type: Object,
    required: true,
  },
});

const filters = computed(() => props.report.filters || {});
const summary = computed(() => props.report.summary || {});
const comparison = computed(() => props.report.comparison || {});
const drawerSummary = computed(() => props.report.drawer_summary || {});
const methodBreakdown = computed(() => props.report.method_breakdown || []);
const series = computed(() => props.report.series || []);
const shifts = computed(() => props.report.shifts || []);
const recentTransactions = computed(() => props.report.recent_transactions || []);
const integrity = computed(() => props.report.integrity || { is_healthy: true });

const fromInput = ref(filters.value.from || '');
const toInput = ref(filters.value.to || '');

watch(
  () => [filters.value.from, filters.value.to],
  ([nextFrom, nextTo]) => {
    fromInput.value = nextFrom || '';
    toInput.value = nextTo || '';
  }
);

const activeSeriesFilter = ref('all'); // 'all' | 'pos_cash' | 'pos_card' | 'online'
const chartType = ref('bar'); // 'bar' | 'area'
const txChannelFilter = ref('all');
const txSearchQuery = ref('');

const presets = [
  { key: 'today', label: 'Today' },
  { key: 'yesterday', label: 'Yesterday' },
  { key: '7d', label: 'Last 7 Days' },
  { key: '30d', label: 'Last 30 Days' },
  { key: 'this_month', label: 'This Month' },
  { key: 'last_month', label: 'Last Month' },
];

const groupOptions = [
  { key: 'day', label: 'Daily' },
  { key: 'week', label: 'Weekly' },
  { key: 'month', label: 'Monthly' },
];

const navigateWithParams = (params) => {
  router.get(route('admin.analytics.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const selectPreset = (presetKey) => {
  navigateWithParams({
    preset: presetKey,
    group_by: filters.value.auto_group_by ? undefined : filters.value.group_by,
  });
};

const applyCustomRange = () => {
  if (!fromInput.value || !toInput.value) {
    return;
  }
  navigateWithParams({
    preset: 'custom',
    from: fromInput.value,
    to: toInput.value,
    group_by: filters.value.auto_group_by ? undefined : filters.value.group_by,
  });
};

const selectGroupBy = (groupKey) => {
  navigateWithParams({
    preset: filters.value.preset,
    from: filters.value.from,
    to: filters.value.to,
    group_by: groupKey,
  });
};

const exportUrl = computed(() =>
  route('admin.analytics.export', {
    preset: filters.value.preset,
    from: filters.value.from,
    to: filters.value.to,
    group_by: filters.value.group_by,
  })
);

const tillLogsUrl = computed(() => {
  const preset = ['today', 'yesterday', '7d', '30d', 'this_month', 'last_month'].includes(filters.value.preset)
    ? filters.value.preset
    : 'custom';
  const query = {
    preset,
    from: preset === 'custom' ? filters.value.from : undefined,
    to: preset === 'custom' ? filters.value.to : undefined,
  };
  try {
    if (typeof route === 'function' && route().has('admin.tills.index')) {
      return route('admin.tills.index', query);
    }
  } catch {
    // fallback
  }
  const search = new URLSearchParams(
    Object.entries(query).filter(([, v]) => v !== undefined && v !== '')
  ).toString();
  return search ? `/admin/tills?${search}` : '/admin/tills';
});

// Channel proportions & Apache ECharts Donut + Revenue Trend configs
const positiveChannelTotal = computed(() => {
  const cash = Math.max(0, Number(summary.value.pos_cash_net || 0));
  const card = Math.max(0, Number(summary.value.pos_card_net || 0));
  const online = Math.max(0, Number(summary.value.online_net || 0));
  return cash + card + online;
});

const channelShare = (amount) => {
  if (positiveChannelTotal.value <= 0) {
    return 0;
  }
  return Math.round((Math.max(0, Number(amount || 0)) / positiveChannelTotal.value) * 1000) / 10;
};

const channelMixRows = computed(() => {
  const rows = [
    {
      key: 'pos_cash',
      label: 'POS Cash Drawer',
      amount: Math.max(0, Number(summary.value.pos_cash_net || 0)),
      rawAmount: Number(summary.value.pos_cash_net || 0),
      color: '#10b981',
      dotClass: 'bg-emerald-500',
      textClass: 'text-emerald-700',
    },
    {
      key: 'pos_card',
      label: 'POS Card Terminal',
      amount: Math.max(0, Number(summary.value.pos_card_net || 0)),
      rawAmount: Number(summary.value.pos_card_net || 0),
      color: '#0ea5e9',
      dotClass: 'bg-sky-500',
      textClass: 'text-sky-700',
    },
    {
      key: 'online',
      label: 'Website Online',
      amount: Math.max(0, Number(summary.value.online_net || 0)),
      rawAmount: Number(summary.value.online_net || 0),
      color: '#8b5cf6',
      dotClass: 'bg-violet-500',
      textClass: 'text-violet-700',
    },
  ];

  return rows.map((row) => ({
    ...row,
    sharePct: channelShare(row.amount),
  }));
});

const getBucketChannelNet = (bucket, filterKey) => {
  if (!bucket) {
    return 0;
  }
  switch (filterKey) {
    case 'pos_cash':
      return Number(bucket.pos_cash_net || 0);
    case 'pos_card':
      return Number(bucket.pos_card_net || 0);
    case 'online':
      return Number(bucket.online_net || 0);
    default:
      return Number(bucket.total_net || 0);
  }
};

const getBucketChannelRefunds = (bucket, filterKey) => {
  if (!bucket) {
    return 0;
  }
  switch (filterKey) {
    case 'pos_cash':
      return Number(bucket.pos_cash_refunds || 0);
    case 'pos_card':
      return Number(bucket.pos_card_refunds || 0);
    case 'online':
      return Number(bucket.online_refunds || 0);
    default:
      return Number(bucket.total_refunds || 0);
  }
};

const trendHighlights = computed(() => {
  const buckets = series.value;
  const key = activeSeriesFilter.value;
  let totalNet = 0;
  let refundsTotal = 0;
  let activeCount = 0;
  let peakBucket = null;
  let peakAmount = 0;

  for (const b of buckets) {
    const net = getBucketChannelNet(b, key);
    const ref = getBucketChannelRefunds(b, key);
    totalNet += net;
    refundsTotal += ref;
    if (Math.abs(net) > 0.005 || Number(b.orders || 0) > 0) {
      activeCount += 1;
    }
    if (!peakBucket || net > peakAmount) {
      peakAmount = net;
      peakBucket = b;
    }
  }

  return {
    totalNet,
    refundsTotal,
    activeCount,
    totalCount: buckets.length,
    avgActive: activeCount > 0 ? totalNet / activeCount : 0,
    peakLabel: peakBucket && peakAmount > 0 ? peakBucket.short_label || peakBucket.label : '—',
    peakAmount: peakAmount > 0 ? peakAmount : 0,
  };
});

const channelMixChartOption = computed(() => {
  const hasPositive = positiveChannelTotal.value > 0;
  const data = hasPositive
    ? channelMixRows.value.map((row) => ({
        name: row.label,
        value: row.amount,
        sharePct: row.sharePct,
        rawAmount: row.rawAmount,
        itemStyle: { color: row.color },
      }))
    : [
        {
          name: 'No Net Revenue',
          value: 1,
          sharePct: 0,
          rawAmount: 0,
          itemStyle: { color: '#e4e4e7' },
        },
      ];

  return {
    tooltip: {
      trigger: 'item',
      confine: true,
      backgroundColor: '#09090b',
      borderColor: '#27272a',
      borderWidth: 1,
      padding: [8, 12],
      textStyle: { color: '#fafafa', fontSize: 12 },
      formatter: (params) => {
        if (!hasPositive) {
          return '<div style="font-size:12px;">No revenue collected in range</div>';
        }
        return `
          <div style="font-weight:700;margin-bottom:4px;">${escapeHtml(params.name)}</div>
          <div style="display:flex;justify-content:space-between;gap:14px;font-family:monospace;">
            <span>${escapeHtml(formatMoney(params.data.rawAmount))}</span>
            <span style="color:#a1a1aa;">${Number(params.percent || 0).toFixed(1)}%</span>
          </div>
        `;
      },
    },
    series: [
      {
        name: 'Channel Mix',
        type: 'pie',
        radius: ['62%', '86%'],
        center: ['50%', '50%'],
        padAngle: hasPositive ? 3 : 0,
        avoidLabelOverlap: false,
        silent: !hasPositive,
        label: { show: false },
        labelLine: { show: false },
        itemStyle: {
          borderRadius: 6,
          borderColor: '#ffffff',
          borderWidth: 2,
        },
        emphasis: {
          scale: hasPositive,
          scaleSize: 4,
        },
        data,
      },
    ],
  };
});

const revenueChartOption = computed(() => {
  const buckets = series.value;
  const labels = buckets.map((b) => b.short_label || b.label || b.key);
  const showCash = activeSeriesFilter.value === 'all' || activeSeriesFilter.value === 'pos_cash';
  const showCard = activeSeriesFilter.value === 'all' || activeSeriesFilter.value === 'pos_card';
  const showOnline = activeSeriesFilter.value === 'all' || activeSeriesFilter.value === 'online';
  const isArea = chartType.value === 'area';
  const isStacked = activeSeriesFilter.value === 'all';
  const useZoom = buckets.length > 60;

  const channelConfigs = [
    {
      enabled: showCash,
      name: 'POS Cash',
      field: 'pos_cash_net',
      color: '#10b981',
      areaTop: 'rgba(16, 185, 129, 0.24)',
      areaBottom: 'rgba(16, 185, 129, 0.01)',
    },
    {
      enabled: showCard,
      name: 'POS Card',
      field: 'pos_card_net',
      color: '#0ea5e9',
      areaTop: 'rgba(14, 165, 233, 0.24)',
      areaBottom: 'rgba(14, 165, 233, 0.01)',
    },
    {
      enabled: showOnline,
      name: 'Website Online',
      field: 'online_net',
      color: '#8b5cf6',
      areaTop: 'rgba(139, 92, 246, 0.24)',
      areaBottom: 'rgba(139, 92, 246, 0.01)',
    },
  ].filter((cfg) => cfg.enabled);

  // Determine per-bucket topmost enabled positive bar so every stack cap gets clean rounded corners
  const topSeriesPerBucket = buckets.map((bucket) => {
    for (let i = channelConfigs.length - 1; i >= 0; i -= 1) {
      if (Math.max(0, Number(bucket[channelConfigs[i].field] || 0)) > 0) {
        return i;
      }
    }
    return channelConfigs.length - 1;
  });

  const builtSeries = channelConfigs.map((cfg, idx) => {
    const rawValues = buckets.map((b) => Math.max(0, Number(b[cfg.field] || 0)));

    if (isArea) {
      return {
        name: cfg.name,
        type: 'line',
        stack: isStacked ? 'revenue' : undefined,
        smooth: 0.3,
        symbol: 'circle',
        symbolSize: (val) => (Number(val) > 0 ? 7 : 0),
        showSymbol: buckets.length <= 45,
        lineStyle: { width: 2.25, color: cfg.color },
        itemStyle: { color: cfg.color, borderColor: '#ffffff', borderWidth: 1.5 },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: cfg.areaTop },
              { offset: 1, color: cfg.areaBottom },
            ],
          },
        },
        emphasis: { focus: 'series', scale: true },
        data: rawValues,
      };
    }

    const barData = rawValues.map((val, bucketIdx) => {
      const isCap = !isStacked || topSeriesPerBucket[bucketIdx] === idx;
      return {
        value: val,
        itemStyle: {
          color: cfg.color,
          borderRadius: isCap && val > 0 ? [5, 5, 0, 0] : [0, 0, 0, 0],
        },
      };
    });

    return {
      name: cfg.name,
      type: 'bar',
      stack: isStacked ? 'revenue' : undefined,
      barMaxWidth: 28,
      barCategoryGap: '32%',
      itemStyle: { color: cfg.color },
      emphasis: { focus: 'series' },
      data: barData,
    };
  });

  return {
    animationDuration: 320,
    tooltip: {
      trigger: 'axis',
      confine: true,
      axisPointer: {
        type: isArea ? 'line' : 'shadow',
        shadowStyle: { color: 'rgba(113, 113, 122, 0.06)' },
        lineStyle: { color: '#a1a1aa', type: 'dashed' },
      },
      backgroundColor: '#09090b',
      borderColor: '#27272a',
      borderWidth: 1,
      padding: [10, 12],
      textStyle: { color: '#fafafa', fontSize: 12 },
      formatter: (params) => {
        const list = Array.isArray(params) ? params : [params];
        const idx = list[0]?.dataIndex ?? 0;
        const bucket = buckets[idx];
        if (!bucket) {
          return '';
        }

        const refundRow =
          Number(bucket.total_refunds || 0) > 0
            ? `<div style="display:flex;justify-content:space-between;gap:16px;color:#fda4af;margin-top:2px;">
                 <span>Refunds</span>
                 <span style="font-family:monospace;">−${escapeHtml(formatMoney(bucket.total_refunds))}</span>
               </div>`
            : '';

        return `
          <div style="min-width:210px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding-bottom:6px;margin-bottom:6px;border-bottom:1px solid #27272a;">
              <strong style="color:#ffffff;font-size:12px;">${escapeHtml(bucket.label)}</strong>
              <span style="font-family:monospace;font-size:11px;color:#a1a1aa;">${Number(bucket.orders || 0)} ord</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;">
              <span style="color:#6ee7b7;">● POS Cash</span>
              <span style="font-family:monospace;">${escapeHtml(formatMoney(bucket.pos_cash_net))}</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;margin-top:2px;">
              <span style="color:#7dd3fc;">● POS Card</span>
              <span style="font-family:monospace;">${escapeHtml(formatMoney(bucket.pos_card_net))}</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;margin-top:2px;">
              <span style="color:#c4b5fd;">● Website Online</span>
              <span style="font-family:monospace;">${escapeHtml(formatMoney(bucket.online_net))}</span>
            </div>
            ${refundRow}
            <div style="display:flex;justify-content:space-between;gap:16px;padding-top:6px;margin-top:6px;border-top:1px solid #27272a;font-weight:700;">
              <span>Period Net</span>
              <span style="font-family:monospace;">${escapeHtml(formatMoney(bucket.total_net))}</span>
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
    grid: {
      left: 4,
      right: 8,
      top: 38,
      bottom: useZoom ? 34 : 4,
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      boundaryGap: !isArea,
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
        formatter: (value) => formatCompactMoney(value),
      },
      splitLine: { lineStyle: { type: 'dashed', color: '#f4f4f5' } },
    },
    dataZoom: useZoom
      ? [
          { type: 'inside', xAxisIndex: 0 },
          {
            type: 'slider',
            height: 14,
            bottom: 2,
            borderColor: '#e4e4e7',
            fillerColor: 'rgba(24, 24, 27, 0.08)',
            handleStyle: { color: '#52525b' },
          },
        ]
      : [],
    series: builtSeries,
  };
});

const renderDeltaBadgeClass = (pct) => {
  if (pct === null || pct === undefined) {
    return 'bg-zinc-100 text-zinc-500 border-zinc-200';
  }
  return Number(pct) >= 0
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : 'bg-rose-50 text-rose-700 border-rose-200';
};

const filteredTransactions = computed(() => {
  const q = txSearchQuery.value.trim().toLowerCase();
  return recentTransactions.value.filter((tx) => {
    if (txChannelFilter.value !== 'all' && tx.channel !== txChannelFilter.value) {
      return false;
    }
    if (!q) {
      return true;
    }
    const orderNum = String(tx.order_number || '').toLowerCase();
    const customer = String(tx.customer_name || '').toLowerCase();
    const paymentLabel = String(tx.payment_label || '').toLowerCase();
    const shiftId = tx.pos_shift_id ? `#${tx.pos_shift_id}` : '';
    return (
      orderNum.includes(q) ||
      customer.includes(q) ||
      paymentLabel.includes(q) ||
      shiftId.includes(q)
    );
  });
});
</script>

<template>
  <AdminLayout title="Payments & Revenue Analytics">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      <!-- Page Header & Control Bar -->
      <header class="bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-1.5">
              <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                <BarChart3 class="w-3.5 h-3.5 text-emerald-600" />
                <span>Revenue &amp; Channel Analytics</span>
              </span>
              <span class="text-[11px] font-mono text-zinc-500 bg-zinc-100 border border-zinc-200 px-2 py-0.5 rounded-full">
                {{ filters.timezone || 'America/Toronto' }}
              </span>
              <span
                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                :class="
                  integrity.is_healthy
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : 'bg-amber-50 text-amber-800 border-amber-200'
                "
              >
                <ShieldCheck v-if="integrity.is_healthy" class="w-3.5 h-3.5" />
                <AlertTriangle v-else class="w-3.5 h-3.5" />
                <span>
                  {{ integrity.is_healthy ? 'Ledger Reconciled' : 'Audit Alert Active' }}
                </span>
              </span>
            </div>

            <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">
              Payments &amp; Channel Performance
            </h1>
            <p class="text-xs sm:text-sm text-zinc-500 mt-1">
              Net collections after refunds across POS Cash, POS Card Terminal, and Online Stripe orders
              <span class="font-mono font-medium text-zinc-700 ml-1">
                ({{ filters.from }} → {{ filters.to }} · {{ filters.days_count }} {{ filters.days_count === 1 ? 'day' : 'days' }})
              </span>
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <Link
              :href="tillLogsUrl"
              class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-zinc-800 bg-zinc-100 hover:bg-zinc-200/80 border border-zinc-200 transition-colors"
            >
              <Wallet class="w-3.5 h-3.5 text-amber-600" />
              <span>Till &amp; Shift Logs</span>
              <ArrowUpRight class="w-3.5 h-3.5 text-zinc-500" />
            </Link>

            <a
              :href="exportUrl"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold transition-colors shadow-2xs"
            >
              <Download class="w-3.5 h-3.5" />
              <span>Export CSV</span>
            </a>
          </div>
        </div>

        <!-- Unified Date Presets, Custom Range & Day/Week/Month Switcher -->
        <div class="pt-3.5 border-t border-zinc-100 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="preset in presets"
              :key="preset.key"
              type="button"
              @click="selectPreset(preset.key)"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer"
              :class="
                filters.preset === preset.key
                  ? 'bg-zinc-900 border-zinc-900 text-white shadow-2xs'
                  : 'bg-zinc-50 border-zinc-200 text-zinc-700 hover:bg-zinc-100'
              "
            >
              {{ preset.label }}
            </button>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <form
              @submit.prevent="applyCustomRange"
              class="inline-flex items-center gap-1.5 bg-zinc-50 border border-zinc-200 rounded-xl px-2.5 py-1"
            >
              <Calendar class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
              <input
                v-model="fromInput"
                type="date"
                aria-label="From date"
                class="bg-transparent border-0 text-xs font-mono text-zinc-800 focus:ring-0 p-0"
              />
              <span class="text-xs text-zinc-400">to</span>
              <input
                v-model="toInput"
                type="date"
                aria-label="To date"
                class="bg-transparent border-0 text-xs font-mono text-zinc-800 focus:ring-0 p-0"
              />
              <button
                type="submit"
                class="ml-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold cursor-pointer transition-colors"
              >
                Apply
              </button>
            </form>

            <!-- Day / Week / Month Granularity Toggle -->
            <div class="inline-flex items-center rounded-xl bg-zinc-100 p-1 border border-zinc-200">
              <button
                v-for="group in groupOptions"
                :key="group.key"
                type="button"
                @click="selectGroupBy(group.key)"
                class="px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                :class="
                  filters.group_by === group.key
                    ? 'bg-white text-zinc-950 shadow-2xs'
                    : 'text-zinc-600 hover:text-zinc-900'
                "
              >
                {{ group.label }}
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Elevated Light-Theme 4 KPI Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Total Net Revenue -->
        <div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs flex flex-col justify-between">
          <div class="h-1 bg-zinc-900 w-full" />
          <div class="p-4 flex-1 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-semibold text-zinc-600">Total Net Revenue</span>
              <span
                v-if="comparison.total_net_pct !== null && comparison.total_net_pct !== undefined"
                class="inline-flex items-center gap-0.5 text-[11px] font-mono font-bold px-2 py-0.5 rounded-full border"
                :class="renderDeltaBadgeClass(comparison.total_net_pct)"
              >
                <TrendingUp v-if="Number(comparison.total_net_pct) >= 0" class="w-3 h-3" />
                <TrendingDown v-else class="w-3 h-3" />
                {{ Number(comparison.total_net_pct) >= 0 ? '+' : '' }}{{ comparison.total_net_pct }}%
              </span>
              <span v-else class="text-[11px] font-mono text-zinc-500 bg-zinc-100 px-2 py-0.5 rounded-full">
                {{ summary.total_orders || 0 }} orders
              </span>
            </div>

            <div class="mt-3">
              <div class="text-3xl font-bold font-mono tabular-nums tracking-tight text-zinc-950">
                {{ formatMoney(summary.total_net) }}
              </div>
              <div class="mt-3 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs font-mono tabular-nums">
                <div>
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Gross Collected</span>
                  <span class="text-zinc-800 font-semibold">{{ formatMoney(summary.total_gross) }}</span>
                </div>
                <div class="text-right">
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Refunds</span>
                  <span class="text-rose-600 font-semibold">−{{ formatMoney(summary.total_refunds) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- POS Cash Drawer -->
        <div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs flex flex-col justify-between">
          <div class="h-1 bg-emerald-500 w-full" />
          <div class="p-4 flex-1 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700">
                  <Banknote class="w-4 h-4" />
                </div>
                <span class="text-xs font-semibold text-zinc-700">POS Cash Drawer</span>
              </div>
              <span
                v-if="comparison.pos_cash_net_pct !== null && comparison.pos_cash_net_pct !== undefined"
                class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full border"
                :class="renderDeltaBadgeClass(comparison.pos_cash_net_pct)"
              >
                {{ Number(comparison.pos_cash_net_pct) >= 0 ? '+' : '' }}{{ comparison.pos_cash_net_pct }}%
              </span>
            </div>

            <div class="mt-3">
              <div class="flex items-baseline justify-between gap-2">
                <div class="text-2xl font-bold font-mono tabular-nums text-zinc-950">
                  {{ formatMoney(summary.pos_cash_net) }}
                </div>
                <span class="text-xs font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                  {{ channelShare(summary.pos_cash_net) }}%
                </span>
              </div>
              <div class="mt-3 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs font-mono tabular-nums">
                <div>
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Cash Gross</span>
                  <span class="text-zinc-800 font-semibold">{{ formatMoney(summary.pos_cash_gross) }}</span>
                </div>
                <div class="text-right">
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Cash Refunds</span>
                  <span class="text-rose-600 font-semibold">−{{ formatMoney(summary.pos_cash_refunds) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- POS Card Terminal -->
        <div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs flex flex-col justify-between">
          <div class="h-1 bg-sky-500 w-full" />
          <div class="p-4 flex-1 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-sky-50 border border-sky-200 flex items-center justify-center text-sky-700">
                  <CreditCard class="w-4 h-4" />
                </div>
                <span class="text-xs font-semibold text-zinc-700">POS Card Terminal</span>
              </div>
              <span
                v-if="Number(summary.pos_split_orders || 0) > 0"
                class="inline-flex items-center gap-1 text-[11px] font-mono font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200"
                title="Includes card tenders from split Cash + Card orders"
              >
                <Split class="w-3 h-3" />
                {{ summary.pos_split_orders }} split
              </span>
            </div>

            <div class="mt-3">
              <div class="flex items-baseline justify-between gap-2">
                <div class="text-2xl font-bold font-mono tabular-nums text-zinc-950">
                  {{ formatMoney(summary.pos_card_net) }}
                </div>
                <span class="text-xs font-mono font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md">
                  {{ channelShare(summary.pos_card_net) }}%
                </span>
              </div>
              <div class="mt-3 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs font-mono tabular-nums">
                <div>
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Terminal Gross</span>
                  <span class="text-zinc-800 font-semibold">{{ formatMoney(summary.pos_card_gross) }}</span>
                </div>
                <div class="text-right">
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Terminal Refunds</span>
                  <span class="text-rose-600 font-semibold">−{{ formatMoney(summary.pos_card_refunds) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Website Online (Stripe) -->
        <div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs flex flex-col justify-between">
          <div class="h-1 bg-violet-500 w-full" />
          <div class="p-4 flex-1 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-violet-50 border border-violet-200 flex items-center justify-center text-violet-700">
                  <Globe class="w-4 h-4" />
                </div>
                <span class="text-xs font-semibold text-zinc-700">Website Online (Stripe)</span>
              </div>
              <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-700 border border-zinc-200">
                {{ summary.online_orders || 0 }} orders
              </span>
            </div>

            <div class="mt-3">
              <div class="flex items-baseline justify-between gap-2">
                <div class="text-2xl font-bold font-mono tabular-nums text-zinc-950">
                  {{ formatMoney(summary.online_net) }}
                </div>
                <span class="text-xs font-mono font-bold text-violet-700 bg-violet-50 px-2 py-0.5 rounded-md">
                  {{ channelShare(summary.online_net) }}%
                </span>
              </div>
              <div class="mt-3 pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs font-mono tabular-nums">
                <div>
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Online Gross</span>
                  <span class="text-zinc-800 font-semibold">{{ formatMoney(summary.online_gross) }}</span>
                </div>
                <div class="text-right">
                  <span class="text-zinc-400 block text-[10px] font-sans uppercase font-semibold">Stripe Refunds</span>
                  <span class="text-rose-600 font-semibold">−{{ formatMoney(summary.online_refunds) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Visual Analytics Row: Apache ECharts Trend + Channel Mix Donut -->
      <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <!-- Interactive ECharts Bar / Area Trend Chart -->
        <div class="xl:col-span-2 bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs flex flex-col">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-4 border-b border-zinc-100">
            <div class="min-w-0">
              <h2 class="text-base font-bold text-zinc-900 tracking-tight">
                Revenue Trend by {{ filters.group_by === 'day' ? 'Day' : filters.group_by === 'week' ? 'Week' : 'Month' }}
              </h2>
              <p class="text-xs text-zinc-500 mt-0.5">
                Net POS Cash, POS Card Terminal, and Website Online collections after refunds
              </p>
            </div>

            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
              <!-- Bar / Smooth Area Mode Toggle -->
              <div class="inline-flex items-center gap-0.5 bg-zinc-100 p-1 rounded-xl border border-zinc-200">
                <button
                  type="button"
                  @click="chartType = 'bar'"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap"
                  :class="
                    chartType === 'bar'
                      ? 'bg-zinc-900 text-white shadow-2xs'
                      : 'text-zinc-600 hover:text-zinc-900'
                  "
                >
                  <BarChart3 class="w-3.5 h-3.5" />
                  <span>Bar</span>
                </button>
                <button
                  type="button"
                  @click="chartType = 'area'"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap"
                  :class="
                    chartType === 'area'
                      ? 'bg-zinc-900 text-white shadow-2xs'
                      : 'text-zinc-600 hover:text-zinc-900'
                  "
                >
                  <TrendingUp class="w-3.5 h-3.5" />
                  <span>Area</span>
                </button>
              </div>

              <!-- Channel Filter Pills -->
              <div class="inline-flex items-center gap-0.5 bg-zinc-100 p-1 rounded-xl border border-zinc-200">
                <button
                  v-for="opt in [
                    { key: 'all', label: 'All' },
                    { key: 'pos_cash', label: 'Cash' },
                    { key: 'pos_card', label: 'Card' },
                    { key: 'online', label: 'Online' },
                  ]"
                  :key="opt.key"
                  type="button"
                  @click="activeSeriesFilter = opt.key"
                  class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer whitespace-nowrap"
                  :class="
                    activeSeriesFilter === opt.key
                      ? 'bg-white text-zinc-950 shadow-2xs'
                      : 'text-zinc-600 hover:text-zinc-900'
                  "
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- Compact Trend Summary Strip -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 py-3.5 border-b border-zinc-100 tabular-nums">
            <div class="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">Selected Net</span>
              <span class="text-sm font-bold font-mono text-zinc-950 mt-0.5 block">
                {{ formatMoney(trendHighlights.totalNet) }}
              </span>
            </div>
            <div class="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">Active Periods</span>
              <span class="text-sm font-bold font-mono text-zinc-900 mt-0.5 block">
                {{ trendHighlights.activeCount }}
                <span class="text-[11px] font-normal text-zinc-500">/ {{ trendHighlights.totalCount }}</span>
              </span>
            </div>
            <div class="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">Avg / Active Period</span>
              <span class="text-sm font-bold font-mono text-zinc-900 mt-0.5 block">
                {{ formatMoney(trendHighlights.avgActive) }}
              </span>
            </div>
            <div class="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">Peak Period</span>
              <span class="text-sm font-bold font-mono text-emerald-700 mt-0.5 block truncate">
                {{ trendHighlights.peakAmount > 0 ? `${trendHighlights.peakLabel} · ${formatMoney(trendHighlights.peakAmount)}` : '—' }}
              </span>
            </div>
          </div>

          <EChart
            v-if="series.length > 0"
            :option="revenueChartOption"
            height-class="flex-1 min-h-[340px] mt-4"
          />

          <div v-else class="flex-1 min-h-[340px] flex items-center justify-center text-xs text-zinc-500">
            No series periods to display for the selected range.
          </div>
        </div>

        <!-- ECharts Channel Mix Donut Chart + Accounting Metrics -->
        <div class="bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs flex flex-col justify-between space-y-5">
          <div>
            <h2 class="text-base font-bold text-zinc-900 tracking-tight">Channel Revenue Mix</h2>
            <p class="text-xs text-zinc-500">Share of net revenue &amp; tax/fee totals</p>
          </div>

          <!-- ECharts Donut Ring -->
          <div class="flex flex-col sm:flex-row xl:flex-col items-center gap-4">
            <div class="relative w-44 h-44 shrink-0">
              <EChart :option="channelMixChartOption" height-class="h-44" />
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-4">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">Net Mix</span>
                <span class="text-sm font-bold font-mono text-zinc-900 tabular-nums mt-0.5">
                  {{ formatMoney(summary.total_net) }}
                </span>
              </div>
            </div>

            <!-- Legend Summary Rows -->
            <div class="w-full space-y-2">
              <div
                v-for="seg in channelMixRows"
                :key="seg.key"
                class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-xl border border-zinc-100 bg-zinc-50/50"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span :class="seg.dotClass" class="w-2.5 h-2.5 rounded-full shrink-0" />
                  <span class="text-xs font-medium text-zinc-700 truncate">{{ seg.label }}</span>
                </div>
                <div class="text-right font-mono tabular-nums shrink-0">
                  <span class="text-xs font-bold text-zinc-900">{{ formatMoney(seg.rawAmount) }}</span>
                  <span :class="seg.textClass" class="text-[11px] font-semibold ml-1.5">{{ seg.sharePct }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tax / Fees / Discounts / AOV Sub-Grid -->
          <div class="pt-3 border-t border-zinc-100 grid grid-cols-2 gap-2.5 text-xs tabular-nums">
            <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] uppercase font-semibold text-zinc-500 block">Avg Order Value</span>
              <span class="font-mono font-bold text-zinc-900 mt-0.5 block">{{ formatMoney(summary.average_order_value) }}</span>
            </div>
            <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] uppercase font-semibold text-zinc-500 block">Paid Orders</span>
              <span class="font-mono font-bold text-zinc-900 mt-0.5 block">
                {{ summary.total_orders || 0 }}
                <span class="text-[10px] text-zinc-500 font-normal">({{ summary.pos_orders || 0 }} POS / {{ summary.online_orders || 0 }} Web)</span>
              </span>
            </div>
            <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] uppercase font-semibold text-zinc-500 block">Tax Collected (Net)</span>
              <span class="font-mono font-bold text-zinc-900 mt-0.5 block">{{ formatMoney(summary.tax_collected) }}</span>
            </div>
            <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100">
              <span class="text-[10px] uppercase font-semibold text-zinc-500 block">Delivery Fees</span>
              <span class="font-mono font-bold text-zinc-900 mt-0.5 block">{{ formatMoney(summary.delivery_fees_collected) }}</span>
            </div>
            <div class="col-span-2 p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/70 flex items-center justify-between">
              <span class="text-[11px] font-semibold text-amber-900">Discounts &amp; Loyalty Redeemed</span>
              <span class="font-mono font-bold text-amber-800">−{{ formatMoney(summary.discounts_total) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Spacious Side-by-Side Lower Section: Period Table + Live Till / Methods / Integrity -->
      <section class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
        <!-- Period Accounting Breakdown Table -->
        <div class="xl:col-span-2 bg-white border border-zinc-200 rounded-2xl shadow-2xs overflow-hidden">
          <div class="px-5 py-4 border-b border-zinc-200 flex items-center justify-between gap-3 bg-zinc-50/60">
            <div class="flex items-center gap-2">
              <Layers class="w-4 h-4 text-zinc-500" />
              <div>
                <h2 class="text-sm font-bold text-zinc-900">
                  {{ filters.group_by === 'day' ? 'Daily' : filters.group_by === 'week' ? 'Weekly' : 'Monthly' }} Accounting Breakdown
                </h2>
                <p class="text-[11px] text-zinc-500">
                  Net settlements with gross &amp; refund details per period bucket
                </p>
              </div>
            </div>
            <span class="text-[11px] font-mono font-semibold text-zinc-600 bg-white border border-zinc-200 px-2.5 py-1 rounded-lg">
              {{ series.length }} {{ series.length === 1 ? 'period' : 'periods' }}
            </span>
          </div>

          <div class="overflow-x-auto max-h-[520px] overflow-y-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="sticky top-0 z-10 bg-zinc-50 border-b border-zinc-200 text-[10px] font-mono uppercase tracking-wider text-zinc-500">
                <tr>
                  <th class="py-3 px-4 font-semibold">Period</th>
                  <th class="py-3 px-3 font-semibold text-right">Orders</th>
                  <th class="py-3 px-3 font-semibold text-right text-emerald-800">POS Cash</th>
                  <th class="py-3 px-3 font-semibold text-right text-sky-800">POS Card</th>
                  <th class="py-3 px-3 font-semibold text-right text-violet-800">Online Web</th>
                  <th class="py-3 px-3 font-semibold text-right text-rose-700">Refunds</th>
                  <th class="py-3 px-4 font-semibold text-right text-zinc-900">Total Net</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-100 tabular-nums font-mono">
                <tr v-for="row in series" :key="row.key" class="hover:bg-zinc-50/80 transition-colors">
                  <td class="py-3 px-4 font-sans font-semibold text-zinc-900">
                    {{ row.label }}
                    <span class="block text-[10px] font-mono font-normal text-zinc-400">
                      {{ row.start_date === row.end_date ? row.start_date : `${row.start_date} → ${row.end_date}` }}
                    </span>
                  </td>
                  <td class="py-3 px-3 text-right text-zinc-700">
                    {{ row.orders }}
                  </td>
                  <td class="py-3 px-3 text-right">
                    <span class="font-bold text-emerald-800">{{ formatMoney(row.pos_cash_net) }}</span>
                    <span v-if="row.pos_cash_gross > 0 || row.pos_cash_refunds > 0" class="block text-[10px] text-zinc-400">
                      {{ formatMoney(row.pos_cash_gross) }}
                      <template v-if="row.pos_cash_refunds > 0"> / −{{ formatMoney(row.pos_cash_refunds) }}</template>
                    </span>
                  </td>
                  <td class="py-3 px-3 text-right">
                    <span class="font-bold text-sky-800">{{ formatMoney(row.pos_card_net) }}</span>
                    <span v-if="row.pos_card_gross > 0 || row.pos_card_refunds > 0" class="block text-[10px] text-zinc-400">
                      {{ formatMoney(row.pos_card_gross) }}
                      <template v-if="row.pos_card_refunds > 0"> / −{{ formatMoney(row.pos_card_refunds) }}</template>
                    </span>
                  </td>
                  <td class="py-3 px-3 text-right">
                    <span class="font-bold text-violet-800">{{ formatMoney(row.online_net) }}</span>
                    <span v-if="row.online_gross > 0 || row.online_refunds > 0" class="block text-[10px] text-zinc-400">
                      {{ formatMoney(row.online_gross) }}
                      <template v-if="row.online_refunds > 0"> / −{{ formatMoney(row.online_refunds) }}</template>
                    </span>
                  </td>
                  <td class="py-3 px-3 text-right text-rose-600">
                    {{ row.total_refunds > 0 ? `−${formatMoney(row.total_refunds)}` : '$0.00' }}
                  </td>
                  <td class="py-3 px-4 text-right font-bold text-zinc-950">
                    {{ formatMoney(row.total_net) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-zinc-200 bg-zinc-50 font-mono font-bold tabular-nums text-zinc-950">
                  <td class="py-3.5 px-4 font-sans">Range Totals</td>
                  <td class="py-3.5 px-3 text-right">{{ summary.total_orders }}</td>
                  <td class="py-3.5 px-3 text-right text-emerald-800">{{ formatMoney(summary.pos_cash_net) }}</td>
                  <td class="py-3.5 px-3 text-right text-sky-800">{{ formatMoney(summary.pos_card_net) }}</td>
                  <td class="py-3.5 px-3 text-right text-violet-800">{{ formatMoney(summary.online_net) }}</td>
                  <td class="py-3.5 px-3 text-right text-rose-700">−{{ formatMoney(summary.total_refunds) }}</td>
                  <td class="py-3.5 px-4 text-right text-sm">{{ formatMoney(summary.total_net) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Side Stack: POS Till Summary + Payment Methods & Audit -->
        <div class="space-y-5">
          <!-- Till Reconciliation Snapshot Card -->
          <div class="bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs space-y-4">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-700">
                  <Wallet class="w-4 h-4" />
                </div>
                <div>
                  <h3 class="text-sm font-bold text-zinc-900">POS Till &amp; Cash Drawer</h3>
                  <p class="text-[11px] text-zinc-500">
                    {{ drawerSummary.shifts_count || 0 }} shifts · {{ drawerSummary.shifts_open_count || 0 }} open / {{ drawerSummary.shifts_closed_count || 0 }} closed
                  </p>
                </div>
              </div>
              <Link
                :href="tillLogsUrl"
                class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-0.5 shrink-0"
              >
                <span>Open Logs</span>
                <ArrowUpRight class="w-3.5 h-3.5" />
              </Link>
            </div>

            <div class="grid grid-cols-2 gap-2.5 text-xs tabular-nums">
              <div class="p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200/80">
                <span class="text-[10px] uppercase font-semibold text-emerald-800 block">Coins / Deposits In</span>
                <span class="font-mono font-bold text-emerald-900 mt-0.5 block">+{{ formatMoney(drawerSummary.cash_deposits_total) }}</span>
              </div>
              <div class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/80">
                <span class="text-[10px] uppercase font-semibold text-amber-800 block">Safe Drops</span>
                <span class="font-mono font-bold text-amber-900 mt-0.5 block">−{{ formatMoney(drawerSummary.cash_withdrawals_total) }}</span>
              </div>
              <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-200">
                <span class="text-[10px] uppercase font-semibold text-zinc-500 block">Cash Variance</span>
                <span
                  class="font-mono font-bold mt-0.5 block"
                  :class="
                    Math.abs(Number(drawerSummary.cash_variance_total || 0)) < 0.005
                      ? 'text-emerald-700'
                      : Number(drawerSummary.cash_variance_total) < 0
                        ? 'text-rose-600'
                        : 'text-amber-700'
                  "
                >
                  {{ formatSignedMoney(drawerSummary.cash_variance_total) }}
                </span>
              </div>
              <div class="p-2.5 rounded-xl bg-sky-50/60 border border-sky-200/80">
                <span class="text-[10px] uppercase font-semibold text-sky-800 block">Card Batch Diff</span>
                <span class="font-mono font-bold text-sky-950 mt-0.5 block">
                  <template v-if="drawerSummary.shifts_with_terminal_count > 0">
                    {{ formatSignedMoney(drawerSummary.terminal_variance_total) }}
                  </template>
                  <span v-else class="text-[11px] font-sans font-normal text-sky-600">No slips</span>
                </span>
              </div>
            </div>

            <!-- Recent Shifts Snapshot List -->
            <div v-if="shifts.length > 0" class="space-y-2 pt-1">
              <div
                v-for="shift in shifts.slice(0, 3)"
                :key="shift.id"
                class="p-2.5 rounded-xl border border-zinc-200 bg-zinc-50/60 flex items-center justify-between gap-2 text-xs"
              >
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="font-mono font-bold text-zinc-900">#{{ shift.id }}</span>
                    <span
                      class="text-[10px] font-bold uppercase px-1.5 py-0.2 rounded-full"
                      :class="shift.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-700'"
                    >
                      {{ shift.status }}
                    </span>
                  </div>
                  <div class="text-[11px] text-zinc-500 truncate mt-0.5">
                    {{ shift.opened_by?.name || 'Staff' }} · {{ shift.opened_at_formatted }}
                  </div>
                </div>
                <div class="text-right font-mono tabular-nums shrink-0">
                  <div class="font-bold text-zinc-900">{{ formatMoney(shift.expected_cash) }}</div>
                  <div v-if="shift.status === 'closed'" class="text-[10px]">
                    <span
                      :class="
                        Math.abs(Number(shift.cash_difference || 0)) < 0.005
                          ? 'text-emerald-700'
                          : Number(shift.cash_difference) < 0
                            ? 'text-rose-600'
                            : 'text-amber-700'
                      "
                    >
                      {{ formatSignedMoney(shift.cash_difference) }} diff
                    </span>
                  </div>
                  <div v-else class="text-[10px] text-emerald-700">Live drawer</div>
                </div>
              </div>
            </div>

            <Link
              :href="tillLogsUrl"
              class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold transition-colors"
            >
              <Wallet class="w-3.5 h-3.5" />
              <span>View Full Till &amp; Shift Logs</span>
              <ArrowUpRight class="w-3.5 h-3.5" />
            </Link>
          </div>

          <!-- Payment Methods & Integrity Audit Card -->
          <div class="bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs space-y-4">
            <div class="flex items-center gap-2">
              <ShieldCheck class="w-4 h-4 text-emerald-600" />
              <div>
                <h3 class="text-sm font-bold text-zinc-900">Payment Methods &amp; Audit</h3>
                <p class="text-[11px] text-zinc-500">Breakdown by tender &amp; automated ledger checks</p>
              </div>
            </div>

            <div class="space-y-2">
              <div v-if="methodBreakdown.length === 0" class="text-xs text-zinc-500 py-2">
                No paid orders in this period.
              </div>
              <div
                v-for="method in methodBreakdown"
                :key="method.key"
                class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl border border-zinc-100 bg-zinc-50/70 text-xs"
              >
                <div class="min-w-0">
                  <div class="font-semibold text-zinc-900 truncate">{{ method.label }}</div>
                  <div class="text-[10px] text-zinc-500 uppercase font-mono">
                    {{ method.channel }} · {{ method.orders }} orders
                  </div>
                </div>
                <div class="text-right font-mono tabular-nums shrink-0">
                  <div class="font-bold text-zinc-950">{{ formatMoney(method.net) }}</div>
                  <div v-if="method.refunds > 0" class="text-[10px] text-rose-600">
                    −{{ formatMoney(method.refunds) }} ref
                  </div>
                </div>
              </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 space-y-1.5 text-xs">
              <div class="flex items-center justify-between py-1">
                <span class="text-zinc-600">Drawer Formula Checks</span>
                <span
                  class="font-mono font-bold text-[11px] inline-flex items-center gap-1"
                  :class="integrity.drawer_formula_mismatches === 0 ? 'text-emerald-700' : 'text-rose-600'"
                >
                  <CheckCircle2 v-if="integrity.drawer_formula_mismatches === 0" class="w-3.5 h-3.5" />
                  {{ integrity.drawer_formula_mismatches === 0 ? '0 errors' : `${integrity.drawer_formula_mismatches} issues` }}
                </span>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-zinc-600">Split Tender Balance</span>
                <span
                  class="font-mono font-bold text-[11px] inline-flex items-center gap-1"
                  :class="integrity.mismatched_split_orders === 0 ? 'text-emerald-700' : 'text-rose-600'"
                >
                  <CheckCircle2 v-if="integrity.mismatched_split_orders === 0" class="w-3.5 h-3.5" />
                  {{ integrity.mismatched_split_orders === 0 ? '0 errors' : `${integrity.mismatched_split_orders} issues` }}
                </span>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-zinc-600">Unshifted POS Orders</span>
                <span
                  class="font-mono font-bold text-[11px]"
                  :class="integrity.unshifted_pos_orders === 0 ? 'text-emerald-700' : 'text-amber-700'"
                >
                  {{ integrity.unshifted_pos_orders === 0 ? '0' : integrity.unshifted_pos_orders }}
                </span>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-zinc-600">Online Orders w/o Stripe ID</span>
                <span class="font-mono font-bold text-[11px] text-zinc-700">
                  {{ integrity.online_missing_stripe_id || 0 }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Full-Width Searchable Recent Orders Ledger -->
      <section class="bg-white border border-zinc-200 rounded-2xl shadow-2xs overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50/60">
          <div class="flex items-center gap-2">
            <Receipt class="w-4 h-4 text-zinc-500" />
            <div>
              <h2 class="text-sm font-bold text-zinc-900">Recent Paid &amp; Refunded Orders Ledger</h2>
              <p class="text-[11px] text-zinc-500">
                Up to 50 recent orders in the selected period with cash/card split &amp; refund traceability
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
              <Search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input
                v-model="txSearchQuery"
                type="text"
                placeholder="Order #, customer, Till #..."
                class="pl-8 pr-3 py-1.5 w-48 rounded-lg border border-zinc-200 text-xs text-zinc-800 placeholder-zinc-400 focus:border-emerald-500 focus:ring-emerald-500"
              />
            </div>

            <div class="inline-flex items-center rounded-xl bg-zinc-100 p-0.5 border border-zinc-200">
              <button
                v-for="opt in [
                  { key: 'all', label: 'All' },
                  { key: 'pos', label: 'POS' },
                  { key: 'online', label: 'Online' },
                ]"
                :key="opt.key"
                type="button"
                @click="txChannelFilter = opt.key"
                class="px-2.5 py-1 rounded-lg text-[11px] font-semibold cursor-pointer transition-colors"
                :class="txChannelFilter === opt.key ? 'bg-white text-zinc-950 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900'"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-zinc-200 bg-zinc-50 text-[10px] font-mono uppercase tracking-wider text-zinc-500">
                <th class="py-3 px-4 font-semibold">Order</th>
                <th class="py-3 px-3 font-semibold">Channel &amp; Tender</th>
                <th class="py-3 px-3 font-semibold text-right">Cash / Card Split</th>
                <th class="py-3 px-3 font-semibold text-right">Gross</th>
                <th class="py-3 px-3 font-semibold text-right">Refunded</th>
                <th class="py-3 px-4 font-semibold text-right">Net Collected</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 tabular-nums font-mono">
              <tr v-if="filteredTransactions.length === 0">
                <td colspan="6" class="py-10 text-center font-sans text-zinc-500">
                  No matching orders found.
                </td>
              </tr>
              <tr v-for="tx in filteredTransactions" :key="tx.id" class="hover:bg-zinc-50/80 transition-colors">
                <td class="py-2.5 px-4 font-sans">
                  <div class="font-bold text-zinc-900">{{ tx.order_number }}</div>
                  <div class="text-[11px] text-zinc-500">{{ tx.customer_name }} · {{ tx.created_at_formatted }}</div>
                </td>
                <td class="py-2.5 px-3 font-sans">
                  <span
                    class="inline-block text-[10px] font-mono uppercase px-2 py-0.5 rounded-md font-semibold mr-1.5 border"
                    :class="tx.channel === 'pos' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-violet-50 text-violet-800 border-violet-200'"
                  >
                    {{ tx.channel }}
                  </span>
                  <span class="text-zinc-700 font-medium">{{ tx.payment_label }}</span>
                  <span v-if="tx.pos_shift_id" class="text-[10px] text-zinc-500 font-mono ml-1">(Till #{{ tx.pos_shift_id }})</span>
                </td>
                <td class="py-2.5 px-3 text-right text-[11px]">
                  <template v-if="tx.channel === 'pos'">
                    <span v-if="tx.cash_amount > 0" class="text-emerald-700">Cash {{ formatMoney(tx.cash_amount) }}</span>
                    <span v-if="tx.cash_amount > 0 && tx.terminal_amount > 0" class="text-zinc-400 mx-1">/</span>
                    <span v-if="tx.terminal_amount > 0" class="text-sky-700">Card {{ formatMoney(tx.terminal_amount) }}</span>
                  </template>
                  <span v-else class="text-violet-700">Stripe</span>
                </td>
                <td class="py-2.5 px-3 text-right text-zinc-800">{{ formatMoney(tx.total) }}</td>
                <td class="py-2.5 px-3 text-right text-rose-600">
                  {{ tx.refunded_total > 0 ? `−${formatMoney(tx.refunded_total)}` : '$0.00' }}
                </td>
                <td class="py-2.5 px-4 text-right font-bold text-zinc-950">{{ formatMoney(tx.net_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
