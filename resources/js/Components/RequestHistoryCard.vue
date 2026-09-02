<script setup lang="ts">
/**
 * RequestHistoryCard.vue
 *
 * Card component representing an asset borrow or consumable supply request within the
 * user's request history dashboard (`RequestHistory.vue`).
 *
 * Key features:
 * - Color-coded status badge reflecting the request's approval/fulfillment lifecycle.
 * - Prominent return deadline badge for currently borrowed assets.
 * - Dynamic composite thumbnail: renders a 2x2 image grid if multiple items are requested,
 *   or a single item thumbnail with uppercase code fallbacks.
 * - Summary metadata showing request number, corporate/project utilization purpose, and borrow duration.
 * - Collapsible drawer listing all requested items, brands, specifications, and quantities.
 * - Context-aware action buttons:
 *   - "Lihat Detail" navigation link.
 *   - "Batalkan Permintaan" (Cancel) button visible only while status is pending approval.
 *   - "Atur Serah Terima" (Handover setup) button when request is confirmed/partial.
 *   - "Atur Pengembalian" (Return setup) button when assets are currently on borrow.
 *
 * @emits cancel - Emitted when the user initiates cancellation of a pending request.
 */
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ChevronDown, ChevronUp, Trash2, Calendar } from 'lucide-vue-next';
import { formatDate } from '@/lib/utils';

// ─────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────

/**
 * Line item belonging to a user request or borrow.
 */
interface RequestItem {
  /** Unique item identifier. */
  id: number;
  /** Subcategory label (e.g., 'Laptop', 'Kabel LAN'). */
  subcategory: string;
  /** Brand name or manufacturer (e.g., 'Dell', 'Lenovo'). */
  brand: string;
  /** Optional specific item name. */
  name?: string;
  /** Technical specification or description. */
  spec: string;
  /** Requested quantity. */
  quantity: number;
  /** Available warehouse stock quantity at the time of request. */
  stockQuantity?: number;
  /** Storage path or URL to the item photo. */
  imageUrl?: string;
  /** Category classification name. */
  category: string;
  /** Unit of measurement (e.g., 'satuan', 'pcs', 'unit'). */
  uom?: string;
}

/**
 * Comprehensive request record containing header metadata and item lists.
 */
interface RequestHistory {
  /** Request database record ID. */
  id: number;
  /** Formatted tracking reference number (e.g., 'REQ-2026-001'). */
  number: string;
  /** Transaction type: 'permintaan' (supplies) or 'peminjaman' (asset loans). */
  type: 'permintaan' | 'peminjaman';
  /** Scope of asset utilization: internal company ('corporate') or project client ('project'). */
  pemanfaatan: 'corporate' | 'project';
  /** Additional utilization details or project title. */
  pemanfaatanDetail: string;
  /** Starting date/timestamp for borrow period (formatted string). */
  durationStart?: string;
  /** Ending deadline date/timestamp for borrow period (formatted string). */
  durationEnd?: string;
  /** Total calculated borrow duration in days. */
  durationDays?: number;
  /** Additional calculated borrow duration hours. */
  durationHours?: number;
  /** Localized human-readable status text displayed in badge. */
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan' | 'Pending' | 'Partial';
  /** Internal workflow state slug used for routing and conditional logic. */
  raw_status: 'wait' | 'approve' | 'confirm' | 'handover' | 'borrow' | 'return' | 'success' | 'reject' | 'cancel' | 'pending' | 'partial';
  /** ISO timestamp representing when the request was created. */
  created_at: string;
  approver_name?: string | null;
  /** List of line items included in this request. */
  items: RequestItem[];
}

/**
 * Component Props Interface
 */
const props = defineProps<{
  /** The request history data record. */
  request: RequestHistory;
}>();

const emit = defineEmits<{
  /** Emitted when the cancel button is clicked for a pending request. Payload: target request record. */
  (e: 'cancel', request: RequestHistory): void;
}>();

// ─────────────────────────────────────────────
// State & Collapsible Logic
// ─────────────────────────────────────────────

/** Reactive flag controlling the collapsible items list drawer. */
const isExpanded = ref(false);

/**
 * Toggles the visibility state of the items list drawer.
 */
const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value;
};

// ─────────────────────────────────────────────
// Status Styling Helper
// ─────────────────────────────────────────────

