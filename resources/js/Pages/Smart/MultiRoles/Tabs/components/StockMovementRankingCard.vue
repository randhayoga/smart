<script setup lang="ts">
/**
 * Stock Movement Ranking Card
 * Displays the system-sorted ranking of consumable stock-out movements by Category, Type,
 * Model, and Department/Project destination with merged cells (rowspan) for unbroken hierarchy.
 */
import { useI18n } from 'vue-i18n';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

export interface RankingRow {
  category_name: string;
  category_row_span: number;
  type_name: string;
  type_row_span: number;
  model_name: string;
  model_row_span: number;
  destination: string;
  request_count: number;
  stock_out_qty: number;
}

interface Props {
  ranking: RankingRow[];
  isLoading?: boolean;
}

withDefaults(defineProps<Props>(), {
  ranking: () => [],
  isLoading: false,
});

const { t } = useI18n();
</script>

<template>
  <div class="p-6 bg-card rounded-xl border border-border shadow-sm space-y-4">
    <!-- Header Title -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-foreground">
          {{ t('admin.stockMovementRankingTitle') }}
        </h2>
      </div>
    </div>

    <!-- Table Container -->
    <div class="rounded-lg border border-border overflow-hidden bg-card">
      <Table>
        <TableHeader>
          <TableRow class="bg-muted/50 hover:bg-transparent border-b border-border">
            <TableHead class="w-1/6 h-10 text-sm font-semibold text-foreground text-left px-2 border-r border-border/60">
              {{ t('admin.rankingCategory') }}
            </TableHead>
            <TableHead class="w-1/6 h-10 text-sm font-semibold text-foreground text-left px-2 border-r border-border/60">
              {{ t('admin.rankingType') }}
            </TableHead>
            <TableHead class="w-1/6 h-10 text-sm font-semibold text-foreground text-left px-2 border-r border-border/60">
              {{ t('admin.rankingModel') }}
            </TableHead>
            <TableHead class="w-1/4 h-10 text-sm font-semibold text-foreground text-left px-2 border-r border-border/60">
              {{ t('admin.rankingDepartmentsProjects') }}
            </TableHead>
            <TableHead class="w-1/12 h-10 text-sm font-semibold text-foreground text-right px-2 border-r border-border/60">
              {{ t('admin.rankingRequest') }}
            </TableHead>
            <TableHead class="w-1/12 h-10 text-sm font-semibold text-foreground text-right px-2">
              {{ t('admin.rankingStockOut') }}
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <template v-if="ranking && ranking.length > 0">
            <TableRow
              v-for="(row, idx) in ranking"
              :key="idx"
              class="hover:bg-muted/30 transition-colors border-b border-border/60"
            >
              <!-- Category Cell (Row Spanned) -->
              <TableCell
                v-if="row.category_row_span > 0"
                :rowspan="row.category_row_span"
                class="align-top font-semibold text-foreground border-r border-border/80 px-2 py-2.5"
              >
                {{ row.category_name }}
              </TableCell>

              <!-- Type Cell (Row Spanned) -->
              <TableCell
                v-if="row.type_row_span > 0"
                :rowspan="row.type_row_span"
                class="align-top font-medium text-foreground border-r border-border/80 px-2 py-2.5"
              >
                {{ row.type_name }}
              </TableCell>

              <!-- Model Cell (Row Spanned) -->
              <TableCell
                v-if="row.model_row_span > 0"
                :rowspan="row.model_row_span"
                class="align-top font-medium text-foreground border-r border-border/80 px-2 py-2.5"
              >
                {{ row.model_name }}
              </TableCell>

              <!-- Destination Cell -->
              <TableCell class="align-middle text-muted-foreground border-r border-border/60 px-2 py-2.5">
                {{ row.destination }}
              </TableCell>

              <!-- Total Requests -->
              <TableCell class="align-middle text-right text-muted-foreground border-r border-border/60 px-2 py-2.5 text-sm">
                {{ row.request_count.toLocaleString() }}
              </TableCell>

              <!-- Total Stock Out -->
              <TableCell class="align-middle text-right font-semibold text-rose-600 px-2 py-2.5 text-sm">
                {{ row.stock_out_qty.toLocaleString() }}
              </TableCell>
            </TableRow>
          </template>

          <!-- Empty State -->
          <TableEmpty v-else :colspan="6">
            <div class="py-8 text-center text-muted-foreground text-sm">
              {{ t('admin.noRankingData') }}
            </div>
          </TableEmpty>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
