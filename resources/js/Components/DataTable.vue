<script setup lang="ts" generic="TData, TValue">
/**
 * Generic TanStack Table wrapper component providing sorting, pagination, global filtering, and row selection.
 */
import type { 
  ColumnDef, 
  ColumnFiltersState, 
  SortingState,
  VisibilityState,
} from '@tanstack/vue-table'
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { ref, watch } from 'vue'

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{
  columns: ColumnDef<TData, TValue>[]
  data: TData[]
  filterValue?: string
  filterKey?: string
  pageSize?: number
  showSelectionCount?: boolean
  defaultSorting?: SortingState
  cellClass?: string
  rowClass?: string | ((row: TData) => string)
  /**
   * Optional container class for scrollable tables (e.g. `table-container-class="max-h-[400px]"`).
   * When set:
   * 1. Enables scrolling via inner Table container (relative w-full overflow-auto + tableContainerClass).
   * 2. Automatically makes TableHeader sticky (`sticky top-0 z-10`) with solid `bg-muted/90` background.
   * 3. Outer card maintains `overflow-hidden` so all 4 rounded corners (`rounded-xl`) & borders stay intact.
   * Note: Do NOT use `backdrop-blur-*` on header cells, as Blink/Chromium compositing bleeds over ancestor border-radius.
   */
  tableContainerClass?: string
}>(), {
  pageSize: 10,
  showSelectionCount: true,
  defaultSorting: () => [],
  tableContainerClass: ''
})

const getRowClass = (row: any) => {
  if (typeof props.rowClass === 'function') {
    return props.rowClass(row.original)
  }
  return props.rowClass || ''
}

const sorting = ref<SortingState>(props.defaultSorting)
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})
const pagination = ref({
  pageIndex: 0,
  pageSize: props.pageSize,
})

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: updaterOrValue => {
    sorting.value = typeof updaterOrValue === 'function' ? updaterOrValue(sorting.value) : updaterOrValue
  },
  onColumnFiltersChange: updaterOrValue => {
    columnFilters.value = typeof updaterOrValue === 'function' ? updaterOrValue(columnFilters.value) : updaterOrValue
  },
  onColumnVisibilityChange: updaterOrValue => {
    columnVisibility.value = typeof updaterOrValue === 'function' ? updaterOrValue(columnVisibility.value) : updaterOrValue
  },
  onRowSelectionChange: updaterOrValue => {
    rowSelection.value = typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection.value) : updaterOrValue
  },
  onPaginationChange: updaterOrValue => {
    pagination.value = typeof updaterOrValue === 'function' ? updaterOrValue(pagination.value) : updaterOrValue
  },
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
    get pagination() { return pagination.value },
  },
})

// Update pagination when pageSize prop changes
watch(() => props.pageSize, (newSize) => {
  table.setPageSize(newSize || 10)
})

// Expose internal table for external filter control if needed
defineExpose({
  table
})

// Watch for filter changes from parent
watch(() => props.filterValue, (val) => {
  if (props.filterKey) {
    table.getColumn(props.filterKey)?.setFilterValue(val)
  } else {
    table.setGlobalFilter(val)
  }
})
</script>

<template>
  <!-- Outer card frame always preserves rounded-xl, border, and overflow-hidden -->
  <div class="rounded-xl border border-border shadow-sm overflow-hidden bg-card">
    <!-- Inner Table handles horizontal & vertical scrolling when tableContainerClass (e.g. max-h-[400px]) is passed -->
    <Table :container-class="tableContainerClass">
      <TableHeader :class="['bg-muted/50', tableContainerClass ? 'sticky top-0 z-10' : '']">
        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="hover:bg-transparent">
          <TableHead 
            v-for="header in headerGroup.headers" 
            :key="header.id" 
            :class="[
              'font-semibold text-foreground text-left',
              tableContainerClass ? 'bg-muted/90 border-b border-border' : ''
            ]"
            :style="{ width: header.getSize() !== 150 ? `${header.getSize()}px` : undefined }"
          >
            <FlexRender
              v-if="!header.isPlaceholder"
              :render="header.column.columnDef.header"
              :props="header.getContext()"
            />
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="table.getRowModel().rows?.length">
          <TableRow
            v-for="row in table.getRowModel().rows"
            :key="row.id"
            :data-state="row.getIsSelected() ? 'selected' : undefined"
            :class="['border-b border-border hover:bg-muted/30 transition-colors last:border-none', getRowClass(row)]"
          >
            <TableCell 
              v-for="cell in row.getVisibleCells()" 
              :key="cell.id" 
              :class="['text-left', props.cellClass]"
              :style="{ width: cell.column.getSize() !== 150 ? `${cell.column.getSize()}px` : undefined }"
            >
              <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
            </TableCell>
          </TableRow>
        </template>
        <template v-else>
          <TableRow>
            <TableCell :colspan="columns.length" class="h-24 text-center text-muted-foreground">
              {{ $t('common.noData') }}
            </TableCell>
          </TableRow>
        </template>
      </TableBody>
    </Table>
  </div>

  <!-- Pagination UI -->
  <div v-if="table.getPageCount() > 1" class="flex items-center justify-end space-x-2 pt-4 px-2">
    <div v-if="showSelectionCount" class="flex-1 text-sm text-muted-foreground">
      {{ $t('common.pagination.rowsSelected', { selected: table.getFilteredSelectedRowModel().rows.length, total: table.getFilteredRowModel().rows.length }) }}
    </div>
    <div class="flex items-center space-x-2">
      <Button
        variant="outline"
        :disabled="!table.getCanPreviousPage()"
        @click="table.previousPage()"
        class="rounded-[14px]"
      >
        <ChevronLeft class="w-4 h-4 mr-1" />
        {{ $t('common.pagination.previous') }}
      </Button>
      <Button
        variant="outline"
        :disabled="!table.getCanNextPage()"
        @click="table.nextPage()"
        class="rounded-[14px]"
      >
        {{ $t('common.pagination.next') }}
        <ChevronRight class="w-4 h-4 ml-1" />
      </Button>
    </div>
  </div>
</template>
