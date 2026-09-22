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
import { ref, watch, computed, onMounted } from 'vue'

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
import { Skeleton } from '@/Components/ui/skeleton'
import { useTableSearch } from '@/composables/useTableSearch'

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
  /**
   * Whether the table is in a loading state, rendering pulsing skeleton rows.
   */
  loading?: boolean
  /**
   * Number of skeleton rows to render when loading is true (default: 5).
   */
  skeletonRows?: number
  /**
   * Threshold of rows above which table searching triggers pulsing skeleton rows (default: 50).
   */
  skeletonThreshold?: number
}>(), {
  pageSize: 50,
  showSelectionCount: true,
  defaultSorting: () => [],
  tableContainerClass: '',
  loading: false,
  skeletonRows: 5,
  skeletonThreshold: 50,
})

const getRowClass = (row: any) => {
  if (typeof props.rowClass === 'function') {
    return props.rowClass(row.original)
  }
  return props.rowClass || ''
}

const isCodeIdentifier = (id: string): boolean => {
  const lower = id.toLowerCase()
  if (lower.includes('phone') || lower.includes('count')) return false
  if (['code', 'number', 'employee_id', 'nik', 'asset_code', 'assetcode', 'lot_code', 'lotcode', 'unit_number', 'kode_aset'].includes(lower)) return true
  if (/^(.*_)?(code|kode)(_.*)?$/i.test(id)) return true
  if (/^(.*_)?number$/i.test(id)) return true
  return false
}

const isDateIdentifier = (id: string): boolean => {
  const lower = id.toLowerCase()
  if (lower === 'durasi' || lower === 'duration') return false
  if (['waktu', 'lastupdate', 'created_at', 'updated_at', 'time'].includes(lower)) return true
  if (/(date|time)$/i.test(id)) return true
  if (/(^|_)(date|time)(_|$)/i.test(id)) return true
  return false
}

const getColId = (col: any): string => {
  return (col.id || (typeof col.accessorKey === 'string' ? col.accessorKey : '')) as string
}

const resolveDefaultSorting = (): SortingState => {
  if (props.defaultSorting && props.defaultSorting.length > 0) {
    return props.defaultSorting
  }

  // 1. Code (if exists): descending (exception: employee_id is sorted ascending)
  for (const col of props.columns) {
    if (col.enableSorting === false) continue
    const colId = getColId(col)
    const accessorKey = typeof (col as any).accessorKey === 'string' ? (col as any).accessorKey : ''
    if ((colId && isCodeIdentifier(colId)) || (accessorKey && isCodeIdentifier(accessorKey))) {
      const targetId = colId || accessorKey
      const isAscendingCode = targetId.toLowerCase() === 'employee_id' || targetId.toLowerCase() === 'nik'
      return [{ id: targetId, desc: !isAscendingCode }]
    }
  }

  // 2. Date (if exists): newest (descending)
  for (const col of props.columns) {
    if (col.enableSorting === false) continue
    const colId = getColId(col)
    const accessorKey = typeof (col as any).accessorKey === 'string' ? (col as any).accessorKey : ''
    if ((colId && isDateIdentifier(colId)) || (accessorKey && isDateIdentifier(accessorKey))) {
      return [{ id: colId || accessorKey, desc: true }]
    }
  }

  // 3. If both doesn't exists, keep the current default sort
  return props.defaultSorting || []
}

const sorting = ref<SortingState>(resolveDefaultSorting())
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
  table.setPageSize(newSize || 50)
})

// Update sorting when defaultSorting or columns change
watch(() => props.defaultSorting, (newVal) => {
  if (newVal && newVal.length > 0) {
    sorting.value = newVal
  } else {
    sorting.value = resolveDefaultSorting()
  }
}, { deep: true })

watch(() => props.columns, () => {
  if (!props.defaultSorting || props.defaultSorting.length === 0) {
    sorting.value = resolveDefaultSorting()
  }
}, { deep: true })

// Expose internal table for external filter control if needed
defineExpose({
  table
})

// Coordinate with TableSearch and internal filtering
const { isTableSearching } = useTableSearch()
const isInternalSearching = ref(false)
let filterDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Determine if this is a large dataset exceeding the threshold (e.g. > 50 rows)
const isLargeDataset = computed(() => (props.data?.length || 0) > (props.skeletonThreshold ?? 50))

// Brief skeleton on initial mount of large tables so the browser renders smoothly without freeze
const isInitialLoading = ref((props.data?.length || 0) > (props.skeletonThreshold ?? 50))

onMounted(() => {
  if (isInitialLoading.value) {
    setTimeout(() => {
      isInitialLoading.value = false
    }, 250)
  }
})

// Watch data changes if data loads asynchronously
watch(() => props.data?.length, (newLen) => {
  if (!isInitialLoading.value && (newLen || 0) > (props.skeletonThreshold ?? 50) && !props.filterValue) {
    isInitialLoading.value = true
    setTimeout(() => {
      isInitialLoading.value = false
    }, 200)
  }
})

// Show skeleton when explicitly loading, on initial mount of large tables, or when actively searching
const showSkeleton = computed(() => {
  return props.loading || 
         isInitialLoading.value || 
         (isLargeDataset.value && (isTableSearching.value || isInternalSearching.value))
})

// Watch for filter changes from parent
watch(() => props.filterValue, (val) => {
  // Reset pagination index when search query changes
  if (pagination.value.pageIndex > 0) {
    table.setPageIndex(0)
  }

  if (isLargeDataset.value) {
    isInternalSearching.value = true
    if (filterDebounceTimer) clearTimeout(filterDebounceTimer)

    filterDebounceTimer = setTimeout(() => {
      if (props.filterKey) {
        table.getColumn(props.filterKey)?.setFilterValue(val)
      } else {
        table.setGlobalFilter(val)
      }
      setTimeout(() => {
        isInternalSearching.value = false
      }, 50)
    }, 150)
  } else {
    // For smaller datasets (<= 50 rows), filter immediately
    if (props.filterKey) {
      table.getColumn(props.filterKey)?.setFilterValue(val)
    } else {
      table.setGlobalFilter(val)
    }
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
        <!-- Skeleton Loading State (Triggered on initial load or searching large datasets > 50 rows) -->
        <template v-if="showSkeleton">
          <TableRow
            v-for="r in (props.skeletonRows || 5)"
            :key="`skeleton-row-${r}`"
            class="border-b border-border last:border-none"
          >
            <TableCell
              v-for="header in (table.getHeaderGroups()[0]?.headers || [])"
              :key="`skeleton-cell-${header.id}`"
              :class="['text-left py-3.5', props.cellClass]"
              :style="{ width: header.getSize() !== 150 ? `${header.getSize()}px` : undefined }"
            >
              <Skeleton class="h-4 w-full max-w-[160px] rounded" />
            </TableCell>
          </TableRow>
        </template>
        <template v-else-if="table.getRowModel().rows?.length">
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
