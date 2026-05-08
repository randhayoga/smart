<script setup lang="ts" generic="TData, TValue">
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

const props = withDefaults(defineProps<{
  columns: ColumnDef<TData, TValue>[]
  data: TData[]
  filterValue?: string
  filterKey?: string
  showSelectionCount?: boolean
}>(), {
  showSelectionCount: true
})

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})

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
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
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
  <div class="rounded-xl border border-border shadow-sm overflow-hidden bg-card">
    <Table>
      <TableHeader class="bg-muted/50">
        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="hover:bg-transparent">
          <TableHead 
            v-for="header in headerGroup.headers" 
            :key="header.id" 
            class="font-semibold text-foreground text-left"
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
            class="border-b border-border hover:bg-muted/30 transition-colors last:border-none"
          >
            <TableCell 
              v-for="cell in row.getVisibleCells()" 
              :key="cell.id"
              class="text-left"
              :style="{ width: cell.column.getSize() !== 150 ? `${cell.column.getSize()}px` : undefined }"
            >
              <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
            </TableCell>
          </TableRow>
        </template>
        <template v-else>
          <TableRow>
            <TableCell :colspan="columns.length" class="h-24 text-center text-muted-foreground">
              Tidak ada data.
            </TableCell>
          </TableRow>
        </template>
      </TableBody>
    </Table>
  </div>

  <!-- Pagination UI -->
  <div class="flex items-center justify-end space-x-2 pt-4 px-2">
    <div v-if="showSelectionCount" class="flex-1 text-sm text-muted-foreground">
      {{ table.getFilteredSelectedRowModel().rows.length }} of
      {{ table.getFilteredRowModel().rows.length }} row(s) selected.
    </div>
    <div class="flex items-center space-x-2">
      <Button
        variant="outline"
        size="sm"
        :disabled="!table.getCanPreviousPage()"
        @click="table.previousPage()"
        class="rounded-[14px]"
      >
        <ChevronLeft class="w-4 h-4 mr-1" />
        Sebelumnya
      </Button>
      <Button
        variant="outline"
        size="sm"
        :disabled="!table.getCanNextPage()"
        @click="table.nextPage()"
        class="rounded-[14px]"
      >
        Selanjutnya
        <ChevronRight class="w-4 h-4 ml-1" />
      </Button>
    </div>
  </div>
</template>