/**
 * Returns Tailwind CSS background and foreground color classes matching the given request status.
 *
 * @param status - Localized status string from the request record.
 * @returns Tailwind CSS utility class string for badge styling.
 */
const getStatusClasses = (status: string) => {
  switch (status) {
    case 'Menunggu approval':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
    case 'Disetujui':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300';
    case 'Selesai':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    case 'Serah Terima':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300';
    case 'Dipinjam':
      return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
    case 'Ditolak':
    case 'Dibatalkan':
      return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
    case 'Pending':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300';
    case 'Partial':
      return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-300';
    default:
      return 'bg-muted text-muted-foreground';
  }
};
</script>

<template>
  <div class="bg-card border border-border rounded-[0.875rem] p-3.5 sm:p-4 hover:border-primary/40 transition-all shadow-card hover:shadow-card-hover relative overflow-hidden">
    
    <!-- Header Section: Status Badge & Creation Date -->
    <div class="flex items-center justify-between gap-2 mb-3">
      <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
        <!-- Main Request Status Pill -->
        <span 
          class="text-xs font-semibold px-2.5 py-0.5 rounded-full inline-flex items-center"
          :class="getStatusClasses(request.status)"
        >
          {{ request.status === 'Disetujui' ? 'Di-approve' : (request.status === 'Dipinjam' ? 'Sedang dipinjam' : request.status) }}
        </span>

        <!-- Return Deadline Badge (Displayed only for active loans with scheduled end date) -->
        <span 
          v-if="request.status === 'Dipinjam' && request.durationEnd" 
          class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200/80 dark:bg-rose-950/30 dark:text-rose-300 dark:border-rose-800 inline-flex items-center gap-1"
        >
          <Calendar class="w-3 h-3" />
          <span>Tenggat: {{ request.durationEnd }}</span>
        </span>
      </div>

      <!-- Creation Timestamp -->
      <span class="text-xs text-muted-foreground font-medium shrink-0">
        Dibuat: {{ formatDate(request.created_at) }}
      </span>
    </div>

    <!-- Body Section: Thumbnail Preview & Request Details -->
    <div class="flex flex-col sm:flex-row gap-3.5 sm:gap-4 items-start">
      <!-- Thumbnail Image Container: 2x2 Grid for Multiple Items or Single Large Image -->
      <div class="shrink-0">
        <!-- 2x2 Grid (up to 4 items) -->
        <div 
          v-if="request.items.length > 1" 
          class="grid grid-cols-2 gap-1 w-16 h-16 sm:w-20 sm:h-20 rounded-[0.875rem] overflow-hidden bg-muted border border-border p-1"
        >
          <div 
            v-for="item in request.items.slice(0, 4)" 
            :key="item.id" 
            class="bg-background rounded-md overflow-hidden flex items-center justify-center border border-border/40 relative"
          >
            <img 
              v-if="item.imageUrl" 
              :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/media/' + item.imageUrl" 
              class="w-full h-full object-cover" 
            />
            <div v-else class="text-[9px] font-bold text-muted-foreground/50 select-none">
              {{ item.subcategory.substring(0, 2).toUpperCase() }}
            </div>
          </div>
        </div>
        
        <!-- Single Item Thumbnail -->
        <div 
          v-else 
          class="w-16 h-16 sm:w-20 sm:h-20 rounded-[0.875rem] bg-muted border border-border overflow-hidden flex items-center justify-center relative"
        >
          <img 
            v-if="request.items[0]?.imageUrl" 
            :src="request.items[0].imageUrl.startsWith('http') || request.items[0].imageUrl.startsWith('/') ? request.items[0].imageUrl : '/media/' + request.items[0].imageUrl" 
            class="w-full h-full object-cover" 
          />
          <div v-else class="text-xs font-bold text-muted-foreground/60 select-none">
            {{ request.items[0]?.subcategory.substring(0, 3).toUpperCase() }}
          </div>
        </div>
      </div>

      <!-- Request Details & Description -->
      <div class="flex-grow space-y-1 min-w-0">
        <!-- Request Number -->
        <h2 class="text-sm sm:text-base font-bold text-foreground truncate leading-snug">
          <span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}
        </h2>
        
        <!-- PIC Approval -->
        <p class="text-xs sm:text-sm text-foreground leading-normal">
          <span class="text-muted-foreground">PIC Approval:</span> 
          <span class="font-semibold ml-1">
            {{ request.approver_name || '-' }}
          </span>
        </p>

        <!-- Utilization Purpose Scope -->
        <p class="text-xs sm:text-sm text-foreground leading-normal">
          <span class="text-muted-foreground">Pemanfaatan:</span> 
          <span class="font-semibold ml-1">
            {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
          </span>
        </p>

        <!-- Borrow Duration (Visible only for borrow requests) -->
        <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-xs sm:text-sm text-foreground leading-normal">
          <span class="text-muted-foreground">Durasi:</span>
          <span class="font-medium ml-1">
            <template v-if="request.durationEnd">
              {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours }} jam)
            </template>
            <template v-else>
              {{ request.durationStart }} s.d. - (Tanpa Tenggat Waktu)
            </template>
          </span>
        </p>

        <!-- Collapsible Items Toggle Button -->
        <div class="pt-0.5">
          <button
            @click="toggleExpanded"
            class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 transition-all cursor-pointer"
          >
            <span>{{ isExpanded ? 'Sembunyikan barang' : 'Lihat barang' }} ({{ request.items.length }})</span>
            <ChevronUp v-if="isExpanded" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>

          <!-- Collapsible Items List Drawer -->
          <div 
            v-if="isExpanded" 
            class="mt-2.5 bg-muted/40 border border-border p-3 rounded-[0.875rem] space-y-1 text-xs animate-in fade-in slide-in-from-top-1 duration-200"
          >
            <p class="font-semibold text-foreground">Daftar barang:</p>
            <ul class="space-y-0.5 pl-0.5">
              <li 
                v-for="item in request.items" 
                :key="item.id" 
                class="text-muted-foreground font-medium flex items-center gap-1.5"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-primary/60 shrink-0"></span>
                <template v-if="(item.brand && item.brand !== '-') || (item.name && item.name !== 'Tidak Spesifik') || item.spec">
                  <span class="text-foreground font-semibold">{{ item.subcategory }}:</span>
                  <span>{{ item.brand !== '-' ? item.brand : '' }} {{ item.name && item.name !== 'Tidak Spesifik' ? item.name : '' }} {{ item.spec }}</span>
                  <span class="text-foreground font-medium shrink-0">({{ item.quantity }} {{ item.uom || 'satuan' }})</span>
                </template>
                <template v-else>
                  <span class="text-foreground font-semibold">{{ item.subcategory }}</span>
                  <span class="text-foreground font-medium shrink-0">({{ item.quantity }} {{ item.uom || 'satuan' }})</span>
                </template>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Action Buttons Bar -->
    <div class="flex items-center justify-end gap-2.5 mt-3 pt-2 border-t border-border/60">
      <!-- Detail View Link -->
      <Link
        :href="route('smart.history.show', request.id)"
        class="text-xs sm:text-sm font-semibold text-primary hover:underline transition-colors mr-1"
      >
        Lihat Detail
      </Link>

      <!-- Cancel Request Button (Visible only while awaiting approval) -->
      <Button
        v-if="request.status === 'Menunggu approval'"
        variant="destructive"
        size="sm"
        class="font-semibold text-xs flex items-center gap-1.5 h-8 px-3"
        @click="emit('cancel', request)"
      >
        <Trash2 class="w-3.5 h-3.5" />
        Batalkan Permintaan
      </Button>

      <!-- Handover Setup CTA (Visible when confirmed or partial) -->
      <Link
        v-if="request.raw_status === 'confirm' || request.raw_status === 'partial'"
        :href="route('smart.history.show', request.id)"
      >
        <Button
          variant="primary"
          size="sm"
          class="font-semibold text-xs h-8 px-3.5"
        >
          Atur Serah Terima
        </Button>
      </Link>

      <!-- Return Setup CTA (Visible when currently borrowed) -->
      <Link
        v-if="request.raw_status === 'borrow'"
        :href="route('smart.history.show', request.id)"
      >
        <Button
          variant="primary"
          size="sm"
          class="font-semibold text-xs h-8 px-3.5"
        >
          Atur Pengembalian
        </Button>
      </Link>
    </div>

  </div>
</template>

<style scoped>
/* Smooth expand animation for collapsible items list drawer */
.animate-in {
  animation-duration: 200ms;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
</style>

