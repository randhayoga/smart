<script setup lang="ts">
/**
 * AssetItemCard.vue
 *
 * Unified reusable card component for presenting requested or allocated assets
 * and supplies across various views (Admin Fulfillment, Admin Confirmation Modal,
 * User Request History, Handover, and Returns).
 *
 * Key features:
 * - Thumbnail image with automatic path normalization or 3-letter abbreviation fallback.
 * - Brand, category, and subcategory display with duplicate label reduction.
 * - Status indicator for fulfilled items (customized for consumable vs. non-consumable).
 * - Real-time warehouse stock level badge (visible to admin users).
 * - Quantity display with customizable label and unit of measurement (UOM).
 * - Interactive non-consumable allocation accordion with color-coded slot pills and action button.
 * - Interactive consumable allocation accordion with lot breakdown and fulfilled/requested count badge.
 * - Dynamic `#footer` slot support with empty-VNode detection to prevent empty border rendering.
 *
 * @slot footer - Optional action or button bar rendered at the bottom of the card.
 */
import { ref, computed, useSlots, Comment, Fragment, type VNode } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

export interface AllocationSlot {
  slot_number: number;
  asset_number: string | null;
  color: 'purple' | 'green' | 'red' | string;
}

export interface LotFulfillment {
  fulfillment_id?: string | number;
  lot_number: string;
  quantity_fulfilled: number;
  brand_name: string;
  item_name: string;
  specification?: string | null;
  storage_location: string;
}

export interface ConsumableSummary {
  quantity_fulfilled: number;
  is_fully_fulfilled: boolean;
}

export interface Props {
  /** Brand or product title (e.g., "Lenovo ThinkPad", "Baterai AA"). */
  brand?: string;
  /** Product specific model/name. */
  name?: string;
  /** Product technical specification. */
  spec?: string;
  /** Primary category name (e.g., "IT Equipment", "Office Supplies"). */
  category?: string;
  /** Subcategory name (e.g., "Laptop", "Battery"). */
  subcategory?: string;
  /** Quantity requested or allocated. */
  quantity?: string | number;
  /** Display label for the quantity row. Defaults to 'Jumlah diminta'. */
  quantityLabel?: string;
  /** URL or relative storage path for the product image thumbnail. */
  imageUrl?: string | null;
  /** Unit of measurement label (e.g., 'unit', 'satuan', 'pcs'). Defaults to 'satuan'. */
  uom?: string;
  /** Fulfillment status string (e.g., 'fulfilled'). */
  status?: string;
  /** Current warehouse stock quantity. Null if stock tracking is unavailable. */
  stock?: number | null;
  /** Flag to determine if the viewer has admin privileges to view stock availability. */
  isAdmin?: boolean;
  /** Indicates whether the item is consumable supply rather than fixed asset. */
  isConsumable?: boolean;

  /** Simple array of asset serial numbers or identification codes (legacy / fallback). */
  assets?: string[];

  /** Non-consumable allocation slots for interactive fulfillment view. */
  allocationSlots?: AllocationSlot[];
  /** Whether to show the action button for non-consumable allocation (e.g., 'Pilih Alokasi Aset'). */
  showAllocationAction?: boolean;
  /** Label for non-consumable allocation button. Defaults to 'Pilih Alokasi Aset'. */
  allocationActionLabel?: string;

  /** Consumable LOT fulfillments list. */
  lotFulfillments?: LotFulfillment[];
  /** Consumable summary info for progress badge. */
  consumableSummary?: ConsumableSummary;

  /** Initial open state for accordion sections. Defaults to true. */
  defaultOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  brand: '',
  name: '',
  spec: '',
  category: '',
  subcategory: '',
  quantity: 1,
  quantityLabel: 'Jumlah diminta',
  imageUrl: null,
  uom: 'satuan',
  status: undefined,
  stock: null,
  isAdmin: false,
  isConsumable: false,
  assets: () => [],
  allocationSlots: undefined,
  showAllocationAction: false,
  allocationActionLabel: 'Pilih Alokasi Aset',
  lotFulfillments: undefined,
  consumableSummary: undefined,
  defaultOpen: true,
});

