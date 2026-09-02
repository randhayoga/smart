<script setup lang="ts">
/**
 * CartItemCard.vue
 *
 * Renders an individual line item in the user's shopping basket or request cart
 * (used in `BorrowCart.vue` and `RequestCart.vue`).
 *
 * Key features:
 * - Checkbox for selecting items for batch actions or checkout.
 * - Image thumbnail with fallback placeholder and ambient highlight overlay.
 * - Dual-mode presentation:
 *   1. Generic subcategory request (when `barang_id` is null) with illustration notice.
 *   2. Specific inventory item request with brand, item name, specifications, and category tags.
 * - Localized numeric quantity stepper (id-ID) with min/max bounds.
 * - Destructive remove button emitting deletion request.
 * - Responsive layout: stacks controls vertically on mobile, aligns horizontally on desktop.
 * - Visual disabled state with dimmed opacity and disabled form controls.
 *
 * @emits update:selected - Triggered when the selection checkbox state is toggled.
 * @emits update:quantity - Triggered when the item quantity is altered via the number stepper.
 * @emits remove - Triggered when the trash removal button is clicked.
 */
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/Components/ui/number-field";

/**
 * Representation of an item within the user's shopping/borrow cart.
 */
interface CartItem {
  /** Unique cart entry ID. */
  id: number;
  /** Reference ID to the specific physical item in inventory, or null if requested generically by subcategory. */
  barang_id: number | null;
  /** Brand name of the item (e.g., 'Lenovo', 'PaperOne'). */
  brand: string;
  /** Specific item name or model designation. */
  name: string;
  /** Technical specifications or item attributes. */
  spec: string;
  /** Category identifier or slug. */
  category: string;
  /** Formatted category display name. */
  category_name: string;
  /** Formatted subcategory display name. */
  subcategory_name: string;
  /** Stock Keeping Unit (SKU) or inventory tracking code. */
  code: string;
  /** Total currently available inventory stock. */
  stock: number;
  /** Quantity of this item added to the cart. */
  quantity: number;
  /** Optional URL or media path to product photograph. */
  imageUrl?: string;
}

/**
 * Component Props Interface
 */
interface Props {
  /** The cart line item data object. */
  item: CartItem;
  /** Boolean indicating whether this item is currently selected for checkout. */
  selected: boolean;
  /** When true, disables selection checkbox, quantity stepper, and actions. */
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false
});

const emit = defineEmits<{
  /** Emitted when item selection changes. Payload: new boolean checked state. */
  (e: 'update:selected', val: boolean): void;
  /** Emitted when item quantity changes. Payload: updated integer quantity. */
  (e: 'update:quantity', val: number): void;
  /** Emitted when the user requests removal of this item from the cart. */
  (e: 'remove'): void;
}>();
</script>

<template>
  <div
    class="bg-card border rounded-[0.875rem] p-3 sm:p-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 transition-all duration-300 shadow-card"
    :class="[
      selected ? 'border-primary/50 bg-primary/5' : 'border-border',
      disabled ? 'opacity-60' : 'hover:shadow-card-hover hover:-translate-y-0.5'
    ]"
  >
    <!-- Top/Main Content Area: Checkbox, Image & Product Details -->
    <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
      <!-- Item Selection Checkbox -->
      <Checkbox 
        :model-value="selected"
        @update:model-value="(val) => emit('update:selected', !!val)"
        class="cursor-pointer shrink-0"
        :disabled="disabled"
      />

      <!-- Product Thumbnail Preview -->
      <div class="w-16 h-16 sm:w-24 sm:h-24 shrink-0 bg-muted rounded-[0.875rem] overflow-hidden flex items-center justify-center border border-border relative">
        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
        <img v-if="item.imageUrl" :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/media/' + item.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
        <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
      </div>

      <!-- Product Information -->
      <div class="flex-1 min-w-0 flex flex-col justify-center">
        <!-- Generic Subcategory Mode (when no specific item was chosen) -->
        <template v-if="!item.barang_id">
          <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">{{ item.subcategory_name }}</h3>
          <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">{{ item.category_name }}</p>
          <p class="text-[10px] sm:text-xs text-muted-foreground italic hidden sm:block">*foto hanya ilustrasi</p>
        </template>
        <!-- Specific Inventory Item Mode (with brand, model, and specs) -->
        <template v-else>
          <span v-if="item.brand && item.brand !== '-'" class="text-sm sm:text-base font-bold text-foreground leading-snug truncate">
            {{ item.brand }}
          </span>
          <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">
            {{ item.name }}{{ item.spec && item.spec !== '-' ? ' ' + item.spec : '' }}
          </h3>
          <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">
            {{ item.category_name }} ({{ item.subcategory_name }})
          </p>
        </template>
      </div>
    </div>

    <!-- Controls Area: Item Removal & Quantity Stepper -->
    <div class="flex items-center justify-between sm:justify-end gap-3 border-t sm:border-t-0 border-border pt-3 sm:pt-0">
      <!-- Item Removal Action Button -->
      <Button
        variant="ghost"
        size="icon"
        class="text-destructive hover:bg-destructive/10 hover:text-destructive flex-shrink-0 rounded-full"
        @click="emit('remove')"
        title="Remove from cart"
      >
        <Trash2 class="w-4 h-4" />
      </Button>

      <!-- Localized Quantity Stepper -->
      <div class="flex-shrink-0">
        <NumberField 
          :model-value="item.quantity" 
          @update:model-value="(val: number) => emit('update:quantity', val)"
          :min="1" 
          :max="999999" 
          locale="id-ID" 
          class="w-28 sm:w-32"
          :disabled="disabled"
        >
          <NumberFieldContent>
            <NumberFieldDecrement />
            <NumberFieldInput class="h-9 text-xs sm:text-sm" />
            <NumberFieldIncrement />
          </NumberFieldContent>
        </NumberField>
      </div>
    </div>
  </div>
</template>
