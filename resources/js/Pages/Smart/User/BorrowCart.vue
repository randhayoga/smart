<script setup lang="ts">
/**
 * Borrow Cart Page
 * Manages loanable asset items in the user's shopping basket.
 * Requires selecting a start date/time before picking items to proceed to confirmation.
 */
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import CartItemCard from '@/Components/CartItemCard.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { formatItemDisplayName } from '@/lib/utils';

// --- Data Types & Props ---
interface Props {
  user?: any;
  cartItems?: CartItem[];
  defaultStartDate?: string;
  defaultStartTime?: string;
  defaultEndDate?: string;
  defaultEndTime?: string;
}

const props = withDefaults(defineProps<Props>(), {
  cartItems: () => [],
  defaultStartDate: '',
  defaultStartTime: '',
  defaultEndDate: '',
  defaultEndTime: '',
});

interface CartItem {
  id: number;
  barang_id: number | null;
  brand: string;
  name: string;
  spec: string;
  category: string;
  category_name: string;
  subcategory_name: string;
  code: string;
  stock: number;        // Stock calculation deprecated; loans can be requested regardless of stock
  quantity: number;     // Requested quantity
  selected: boolean;
  imageUrl?: string;
  uom?: string;
}

// --- Date & Time Helper Functions ---

/** Get client local date in YYYY-MM-DD format */
const getClientDefaultDate = () => {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
};

/** Get client local time in HH:MM format */
const getClientDefaultTime = () => {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2, '0');
  const min = String(now.getMinutes()).padStart(2, '0');
  return `${hh}:${min}`;
};

/** Open native date/time picker on input click */
const handlePickerClick = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target && typeof target.showPicker === 'function') {
    target.showPicker();
  }
};

// --- Borrow Schedule State ---
// Start date is required before selecting items
const startDate = ref(getClientDefaultDate());
const startTime = ref(getClientDefaultTime());
const endDate   = ref('');
const endTime   = ref('');

// --- Cart Items State ---
const cartItems = ref<CartItem[]>(props.cartItems.map(item => ({ ...item, selected: false })));

watch(() => props.cartItems, (newVal) => {
  const selectedMap = new Map(cartItems.value.map(i => [i.id, i.selected]));
  cartItems.value = newVal.map(item => ({
    ...item,
    selected: selectedMap.get(item.id) || false
  }));
});

/** Whether a valid start date has been provided */
const isDateSelected = computed(() => startDate.value && startDate.value.trim() !== '');

/** Two-way computed property for "Select All" checkbox */
const isAllSelected = computed({
  get() {
    if (cartItems.value.length === 0) return false;
    return cartItems.value.every(item => item.selected);
  },
  set(val: boolean) {
    if (!isDateSelected.value) return;
    cartItems.value.forEach(item => {
      item.selected = val;
    });
  }
});

// If start date becomes later than end date, reset end date
watch(startDate, (newStart) => {
  if (endDate.value && endDate.value < newStart) {
    endDate.value = '';
    endTime.value = '';
  }
});

watch(endDate, (newVal) => {
  if (!newVal || newVal.trim() === '') {
    endTime.value = '';
  }
});

const filteredItems = computed(() => cartItems.value);

/** Items currently selected for borrowing */
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

/** Validation to enable the "Proceed to Confirmation" action */
const canProceed = computed(() => isDateSelected.value && selectedItems.value.length > 0);

// --- Cart Actions ---

/** Remove an item from the borrow basket */
const removeItem = (id: number) => {
  router.delete(route('smart.borrow-cart.destroy', id), {
    preserveScroll: true,
  });
};

/** Update requested quantity for a specific basket item */
const updateQty = (item: CartItem, value: number) => {
  const clamped = Math.max(1, Math.min(999999, Math.floor(value || 1)));
  router.put(route('smart.borrow-cart.update', item.id), {
    quantity: clamped
  }, {
    preserveScroll: true,
  });
};

/** Navigate to confirmation page with selected item IDs and schedule */
const handleProceed = () => {
  const ids = selectedItems.value.map(i => i.id).join(',');
  router.get(route('smart.borrow-cart.confirmation'), {
    ids,
    start_date: startDate.value,
    start_time: startTime.value,
    end_date: endDate.value,
    end_time: endTime.value,
  });
};
</script>