defineEmits<{
  (e: 'selectAllocation'): void;
}>();

const slots = useSlots();

/** Controls the visibility of accordion sections */
const showAssets = ref(props.defaultOpen);
const showAssetAllocation = ref(props.defaultOpen);
const showConsumableAllocation = ref(props.defaultOpen);

/**
 * Recursively inspects VNodes to verify if slot content contains actual renderable elements
 * rather than empty fragments, whitespace, or comments.
 */
const hasRenderableContent = (nodes: VNode[] | undefined): boolean => {
  if (!nodes || nodes.length === 0) return false;
  return nodes.some(node => {
    if (!node) return false;
    if (node.type === Comment) return false;
    if (node.type === Fragment && Array.isArray(node.children)) {
      return hasRenderableContent(node.children as VNode[]);
    }
    return true;
  });
};

/**
 * Determines whether the footer slot contains visible content to avoid rendering
 * an empty divider border at the bottom of the card.
 */
const hasFooter = computed(() => {
  if (!slots.footer) return false;
  return hasRenderableContent(slots.footer());
});

/**
 * Formats the product display title based on available brand, name, spec, or subcategory.
 */
const displayTitle = computed(() => {
  if (props.brand && !props.name && !props.spec) {
    return props.brand;
  }
  const brand = props.brand && props.brand !== '-' ? props.brand : '';
  const name = props.name && props.name !== 'Tidak Spesifik' ? props.name : '';
  const spec = props.spec && props.spec !== '-' ? props.spec : '';
  const parts = [brand, name, spec].filter(Boolean);
  if (parts.length > 0) return parts.join(' ');
  return props.subcategory || 'Barang';
});

/**
 * Sanitizes and filters the assets array to return only non-empty strings.
 */
const activeAssets = computed(() => {
  return props.assets ? props.assets.filter(asset => asset && String(asset).trim() !== '') : [];
});
</script>

