<script setup lang="ts">
/**
 * Filter bar for Consumable Stock Movement Dashboard.
 * Supports Category -> Type -> Model cascading, Utilization (Corporate/Project) with dynamic
 * Department/Project selection, and 3-tier date granularity (Total, Year, Month).
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Combobox from '@/Components/Combobox.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';

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
  brand_name?: string;
  specification?: string;
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

interface Props {
  categories?: CategoryOption[];
  types?: TypeOption[];
  models?: ModelOption[];
  departments?: DepartmentOption[];
  projects?: ProjectOption[];
  availableDecades?: DecadeOption[];
  availableYears?: number[];
  selectedCategory: string | number;
  selectedType: string | number;
  selectedModel: string | number;
  selectedUtilization: string;
  selectedDepartment: string | number;
  selectedProject: string | number;
  selectedDecade: string | number;
  selectedYear: string | number;
  selectedMonth: string | number;
  isLoading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  categories: () => [],
  types: () => [],
  models: () => [],
  departments: () => [],
  projects: () => [],
  availableDecades: () => [],
  availableYears: () => [],
  isLoading: false,
});

const emit = defineEmits<{
  (e: 'update:selectedCategory', val: string | number): void;
  (e: 'update:selectedType', val: string | number): void;
  (e: 'update:selectedModel', val: string | number): void;
  (e: 'update:selectedUtilization', val: string): void;
  (e: 'update:selectedDepartment', val: string | number): void;
  (e: 'update:selectedProject', val: string | number): void;
  (e: 'update:selectedDecade', val: string | number): void;
  (e: 'update:selectedYear', val: string | number): void;
  (e: 'update:selectedMonth', val: string | number): void;
  (e: 'reset'): void;
}>();

const { t, locale } = useI18n();

// 1. Category Options (No manual prepending of 'Semua' to prevent duplicate)
const categoryComboboxOptions = computed(() => {
  return props.categories.map((c) => ({
    id: c.id,
    name: `${c.name} (${c.code})`,
  }));
});

// 2. Cascading Type Options
const filteredTypes = computed(() => {
  if (!props.selectedCategory || props.selectedCategory === 'all') {
    return props.types;
  }
  return props.types.filter((tp) => String(tp.category_id) === String(props.selectedCategory));
});

const typeComboboxOptions = computed(() => {
  return filteredTypes.value.map((tp) => ({
    id: tp.id,
    name: tp.name,
  }));
});

// 3. Cascading Model Options
const filteredModels = computed(() => {
  if (props.selectedType && props.selectedType !== 'all') {
    return props.models.filter((m) => String(m.type_id) === String(props.selectedType));
  }
  if (props.selectedCategory && props.selectedCategory !== 'all') {
    const validCategoryTypeIds = new Set(filteredTypes.value.map((tp) => String(tp.id)));
    return props.models.filter((m) => validCategoryTypeIds.has(String(m.type_id)));
  }
  return props.models;
});

const formatModelLabel = (m: ModelOption): string => {
  if (m.brand_name || m.specification) {
    const parts = [m.brand_name, m.name, m.specification]
      .map((s) => (s ?? '').trim())
      .filter((s) => s !== '' && s !== '-');
    if (m.brand_name && m.name.includes(m.brand_name)) {
      return m.name;
    }
    return parts.length > 0 ? parts.join(' ') : (m.name || `Model #${m.id}`);
  }
  return m.name || `Model #${m.id}`;
};

const modelComboboxOptions = computed(() => {
  return filteredModels.value.map((m) => ({
    id: m.id,
    name: formatModelLabel(m),
  }));
});

// 4. Utilization Options
const utilizationOptions = computed(() => [
  { id: 'corporate', name: 'Corporate' },
  { id: 'project', name: 'Project' },
]);

// 5. Department Options
const departmentOptions = computed(() => {
  return props.departments.map((d) => ({
    id: d.id,
    name: d.name,
  }));
});

// 6. Project Options
const projectOptions = computed(() => {
  return props.projects.map((p) => ({
    id: p.id,
    name: p.no_project ? `${p.name} (${p.no_project})` : p.name,
  }));
});

// 7. Decade Options
const currentDecade = String(Math.floor(new Date().getFullYear() / 10) * 10);
const decadeComboboxOptions = computed(() => {
  if (props.availableDecades && props.availableDecades.length > 0) {
    return props.availableDecades.map((d) => ({
      id: String(d.id),
      name: d.name,
    }));
  }
  return [{ id: currentDecade, name: `${currentDecade} - ${Number(currentDecade) + 9}` }];
});

// 8. Year Options (Filtered to selected decade, without duplicate 'All')
const yearComboboxOptions = computed(() => {
  const dec = props.selectedDecade ? Number(props.selectedDecade) : Number(currentDecade);
  const years = props.availableYears.filter((yr) => yr >= dec && yr <= dec + 9);
  return years.map((yr) => ({
    id: String(yr),
    name: String(yr),
  }));
});

// 9. Month Options
const monthNames = computed(() => {
  const isEn = locale.value === 'en';
  const idMonths = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
  ];
  const enMonths = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];
  const list = isEn ? enMonths : idMonths;
  return list.map((name, idx) => ({
    id: String(idx + 1),
    name,
  }));
});

const isYearSpecific = computed(() => {
  return props.selectedYear !== 'all' && props.selectedYear !== '';
});

const hasActiveFilters = computed(() => {
  return (
    (props.selectedCategory !== 'all' && props.selectedCategory !== '') ||
    (props.selectedType !== 'all' && props.selectedType !== '') ||
    (props.selectedModel !== 'all' && props.selectedModel !== '') ||
    (props.selectedUtilization !== 'all' && props.selectedUtilization !== '') ||
    (props.selectedDepartment !== 'all' && props.selectedDepartment !== '') ||
    (props.selectedProject !== 'all' && props.selectedProject !== '') ||
    String(props.selectedDecade) !== currentDecade ||
    (props.selectedYear !== 'all' && props.selectedYear !== '') ||
    (props.selectedMonth !== 'all' && props.selectedMonth !== '')
  );
});

const handleDecadeChange = (val: string | number | null) => {
  const nextVal = val ? String(val) : currentDecade;
  emit('update:selectedDecade', nextVal);
  const dec = Number(nextVal);
  if (props.selectedYear && (Number(props.selectedYear) < dec || Number(props.selectedYear) > dec + 9)) {
    emit('update:selectedYear', '');
    emit('update:selectedMonth', '');
  }
};

const handleCategoryChange = (val: string | number | null) => {
  const nextVal = val ? String(val) : 'all';
  emit('update:selectedCategory', nextVal);
  if (nextVal !== 'all') {
    const validTypes = props.types.filter((tp) => String(tp.category_id) === nextVal);
    const isCurrentTypeValid = validTypes.some((tp) => String(tp.id) === String(props.selectedType));
    if (!isCurrentTypeValid && props.selectedType !== 'all') {
      emit('update:selectedType', 'all');
      emit('update:selectedModel', 'all');
    }
  }
};

const handleTypeChange = (val: string | number | null) => {
  const nextVal = val ? String(val) : 'all';
  emit('update:selectedType', nextVal);
  if (nextVal !== 'all') {
    const validModels = props.models.filter((m) => String(m.type_id) === nextVal);
    const isCurrentModelValid = validModels.some((m) => String(m.id) === String(props.selectedModel));
    if (!isCurrentModelValid && props.selectedModel !== 'all') {
      emit('update:selectedModel', 'all');
    }
  }
};

const handleUtilizationChange = (val: string | number | null) => {
  const nextVal = val ? String(val) : 'all';
  emit('update:selectedUtilization', nextVal);
  emit('update:selectedDepartment', 'all');
  emit('update:selectedProject', 'all');
};

const handleYearChange = (val: string | number | null) => {
  const nextVal = val ? String(val) : 'all';
  emit('update:selectedYear', nextVal);
  if (nextVal === 'all') {
    emit('update:selectedMonth', 'all');
  }
};
</script>

<template>
  <div class="flex flex-wrap items-end gap-3 p-4 bg-muted/40 rounded-xl border border-border">
    <!-- Category Combobox -->
    <div class="space-y-1.5 min-w-[170px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterCategory') }}
      </label>
      <Combobox
        :model-value="selectedCategory === 'all' ? '' : selectedCategory"
        @update:model-value="handleCategoryChange"
        :options="categoryComboboxOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[220px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Type Combobox -->
    <div class="space-y-1.5 min-w-[170px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterType') }}
      </label>
      <Combobox
        :model-value="selectedType === 'all' ? '' : selectedType"
        @update:model-value="handleTypeChange"
        :options="typeComboboxOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[200px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Model Combobox (Widened for lengthy model names) -->
    <div class="space-y-1.5 min-w-[220px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterModel') }}
      </label>
      <Combobox
        :model-value="selectedModel === 'all' ? '' : selectedModel"
        @update:model-value="emit('update:selectedModel', $event ? String($event) : 'all')"
        :options="modelComboboxOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[320px] md:w-[380px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Utilization Combobox -->
    <div class="space-y-1.5 min-w-[140px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterUtilization') }}
      </label>
      <Combobox
        :model-value="selectedUtilization === 'all' ? '' : selectedUtilization"
        @update:model-value="handleUtilizationChange"
        :options="utilizationOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[170px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Dynamic Department Combobox (When Utilization is Corporate) -->
    <div
      v-if="selectedUtilization === 'corporate'"
      class="space-y-1.5 min-w-[180px] flex-1 sm:flex-initial animate-in fade-in duration-200"
    >
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterDepartment') }}
      </label>
      <Combobox
        :model-value="selectedDepartment === 'all' ? '' : selectedDepartment"
        @update:model-value="emit('update:selectedDepartment', $event ? String($event) : 'all')"
        :options="departmentOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[260px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Dynamic Project Combobox (When Utilization is Project) -->
    <div
      v-else-if="selectedUtilization === 'project'"
      class="space-y-1.5 min-w-[180px] flex-1 sm:flex-initial animate-in fade-in duration-200"
    >
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterProject') }}
      </label>
      <Combobox
        :model-value="selectedProject === 'all' ? '' : selectedProject"
        @update:model-value="emit('update:selectedProject', $event ? String($event) : 'all')"
        :options="projectOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[260px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Decade Combobox -->
    <div class="space-y-1.5 min-w-[140px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterDecade') }}
      </label>
      <Combobox
        :model-value="String(selectedDecade)"
        @update:model-value="handleDecadeChange"
        :options="decadeComboboxOptions"
        :placeholder="t('admin.filterDecade')"
        width-class="w-full sm:w-[160px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Year Combobox -->
    <div class="space-y-1.5 min-w-[130px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterYear') }}
      </label>
      <Combobox
        :model-value="selectedYear === 'all' ? '' : selectedYear"
        @update:model-value="handleYearChange"
        :options="yearComboboxOptions"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[150px]"
        :disabled="isLoading"
      />
    </div>

    <!-- Month Combobox (Conditioned on Year Selection) -->
    <div class="space-y-1.5 min-w-[150px] flex-1 sm:flex-initial">
      <label class="text-xs text-muted-foreground font-medium block">
        {{ t('admin.filterMonth') }}
      </label>
      <Combobox
        :model-value="selectedMonth === 'all' ? '' : selectedMonth"
        @update:model-value="emit('update:selectedMonth', $event ? String($event) : 'all')"
        :options="monthNames"
        :placeholder="t('common.all')"
        :default-label="t('common.all')"
        width-class="w-full sm:w-[190px]"
        :disabled="isLoading || !isYearSpecific"
      />
    </div>

    <!-- Reset Filter Button -->
    <div v-if="hasActiveFilters" class="pb-0.5">
      <ResetFilterButton @click="emit('reset')" />
    </div>
  </div>
</template>
