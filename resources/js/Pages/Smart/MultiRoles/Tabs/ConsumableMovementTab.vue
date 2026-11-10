<script setup lang="ts">
/**
 * Stock Movement Tab container orchestrating filters, KPI cards, visual trend chart,
 * and the Stock Management Audit table.
 * Powered by Inertia lazy evaluation and partial reloads for maximum performance.
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ConsumableMovementFilterBar from './components/ConsumableMovementFilterBar.vue';
import ConsumableMovementMetricCards from './components/ConsumableMovementMetricCards.vue';
import ConsumableMovementChart from './components/ConsumableMovementChart.vue';
import StockMovementRankingCard from './components/StockMovementRankingCard.vue';
import type { RankingRow } from './components/StockMovementRankingCard.vue';
import InventoryAuditCard from '@/Pages/Smart/Admin/StockManagement/Tabs/components/InventoryAuditCard.vue';
import type { InventoryLogItem } from '@/Pages/Smart/Admin/StockManagement/Tabs/components/InventoryAuditCard.vue';

interface CategoryOption {
  id: number;
  code: string;
  name: string;
}

interface TypeOption {
  id: number;
  code: string;
  name: string;
  category_id: number;
}

interface ModelOption {
  id: number;
  number: string;
  name: string;
  type_id: number;
}

interface DepartmentOption {
  id: number;
  name: string;
  code?: string;
}

interface ProjectOption {
  id: number;
  name: string;
  no_project?: string;
}

interface DecadeOption {
  id: string;
  name: string;
  start_year: number;
  end_year: number;
}

interface FilterOptions {
  categories?: CategoryOption[];
  types?: TypeOption[];
  models?: ModelOption[];
  departments?: DepartmentOption[];
  projects?: ProjectOption[];
  availableDecades?: DecadeOption[];
  availableYears?: number[];
}

interface TrendPoint {
  period: number;
  label: string;
  total_in: number;
  total_out: number;
}

interface MovementData {
  summary?: {
    total_in: number;
    total_out: number;
    total_in_events: number;
    total_out_events: number;
    net_movement: number;
    total_events: number;
  };
  trends?: TrendPoint[];
  ranking?: RankingRow[];
  logs?: InventoryLogItem[];
  filters?: {
    category_id: string;
    type_id: string;
    model_id: string;
    utilization: string;
    department_id: string;
    project_id: string;
    year: string;
    month: string;
  };
}

interface Props {
  filters?: FilterOptions;
  movementData?: MovementData | null;
}

const props = withDefaults(defineProps<Props>(), {
  filters: () => ({
    categories: [],
    types: [],
    models: [],
    departments: [],
    projects: [],
    availableYears: [],
  }),
  movementData: null,
});

const { t, locale } = useI18n();
const page = usePage();
const isLoading = ref(false);
const isTimeFilterLoading = ref(false);

const currentYear = String(new Date().getFullYear());
const currentDecade = String(Math.floor(new Date().getFullYear() / 10) * 10);

// Active filter states - defaulted to current decade & current year (null month)
const selectedCategory = ref<string | number>('all');
const selectedType = ref<string | number>('all');
const selectedModel = ref<string | number>('all');
const selectedUtilization = ref<string>('all');
const selectedDepartment = ref<string | number>('all');
const selectedProject = ref<string | number>('all');
const selectedDecade = ref<string | number>(currentDecade);
const selectedYear = ref<string | number>(currentYear);
const selectedMonth = ref<string | number>('all');

// Initialize filters from URL search params on mount
const initFromUrl = () => {
  const url = new URL(page.url, window.location.origin);
  const cat = url.searchParams.get('category_id');
  const tp = url.searchParams.get('type_id');
  const mdl = url.searchParams.get('model_id');
  const ut = url.searchParams.get('utilization');
  const dept = url.searchParams.get('department_id');
  const prj = url.searchParams.get('project_id');
  const dec = url.searchParams.get('decade');
  const yr = url.searchParams.get('year');
  const mo = url.searchParams.get('month');

  if (cat) selectedCategory.value = cat;
  if (tp) selectedType.value = tp;
  if (mdl) selectedModel.value = mdl;
  if (ut) selectedUtilization.value = ut;
  if (dept) selectedDepartment.value = dept;
  if (prj) selectedProject.value = prj;
  if (dec) selectedDecade.value = dec;
  if (yr) selectedYear.value = yr;
  if (mo) selectedMonth.value = mo;
  if (yr === 'all') selectedMonth.value = 'all';
};

let fetchTimer: any = null;

const triggerFetch = (isTimeFilter = false) => {
  isLoading.value = true;
  if (isTimeFilter) {
    isTimeFilterLoading.value = true;
  }
  router.get(
    '/smart/dashboard',
    {
      tab: 'stock_movement',
      category_id: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
      type_id: selectedType.value !== 'all' ? selectedType.value : undefined,
      model_id: selectedModel.value !== 'all' ? selectedModel.value : undefined,
      utilization: selectedUtilization.value !== 'all' ? selectedUtilization.value : undefined,
      department_id: selectedDepartment.value !== 'all' ? selectedDepartment.value : undefined,
      project_id: selectedProject.value !== 'all' ? selectedProject.value : undefined,
      decade: selectedDecade.value ? String(selectedDecade.value) : currentDecade,
      year: (selectedYear.value && selectedYear.value !== 'all') ? String(selectedYear.value) : undefined,
      month: (selectedMonth.value && selectedMonth.value !== 'all') ? String(selectedMonth.value) : undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['stockMovementData'],
      onFinish: () => {
        isLoading.value = false;
        isTimeFilterLoading.value = false;
      },
    }
  );
};

const onFilterUpdated = (isTimeFilter = false) => {
  if (isTimeFilter) {
    isTimeFilterLoading.value = true;
  }
  if (fetchTimer) clearTimeout(fetchTimer);
  fetchTimer = setTimeout(() => {
    triggerFetch(isTimeFilter);
  }, 10);
};

const handleReset = () => {
  selectedCategory.value = 'all';
  selectedType.value = 'all';
  selectedModel.value = 'all';
  selectedUtilization.value = 'all';
  selectedDepartment.value = 'all';
  selectedProject.value = 'all';
  selectedDecade.value = currentDecade;
  selectedYear.value = '';
  selectedMonth.value = 'all';
  onFilterUpdated(true);
};

onMounted(() => {
  initFromUrl();
  if (!props.movementData) {
    triggerFetch(true);
  }
});

onUnmounted(() => {
  if (fetchTimer) clearTimeout(fetchTimer);
});

// Watch locale change or props.movementData becoming empty: ensures immediate reload without manual page refresh
watch(locale, () => {
  if (!props.movementData) {
    triggerFetch(true);
  }
});

watch(() => props.movementData, (newVal) => {
  if (!newVal) {
    triggerFetch(true);
  }
});

const summary = computed(() => props.movementData?.summary);
const trends = computed(() => props.movementData?.trends || []);
const ranking = computed(() => props.movementData?.ranking || []);
const logs = computed(() => props.movementData?.logs || []);
</script>

<template>
  <div class="p-6 bg-card rounded-xl border border-border shadow-sm overflow-hidden space-y-6">
    <!-- Filter Bar -->
    <ConsumableMovementFilterBar
      :categories="filters.categories"
      :types="filters.types"
      :models="filters.models"
      :departments="filters.departments"
      :projects="filters.projects"
      :available-decades="filters.availableDecades"
      :available-years="filters.availableYears"
      v-model:selected-category="selectedCategory"
      v-model:selected-type="selectedType"
      v-model:selected-model="selectedModel"
      v-model:selected-utilization="selectedUtilization"
      v-model:selected-department="selectedDepartment"
      v-model:selected-project="selectedProject"
      v-model:selected-decade="selectedDecade"
      v-model:selected-year="selectedYear"
      v-model:selected-month="selectedMonth"
      :is-loading="isLoading"
      @update:selected-category="onFilterUpdated(false)"
      @update:selected-type="onFilterUpdated(false)"
      @update:selected-model="onFilterUpdated(false)"
      @update:selected-utilization="onFilterUpdated(false)"
      @update:selected-department="onFilterUpdated(false)"
      @update:selected-project="onFilterUpdated(false)"
      @update:selected-decade="onFilterUpdated(true)"
      @update:selected-year="onFilterUpdated(true)"
      @update:selected-month="onFilterUpdated(true)"
      @reset="handleReset"
    />

    <!-- KPI Metric Cards -->
    <ConsumableMovementMetricCards
      :summary="summary"
      :is-loading="isLoading"
    />

    <!-- Time Series Trend Chart -->
    <ConsumableMovementChart
      :trends="trends"
      :is-loading="isTimeFilterLoading"
    />

    <!-- Stock Movement Ranking Table -->
    <StockMovementRankingCard
      :ranking="ranking"
      :is-loading="isLoading"
    />

    <!-- Stock Management Audit Table -->
    <InventoryAuditCard
      :logs="logs"
      :title="t('admin.stockMovementAuditTitle')"
      :hide-filters="true"
    />
  </div>
</template>
