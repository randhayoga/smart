<script setup lang="ts">
/**
 * Interactive Dual Bar Chart comparing Consumable Stock-In vs Stock-Out over time.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { BarChart } from '@/Components/ui/chart';
import { Skeleton } from '@/Components/ui/skeleton';

interface TrendPoint {
  period: number;
  label: string;
  total_in: number;
  total_out: number;
}

interface Props {
  trends?: TrendPoint[];
  isLoading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  trends: () => [],
  isLoading: false,
});

const { t, locale } = useI18n();

const chartData = computed(() => {
  if (props.trends && props.trends.length > 0) {
    return props.trends;
  }
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return monthNames.map((name, i) => ({
    period: i + 1,
    label: name,
    total_in: 0,
    total_out: 0,
  }));
});

// Chart colors: Emerald for In, Rose for Out
const chartColors = ['#10B981', '#F43F5E'];

const customYFormatter = (tick: number | Date) => {
  const val = Number(tick);
  return isNaN(val) ? '0' : val.toLocaleString(locale.value === 'en' ? 'en-US' : 'id-ID');
};
</script>

<template>
  <Card class="border-border shadow-sm">
    <CardHeader class="pb-2">
      <div class="flex items-center justify-between">
        <CardTitle class="text-lg font-semibold tracking-tight">
          {{ t('admin.movementTrendTitle') }}
        </CardTitle>
      </div>
      <CardDescription class="text-sm">
        {{ t('admin.movementTrendDesc') }}
      </CardDescription>
    </CardHeader>

    <CardContent class="p-4 sm:p-6">
      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="h-[280px] w-full flex items-end gap-3 pt-6 pb-2">
        <Skeleton class="h-[60%] flex-1 rounded" />
        <Skeleton class="h-[80%] flex-1 rounded" />
        <Skeleton class="h-[40%] flex-1 rounded" />
        <Skeleton class="h-[90%] flex-1 rounded" />
        <Skeleton class="h-[50%] flex-1 rounded" />
        <Skeleton class="h-[75%] flex-1 rounded" />
      </div>

      <!-- Dual Bar Chart (Renders empty axes when no transactions exist) -->
      <div v-else class="w-full">
        <BarChart
          :data="chartData"
          index="label"
          :categories="['total_in', 'total_out']"
          :category-labels="{
            total_in: t('admin.totalStockIn'),
            total_out: t('admin.totalStockOut'),
          }"
          :colors="chartColors"
          :y-formatter="customYFormatter"
          height="280px"
        />
      </div>
    </CardContent>
  </Card>
</template>
