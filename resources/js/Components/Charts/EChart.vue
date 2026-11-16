<script setup>
import VChart from 'vue-echarts';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart, PieChart, ScatterChart } from 'echarts/charts';
import {
  DataZoomComponent,
  GridComponent,
  LegendComponent,
  MarkLineComponent,
  TooltipComponent,
} from 'echarts/components';

use([
  CanvasRenderer,
  BarChart,
  LineChart,
  PieChart,
  ScatterChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  MarkLineComponent,
  DataZoomComponent,
]);

defineProps({
  option: {
    type: Object,
    required: true,
  },
  heightClass: {
    type: String,
    default: 'h-72',
  },
  updateOptions: {
    type: Object,
    default: () => ({ notMerge: true }),
  },
});

const emit = defineEmits(['chart-click', 'chart-mouseover', 'chart-mouseout']);
</script>

<template>
  <div :class="['w-full relative', heightClass]">
    <VChart
      :option="option"
      :update-options="updateOptions"
      :autoresize="{ throttle: 100 }"
      class="w-full h-full"
      style="position: absolute; inset: 0; width: 100%; height: 100%;"
      @click="(params) => emit('chart-click', params)"
      @mouseover="(params) => emit('chart-mouseover', params)"
      @mouseout="(params) => emit('chart-mouseout', params)"
    />
  </div>
</template>
