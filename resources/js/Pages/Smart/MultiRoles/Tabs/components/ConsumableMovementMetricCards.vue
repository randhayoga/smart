<script setup lang="ts">
/**
 * 5 Top KPI metric cards for Consumable Stock Movement:
 * 1. Total Input (Frequency of stock_in)
 * 2. Total Stock In (Volume of stock_in)
 * 3. Total Request (Frequency of stock_out)
 * 4. Total Stock Out (Volume of stock_out)
 * 5. Net Stock Changes (Net stock movement)
 * Stylized cleanly without subheadings/badges to match Classic dashboard cards.
 */
import { useI18n } from 'vue-i18n';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { ArrowDownToLine, PackagePlus, ArrowUpFromLine, PackageMinus, Scale } from 'lucide-vue-next';

interface MovementSummary {
  total_in: number;
  total_out: number;
  total_in_events: number;
  total_out_events: number;
  net_movement: number;
  total_events: number;
}

interface Props {
  summary?: MovementSummary;
  isLoading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  summary: () => ({
    total_in: 0,
    total_out: 0,
    total_in_events: 0,
    total_out_events: 0,
    net_movement: 0,
    total_events: 0,
  }),
  isLoading: false,
});

const { t, locale } = useI18n();

const formatNumber = (num: number) => {
  return (num || 0).toLocaleString(locale.value === 'en' ? 'en-US' : 'id-ID');
};
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3.5 sm:gap-4">
    <!-- 1. Total Input (Count of stock_in events) -->
    <Card class="h-full min-w-0 border-border shadow-sm">
      <CardContent class="p-3.5 sm:p-4 flex items-center justify-between h-full gap-3 sm:gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">
            {{ t('admin.totalInputEvents') }}
          </p>
          <div v-if="isLoading" class="mt-2">
            <Skeleton class="h-8 w-24 rounded" />
          </div>
          <h3 v-else class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
            {{ formatNumber(summary.total_in_events) }}
          </h3>
        </div>
        <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-emerald-500/10 rounded-lg text-emerald-600 dark:text-emerald-400 shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
          <ArrowDownToLine class="h-full w-auto max-h-full aspect-square" />
        </div>
      </CardContent>
    </Card>

    <!-- 2. Total Stock In (Volume of stock_in) -->
    <Card class="h-full min-w-0 border-border shadow-sm">
      <CardContent class="p-3.5 sm:p-4 flex items-center justify-between h-full gap-3 sm:gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">
            {{ t('admin.totalStockIn') }}
          </p>
          <div v-if="isLoading" class="mt-2">
            <Skeleton class="h-8 w-24 rounded" />
          </div>
          <h3 v-else class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
            {{ formatNumber(summary.total_in) }}
          </h3>
        </div>
        <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-emerald-500/10 rounded-lg text-emerald-600 dark:text-emerald-400 shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
          <PackagePlus class="h-full w-auto max-h-full aspect-square" />
        </div>
      </CardContent>
    </Card>

    <!-- 3. Total Request (Count of stock_out events) -->
    <Card class="h-full min-w-0 border-border shadow-sm">
      <CardContent class="p-3.5 sm:p-4 flex items-center justify-between h-full gap-3 sm:gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">
            {{ t('admin.totalRequestEvents') }}
          </p>
          <div v-if="isLoading" class="mt-2">
            <Skeleton class="h-8 w-24 rounded" />
          </div>
          <h3 v-else class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
            {{ formatNumber(summary.total_out_events) }}
          </h3>
        </div>
        <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-rose-500/10 rounded-lg text-rose-600 dark:text-rose-400 shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
          <ArrowUpFromLine class="h-full w-auto max-h-full aspect-square" />
        </div>
      </CardContent>
    </Card>

    <!-- 4. Total Stock Out (Volume of stock_out) -->
    <Card class="h-full min-w-0 border-border shadow-sm">
      <CardContent class="p-3.5 sm:p-4 flex items-center justify-between h-full gap-3 sm:gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">
            {{ t('admin.totalStockOut') }}
          </p>
          <div v-if="isLoading" class="mt-2">
            <Skeleton class="h-8 w-24 rounded" />
          </div>
          <h3 v-else class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
            {{ formatNumber(summary.total_out) }}
          </h3>
        </div>
        <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-rose-500/10 rounded-lg text-rose-600 dark:text-rose-400 shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
          <PackageMinus class="h-full w-auto max-h-full aspect-square" />
        </div>
      </CardContent>
    </Card>

    <!-- 5. Net Stock Changes -->
    <Card class="h-full min-w-0 border-border shadow-sm">
      <CardContent class="p-3.5 sm:p-4 flex items-center justify-between h-full gap-3 sm:gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">
            {{ t('admin.netStockChanges') }}
          </p>
          <div v-if="isLoading" class="mt-2">
            <Skeleton class="h-8 w-24 rounded" />
          </div>
          <h3
            v-else
            class="text-xl sm:text-2xl font-bold mt-1 tracking-tight truncate"
            :class="[
              summary.net_movement > 0 ? 'text-emerald-600 dark:text-emerald-400' :
              summary.net_movement < 0 ? 'text-rose-600 dark:text-rose-400' :
              'text-foreground'
            ]"
          >
            {{ summary.net_movement > 0 ? `+${formatNumber(summary.net_movement)}` : formatNumber(summary.net_movement) }}
          </h3>
        </div>
        <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-primary/10 rounded-lg text-primary shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
          <Scale class="h-full w-auto max-h-full aspect-square" />
        </div>
      </CardContent>
    </Card>
  </div>
</template>