<template>
  <AppLayout title="Keranjang Peminjaman">
    <!-- Page Title -->
    <div class="mb-2 flex flex-row items-center justify-between sm:flex-col sm:items-start">
      <div class="min-w-0">
        <h1 class="text-lg font-bold text-gray-900 leading-none">Keranjang Peminjaman</h1>
        <p class="text-sm text-muted-foreground mt-2 hidden sm:block">Pilih tanggal peminjaman lalu pilih barang-barang yang ingin dimasukkan dalam peminjaman.</p>
      </div>

      <!-- Pilih Semua Checkbox -->
      <div 
        class="items-center gap-2 bg-card border border-border px-3 py-1.5 sm:py-2 rounded-lg w-fit sm:mt-3 transition-opacity duration-200"
        :class="[
          cartItems.length === 0 
            ? 'hidden sm:flex opacity-50 cursor-not-allowed select-none' 
            : 'flex',
          (!isDateSelected && cartItems.length > 0)
            ? 'opacity-50 cursor-not-allowed' 
            : ''
        ]"
      >
        <Checkbox 
          id="select-all" 
          :model-value="isAllSelected"
          @update:model-value="(val) => isAllSelected = !!val"
          :disabled="!isDateSelected || cartItems.length === 0"
          class="cursor-pointer disabled:cursor-not-allowed"
        />
        <label 
          for="select-all" 
          class="text-sm font-medium text-foreground select-none"
          :class="(!isDateSelected || cartItems.length === 0) ? 'cursor-not-allowed text-muted-foreground' : 'cursor-pointer'"
        >
          Pilih Semua
        </label>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row sm:gap-6 mt-3 pb-20 lg:pb-0">
      <!-- ============================================================ -->
      <!-- Left Column: Date Form + Items List                          -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- === Borrow Date Block (Must be selected first) === -->
        <div class="bg-card border border-border rounded-[0.875rem] p-5">
          <h2 class="text-base font-bold text-foreground mb-3">Tanggal Peminjaman</h2>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
            <!-- Start Date -->
            <div class="space-y-1 order-1 sm:order-1">
              <label class="text-sm font-medium text-foreground">
                <span class="sm:hidden">Mulai</span>
                <span class="hidden sm:inline">Tanggal mulai</span><span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startDate"
                type="date"
                :min="getClientDefaultDate()"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- Start Time -->
            <div class="space-y-1 order-3 sm:order-2">
              <label class="text-sm font-medium text-foreground hidden sm:block">
                Waktu mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startTime"
                type="time"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- End Date -->
            <div class="space-y-1 order-2 sm:order-3">
              <label class="text-sm font-medium text-muted-foreground">
                <span class="sm:hidden">Selesai</span>
                <span class="hidden sm:inline">Tanggal selesai</span>
              </label>
              <Input
                v-model="endDate"
                type="date"
                :min="startDate || getClientDefaultDate()"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- End Time -->
            <div class="space-y-1 order-4 sm:order-4">
              <label class="text-sm font-medium text-muted-foreground hidden sm:block">Waktu selesai</label>
              <Input
                v-model="endTime"
                type="time"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!endDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>
          </div>

          <!-- Helper message -->
          <div class="text-xs sm:text-sm italic hidden sm:flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mt-3">
            <p class="text-destructive font-medium">*harus diisi</p>
            <p class="text-muted-foreground">tanggal &amp; waktu selesai dapat dikosongkan</p>
          </div>
        </div>

        <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-26.23rem)] sm:h-[calc(100vh-27rem)]">
          <div class="p-3 sm:p-5">
            <div class="space-y-3">
              <!-- Message if empty -->
              <div v-if="filteredItems.length === 0" class="text-center py-10">
                <p class="text-muted-foreground text-sm">Keranjang kosong.</p>
              </div>

              <!-- Item Card -->
              <CartItemCard
                v-for="item in filteredItems"
                :key="item.id"
                :item="item"
                v-model:selected="item.selected"
                :disabled="!isDateSelected"
                @remove="removeItem(item.id)"
                @update:quantity="(qty) => updateQty(item, qty)"
              />
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Right Column: Borrow Summary (sticky)                        -->
      <!-- ============================================================ -->
      <div class="hidden lg:block lg:w-96 xl:w-[28rem] 2xl:w-[30rem] flex-shrink-0">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- List of selected items -->
          <div class="space-y-3 mb-6">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              {{ !isDateSelected ? 'Pilih tanggal peminjaman terlebih dahulu.' : 'Belum ada barang yang dipilih.' }}
            </div>
            <div
              v-for="item in selectedItems"
              :key="item.id"
              class="flex items-center justify-between gap-2"
            >
              <span class="text-base text-foreground font-medium truncate flex-1">
                {{ formatItemDisplayName(item) }}
              </span>
              <span class="text-base text-muted-foreground flex-shrink-0 whitespace-nowrap">
                {{ item.quantity }} {{ item.uom || 'satuan' }}
              </span>
            </div>
          </div>

          <hr class="border-border mb-5" />

          <!-- Proceed to Confirmation Button -->
          <!-- Disabled if: start date is not filled OR no items are selected -->
          <Button
            variant="primary"
            size="lg"
            class="w-full"
            :disabled="!canProceed"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>

          <!-- Contextual hint -->
          <p v-if="!isDateSelected" class="text-xs text-muted-foreground text-center mt-3">
            Pilih tanggal mulai terlebih dahulu.
          </p>
        </div>
      </div>

      <!-- Mobile Sticky Bottom Footer -->
      <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-card border-t border-border px-4 py-3 shadow-lg flex items-center justify-between pb-safe">
        <div class="flex flex-col">
          <span class="text-xs text-muted-foreground font-medium">Total:</span>
          <span class="text-sm font-bold text-foreground">
            {{ selectedItems.length }} jenis barang
          </span>
        </div>
        <Button
          variant="primary"
          size="default"
          class="px-6 rounded-xl"
          :disabled="!canProceed"
          @click="handleProceed"
        >
          Konfirmasi
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