<template>
  <div 
    class="p-4 border transition-all rounded-[14px] space-y-3 bg-card border-border/70 hover:border-primary/20 hover:bg-muted/5"
  >
    <!-- Item Header & Info Row -->
    <div class="flex items-start gap-4">
      <!-- Item Thumbnail / Fallback Abbreviation -->
      <div class="w-16 h-16 rounded-[12px] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center mt-0.5">
        <img 
          v-if="imageUrl" 
          :src="imageUrl.startsWith('http') || imageUrl.startsWith('/') ? imageUrl : '/media/' + imageUrl" 
          class="w-full h-full object-cover" 
        />
        <div v-else class="text-sm font-black text-muted-foreground/50 select-none">
          {{ (subcategory || brand || 'ITM').substring(0, 3).toUpperCase() }}
        </div>
      </div>
      
      <!-- Item Details and Status -->
      <div class="min-w-0 flex-grow space-y-1">
        <!-- Brand & Fulfillment Badge -->
        <div class="flex items-center gap-2 flex-wrap mb-0.5">
          <h4 class="text-sm md:text-base font-bold text-foreground truncate">{{ displayTitle }}</h4>
          <span 
            v-if="status === 'fulfilled'" 
            class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-[10px] font-bold text-green-600 ring-1 ring-inset ring-green-500/20"
          >
            {{ isConsumable ? $t('requests.alreadyProvided') : $t('requests.alreadyAllocated') }}
          </span>
        </div>

        <!-- Category / Subcategory Hierarchy -->
        <p class="text-xs text-muted-foreground">
          <template v-if="subcategory && category && displayTitle !== subcategory && !displayTitle.toLowerCase().includes(subcategory.toLowerCase())">
            {{ $t('requests.categoryLabel') }} {{ category }} ({{ subcategory }})
          </template>
          <template v-else-if="category">
            {{ $t('requests.categoryLabel') }} {{ category }}
          </template>
        </p>

        <!-- Warehouse Stock Indicator (Admin only) -->
        <div v-if="stock !== null && isAdmin" class="text-xs font-semibold text-foreground">
          {{ $t('requests.availableStock') }} 
          <span :class="stock >= Number(quantity) ? 'text-green-600' : 'text-red-500'">
            {{ stock }} {{ uom }}
          </span>
        </div>

        <!-- Quantity & Unit of Measurement -->
        <p class="text-xs text-foreground font-semibold">{{ quantityLabel ? quantityLabel : $t('requests.requestedQuantity') }}: {{ quantity }} {{ uom }}</p>
        
        <!-- Legacy / Simple Collapsible Allocated Asset Serial Numbers -->
        <div v-if="!allocationSlots && activeAssets.length > 0" class="pt-1.5">
          <button 
            type="button"
            @click="showAssets = !showAssets"
            class="text-xs font-bold text-[#6366F1] hover:text-[#5558EB] flex items-center gap-1 transition-colors focus:outline-none"
          >
            <span>{{ showAssets ? $t('requests.hideAssetAllocation') : $t('requests.viewAssetAllocation') }}</span>
            <ChevronUp v-if="showAssets" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>

          <!-- Expanded Asset List -->
          <div 
            v-if="showAssets" 
            class="mt-2 pl-3 py-1 space-y-1 animate-in fade-in slide-in-from-top-1 duration-200"
          >
            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">{{ $t('requests.assetsLabel') }}</p>
            <ul class="space-y-1">
              <li v-for="(asset, idx) in activeAssets" :key="idx" class="text-xs text-foreground font-semibold flex items-center gap-1.5">
                <span class="w-1 h-1 rounded-full bg-foreground shrink-0"></span>
                <span>{{ asset }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         NON-CONSUMABLE (FIXED ASSET) ALLOCATION ACCORDION
         ═══════════════════════════════════════════════ -->
    <div v-if="allocationSlots && allocationSlots.length > 0" class="pt-2 border-t border-border/60 space-y-3">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <button 
          type="button"
          @click="showAssetAllocation = !showAssetAllocation"
          class="text-xs font-bold text-[#6366F1] hover:text-[#5558EB] flex items-center gap-1.5 transition-colors focus:outline-none"
        >
          <span>{{ showAssetAllocation ? $t('requests.hideAssetAllocation') : $t('requests.viewAssetAllocation') }}</span>
          <ChevronUp v-if="showAssetAllocation" class="w-3.5 h-3.5" />
          <ChevronDown v-else class="w-3.5 h-3.5" />
        </button>

        <!-- Action Button: e.g. "Pilih Alokasi Aset" -->
        <Button 
          v-if="showAllocationAction"
          type="button"
          @click="$emit('selectAllocation')"
          variant="primary"
          size="sm"
          class="text-xs font-semibold h-8 px-3.5 bg-[#4F46E5] hover:bg-[#4338CA] text-white"
        >
          {{ allocationActionLabel || $t('requests.chooseAssetAllocation') }}
        </Button>
      </div>

      <!-- Numbered and Color-Coded Slot List (Purple/Green/Red) -->
      <div 
        v-if="showAssetAllocation" 
        class="space-y-2 pt-1 animate-in fade-in duration-200"
      >
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div 
            v-for="slot in allocationSlots" 
            :key="slot.slot_number"
            class="flex items-center justify-between p-2.5 rounded-lg border text-xs transition-colors"
            :class="[
              slot.color === 'purple' 
                ? 'bg-purple-50 text-purple-800 border-purple-200 dark:bg-purple-950/30 dark:text-purple-300 dark:border-purple-800' 
                : slot.color === 'green'
                  ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-800'
                  : 'bg-red-50 text-red-800 border-red-200 dark:bg-red-950/30 dark:text-red-300 dark:border-red-800'
            ]"
          >
            <div class="flex items-center gap-2">
              <span class="font-bold font-mono">{{ slot.slot_number }}.</span>
              <span v-if="slot.asset_number" class="font-semibold font-mono">{{ slot.asset_number }}</span>
              <span v-else class="italic font-medium">{{ $t('requests.notYetAllocated') }}</span>
            </div>
            <span 
              class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded"
              :class="[
                slot.color === 'purple'
                  ? 'bg-purple-200/60 text-purple-900 dark:bg-purple-900/50 dark:text-purple-200'
                  : slot.color === 'green'
                    ? 'bg-emerald-200/60 text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200'
                    : 'bg-red-200/60 text-red-900 dark:bg-red-900/50 dark:text-red-200'
              ]"
            >
              {{ slot.color === 'purple' ? $t('requests.allocated') : (slot.color === 'green' ? $t('requests.borrowed') : $t('requests.emptySlot')) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         CONSUMABLE (HABIS PAKAI) ALLOCATION ACCORDION
         ═══════════════════════════════════════════════ -->
    <div v-else-if="isConsumable && (lotFulfillments !== undefined || consumableSummary !== undefined)" class="pt-2 border-t border-border/60 space-y-2">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <!-- Left Side: Summary Badge & Accordion Toggle -->
        <div class="flex flex-col items-start gap-1">
          <span 
            v-if="consumableSummary"
            class="px-2 py-0.5 rounded text-[11px] font-semibold"
            :class="consumableSummary.is_fully_fulfilled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'"
          >
            {{ consumableSummary.quantity_fulfilled || 0 }} / {{ quantity }} {{ uom }}
          </span>

          <button 
            type="button"
            @click="showConsumableAllocation = !showConsumableAllocation"
            class="text-xs font-bold text-[#6366F1] hover:text-[#5558EB] flex items-center gap-1.5 transition-colors focus:outline-none"
          >
            <span>{{ showConsumableAllocation ? $t('requests.hideStockAllocation') : $t('requests.viewStockAllocation') }}</span>
            <ChevronUp v-if="showConsumableAllocation" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Action Button: Pilih Alokasi -->
        <Button 
          v-if="showAllocationAction"
          type="button"
          @click="$emit('selectAllocation')"
          variant="primary"
          size="sm"
          class="text-xs font-semibold h-8 px-3.5 bg-[#4F46E5] hover:bg-[#4338CA] text-white"
        >
          {{ $t('requests.chooseAllocation') }}
        </Button>
      </div>

      <!-- Collapsible Lot Fulfillments Breakdown -->
      <div 
        v-if="showConsumableAllocation" 
        class="space-y-2 pt-1 animate-in fade-in duration-200"
      >
        <div v-if="lotFulfillments && lotFulfillments.length > 0" class="space-y-2">
          <div 
            v-for="(lot, lIdx) in lotFulfillments" 
            :key="lot.fulfillment_id || lIdx"
            class="p-3 rounded-lg border border-border bg-muted/20 text-xs space-y-1.5"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="font-bold text-foreground text-sm font-mono">
                <span class="text-muted-foreground font-sans text-xs font-normal">{{ $t('requests.lotCodeLabel') }}</span>{{ lot.lot_number }}
              </div>
              <span class="font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800 text-[11px]">
                {{ lot.quantity_fulfilled }} {{ uom }}
              </span>
            </div>
            <div class="text-foreground">
              <span class="text-muted-foreground">{{ $t('requests.physicalItemLabel') }}</span>
              <span class="font-semibold">{{ lot.brand_name }} {{ lot.item_name }}</span>
              <span v-if="lot.specification" class="text-muted-foreground font-normal ml-1">({{ lot.specification }})</span>
            </div>
            <div class="text-xs text-muted-foreground">
              <span>{{ $t('requests.storageLocationLabel') }}</span>
              <span class="font-medium text-foreground">{{ lot.storage_location }}</span>
            </div>
          </div>
        </div>
        <div v-else class="p-3 rounded-lg border border-dashed border-border bg-muted/10 text-xs text-muted-foreground text-center">
          {{ $t('requests.insufficientStock') }}
        </div>
      </div>
    </div>

    <!-- Card Action Footer Slot -->
    <div v-if="hasFooter" class="flex justify-end pt-2 border-t border-border/50">
      <slot name="footer" />
    </div>
  </div>
</template>
