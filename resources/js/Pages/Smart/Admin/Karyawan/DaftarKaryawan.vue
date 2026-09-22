<script setup lang="ts">
/**
 * Admin Employee Inventory List Page (Daftar Karyawan).
 * Displays employees, departments, and active asset loan counts with search and filter capabilities.
 * Adheres to Cruddy by Design, KISS, and DRY principles.
 */
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, ArrowUpDown, ChevronDown } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import Combobox from '@/Components/Combobox.vue';
import DataTable from '@/Components/DataTable.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DetailKaryawan, { type EmployeeData } from './Modals/DetailKaryawan.vue';

const { t } = useI18n();

interface Props {
  employees: EmployeeData[];
  departments: string[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const departmentFilter = ref<string | number | null>('');
const rowsPerPage = ref('50');
const dataTableRef = ref<any>(null);

// Modal state
const isDetailModalOpen = ref(false);
const selectedEmployee = ref<EmployeeData | null>(null);

const openDetailModal = (emp: EmployeeData) => {
  selectedEmployee.value = emp;
  isDetailModalOpen.value = true;
};

// Filtered Employees computed
const filteredEmployees = computed(() => {
  let list = props.employees || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(emp =>
      (emp.employee_id && emp.employee_id.toLowerCase().includes(q)) ||
      (emp.name && emp.name.toLowerCase().includes(q))
    );
  }

  if (departmentFilter.value) {
    list = list.filter(emp => (emp.department || '').toLowerCase() === String(departmentFilter.value).toLowerCase());
  }

  return list;
});

const hasActiveFilters = computed(() => {
  return !!(searchQuery.value || (departmentFilter.value !== '' && departmentFilter.value !== null));
});

const clearFilters = () => {
  searchQuery.value = '';
  departmentFilter.value = '';
};

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'Semua baris' || !rowsPerPage.value) return t('admin.allRows');
  return rowsPerPage.value;
});

// Table Columns: NPK | Nama | Departemen | Jumlah Aset | Aksi
const columns = computed<ColumnDef<EmployeeData>[]>(() => [
  {
    accessorKey: 'employee_id',
    id: 'employee_id',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('admin.employeeId'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-mono text-muted-foreground font-medium text-sm select-none' }, row.getValue('employee_id') || '-')
  },
  {
    accessorKey: 'name',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('admin.employeeName'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground font-medium truncate' }, row.getValue('name'))
  },
  {
    accessorKey: 'department',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('admin.employeeDepartment'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm' }, row.getValue('department') || '-')
  },
  {
    accessorKey: 'active_assets_count',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('admin.activeAssetsCount'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => {
      const count = Number(row.getValue('active_assets_count')) || 0;
      return h('span', { class: 'text-muted-foreground text-sm' }, t('admin.assetsCountSuffix', { count }));
    }
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('common.actions')),
    cell: ({ row }) => {
      return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: t('admin.viewDetail'),
          onClick: () => openDetailModal(row.original)
        }, () => [
          h(Eye, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, t('admin.viewDetail'))
        ])
      ]);
    }
  }
]);

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});
</script>

<template>
  <AppLayout :title="t('admin.employeeTitle')">
    <div class="space-y-4">
      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">{{ t('admin.employeeTitle') }}</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[220px] max-w-xs">
                  <label for="search-karyawan" class="text-xs text-muted-foreground font-medium block">{{ t('admin.filter') }}</label>
                  <TableSearch 
                    id="search-karyawan"
                    name="search"
                    v-model="searchQuery"
                    :placeholder="t('admin.employeeSearchPlaceholder')" 
                  />
                </div>

                <!-- Departemen Combobox Filter -->
                <div class="space-y-1.5 w-full sm:w-[360px] md:w-[400px]">
                  <span class="text-xs text-muted-foreground font-medium block">{{ t('admin.employeeDepartment') }}</span>
                  <Combobox 
                    v-model="departmentFilter"
                    :options="props.departments"
                    :placeholder="t('admin.allDepartments')"
                    :default-label="t('admin.allDepartments')"
                    :search-placeholder="t('admin.searchDepartment')"
                    width-class="w-full sm:w-[360px] md:w-[400px]"
                  />
                </div>

                <!-- Reset Filters Button -->
                <div class="pb-0.5">
                  <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                  >
                    <ResetFilterButton 
                      v-if="hasActiveFilters"
                      @click="clearFilters"
                    />
                  </Transition>
                </div>
              </div>

              <!-- Right: Baris per Halaman -->
              <div class="flex items-center gap-3 text-sm text-muted-foreground shrink-0 pb-1">
                <span class="whitespace-nowrap">{{ t('admin.rowsPerPage') }}</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                      {{ rowsPerPageLabel }}
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px]" align="end" :side-offset="4">
                    <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">{{ t('admin.allRows') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </div>
        </div>

        <!-- DataTable: Default sort NPK small to large (asc) -->
        <div class="pb-4">
          <DataTable
            ref="dataTableRef"
            :columns="columns"
            :data="filteredEmployees"
            :page-size="pageSizeNumber"
            :show-selection-count="false"
            :default-sorting="[{ id: 'employee_id', desc: false }]"
          />
        </div>
      </div>

      <!-- Detail Karyawan Modal -->
      <DetailKaryawan
        :open="isDetailModalOpen"
        :employee="selectedEmployee"
        @update:open="isDetailModalOpen = $event"
      />
    </div>
  </AppLayout>
</template>
