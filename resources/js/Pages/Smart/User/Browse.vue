<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';
import TableSearch from '@/Components/TableSearch.vue';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  ChevronDown,
  X,
  Calendar,
  AlertCircle,
  CheckCircle2,
  ShoppingCart,
  Plus,
  Minus
} from 'lucide-vue-next';

interface Category {
  id: number;
  name: string;
  code: string;
}

interface Barang {
  id: number;
  code: string;
  name: string;
  brand: string;
  specification: string;
  category_id: number;
  category: string;
  subcategory: string;
  type: 'asset' | 'consumable';
  is_consumable: boolean;
  amount: number;
  unit: string;
}

interface Props {
  user: {
    name: string;
    email: string;
  };
  barangs: Barang[];
  categories: Category[];
}

const props = defineProps<Props>();

// ── Search & Filter State ──────────────────────────────────────
const searchQuery = ref('');
const selectedCategory = ref<number | ''>('');
const sortOrder = ref('A-Z');
const availableOnly = ref(false);

// ── Toast Notification State ──────────────────────────────────
const toastMessage = ref('');
const toastType = ref<'success' | 'error'>('success');
const showToast = ref(false);

const triggerToast = (message: string, type: 'success' | 'error' = 'success') => {
  toastMessage.value = message;
  toastType.value = type;
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 5000);
};

// Listen to flash messages from Laravel redirect session
const pageProps = usePage();
watch(() => pageProps.props.flash, (flash: any) => {
  if (flash && flash.success) {
    triggerToast(flash.success, 'success');
  }
  if (flash && flash.error) {
    triggerToast(flash.error, 'error');
  }
}, { deep: true, immediate: true });

// ── Filter & Sorting Logic ────────────────────────────────────
const filteredBarangs = computed(() => {
  let list = [...props.barangs];

  // 1. Text Search (Brand, Specification, Code)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(b => 
      b.brand.toLowerCase().includes(q) || 
      b.specification.toLowerCase().includes(q) ||
      b.code.toLowerCase().includes(q)
    );
  }

  // 2. Category Filter
  if (selectedCategory.value !== '') {
    list = list.filter(b => b.category_id === selectedCategory.value);
  }

  // 3. Available Stock Only Filter
  if (availableOnly.value) {
    list = list.filter(b => b.amount > 0);
  }

  // 4. Sorting
  if (sortOrder.value === 'A-Z') {
    list.sort((a, b) => a.specification.localeCompare(b.specification));
  } else if (sortOrder.value === 'Z-A') {
    list.sort((a, b) => b.specification.localeCompare(a.specification));
  } else if (sortOrder.value === 'Stok: Banyak ke Sedikit') {
    list.sort((a, b) => b.amount - a.amount);
  } else if (sortOrder.value === 'Stok: Sedikit ke Banyak') {
    list.sort((a, b) => a.amount - b.amount);
  }

  return list;
});

const clearFilters = () => {
  searchQuery.value = '';
  selectedCategory.value = '';
  sortOrder.value = 'A-Z';
  availableOnly.value = false;
  triggerToast('Semua filter berhasil dihapus.');
};

// ── Cart & Purchase Order Modals State ──────────────────────────
const isCartModalOpen = ref(false);
const isPoModalOpen = ref(false);
const selectedBarang = ref<Barang | null>(null);

const cartForm = useForm({
  barang_id: null as number | null,
  quantity: 1,
  start_date: '',
  end_date: '',
});

const poForm = useForm({
  barang_id: null as number | null,
  quantity_requested: 1,
  reason: '',
});

// ── Opening Modals ──────────────────────────────────────────────
const openCartModal = (barang: Barang) => {
  selectedBarang.value = barang;
  cartForm.reset();
  cartForm.barang_id = barang.id;
  cartForm.quantity = 1;
  
  if (barang.type === 'asset') {
    // Default dates for asset borrowing (today to +7 days)
    const today = new Date();
    const nextWeek = new Date();
    nextWeek.setDate(today.getDate() + 7);
    
    cartForm.start_date = today.toISOString().split('T')[0];
    cartForm.end_date = nextWeek.toISOString().split('T')[0];
  }
  
  isCartModalOpen.value = true;
};

const closeCartModal = () => {
  isCartModalOpen.value = false;
  selectedBarang.value = null;
};

const openPoModal = (barang: Barang) => {
  selectedBarang.value = barang;
  poForm.reset();
  poForm.barang_id = barang.id;
  poForm.quantity_requested = 1;
  isPoModalOpen.value = true;
};

const closePoModal = () => {
  isPoModalOpen.value = false;
  selectedBarang.value = null;
};

// ── Submit Actions ──────────────────────────────────────────────
const submitAddToCart = () => {
  cartForm.post(route('smart.browse.addToCart'), {
    onSuccess: () => {
      closeCartModal();
    },
    onError: () => {
      triggerToast('Gagal menambahkan barang ke keranjang.', 'error');
    }
  });
};

const submitPo = () => {
  poForm.post(route('smart.browse.po'), {
    onSuccess: () => {
      closePoModal();
    },
    onError: () => {
      triggerToast('Gagal mengajukan permintaan PO / Reimbursement.', 'error');
    }
  });
};

const incrementQty = () => {
  if (selectedBarang.value && cartForm.quantity < selectedBarang.value.amount) {
    cartForm.quantity++;
  } else if (selectedBarang.value && selectedBarang.value.type === 'asset') {
    cartForm.quantity++;
  }
};

const decrementQty = () => {
  if (cartForm.quantity > 1) {
    cartForm.quantity--;
  }
};
</script>

<template>
  <AppLayout title="Pilih Barang">
    <div class="space-y-6">
      
      <!-- Header Breadcrumbs / Title -->
      <div class="space-y-1">
        <span class="text-sm font-medium text-muted-foreground">Pilih Barang</span>
        <Heading as="h1" class="font-bold tracking-tight">Pilih barang</Heading>
      </div>

      <!-- Search & Filters Block -->
      <div class="bg-card rounded-2xl border border-border p-5 shadow-sm space-y-4">
        
        <!-- Search bar -->
        <div class="w-full max-w-3xl">
          <div class="relative">
            <TableSearch 
              v-model="searchQuery" 
              placeholder="Cari barang..." 
              class="w-full py-2.5 pl-10 pr-4 text-sm bg-background border border-input rounded-[14px]"
            />
          </div>
        </div>

        <!-- Filter Row -->
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider">Filter</span>
            <div class="flex flex-wrap items-center gap-3">
              
              <!-- Category Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" class="min-w-[180px] justify-between rounded-[14px] font-normal shadow-sm border border-input">
                    {{ selectedCategory ? (props.categories.find(c => c.id === selectedCategory)?.name || 'Semua kategori') : 'Semua kategori' }}
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px]">
                  <DropdownMenuItem @select="selectedCategory = ''">Semua kategori</DropdownMenuItem>
                  <DropdownMenuItem v-for="cat in props.categories" :key="cat.id" @select="selectedCategory = cat.id">
                    {{ cat.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Sorting Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" class="min-w-[180px] justify-between rounded-[14px] font-normal shadow-sm border border-input">
                    Urutkan: {{ sortOrder }}
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[220px] rounded-[14px]">
                  <DropdownMenuItem @select="sortOrder = 'A-Z'">Urutkan: A-Z</DropdownMenuItem>
                  <DropdownMenuItem @select="sortOrder = 'Z-A'">Urutkan: Z-A</DropdownMenuItem>
                  <DropdownMenuItem @select="sortOrder = 'Stok: Banyak ke Sedikit'">Stok: Banyak ke Sedikit</DropdownMenuItem>
                  <DropdownMenuItem @select="sortOrder = 'Stok: Sedikit ke Banyak'">Stok: Sedikit ke Banyak</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Available Stock Only Toggle Button -->
              <button 
                type="button"
                @click="availableOnly = !availableOnly"
                class="flex items-center gap-2 px-4 py-2 border rounded-[14px] text-sm transition-all duration-200 shadow-sm cursor-pointer select-none bg-background hover:bg-muted/40"
                :class="[
                  availableOnly 
                    ? 'border-primary text-primary bg-primary/5 font-medium' 
                    : 'border-input text-foreground'
                ]"
              >
                <!-- Custom Circular Checkmark Indicator -->
                <span class="w-4.5 h-4.5 rounded-full border flex items-center justify-center transition-all duration-200"
                  :class="[
                    availableOnly 
                      ? 'border-primary bg-primary text-white scale-105' 
                      : 'border-muted-foreground/30'
                  ]"
                >
                  <span v-if="availableOnly" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                </span>
                Tampilkan barang dengan stok tersedia saja
              </button>

              <!-- Clear Filter Button -->
              <button 
                @click="clearFilters"
                class="flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm cursor-pointer"
              >
                Hapus filter
              </button>

            </div>
          </div>
        </div>

      </div>

      <!-- Items Section Header -->
      <div class="space-y-1">
        <h3 class="text-sm font-bold text-muted-foreground">Hasil Pencarian dan Filter:</h3>
      </div>

      <!-- Grid of Item Cards -->
      <div v-if="filteredBarangs.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <div 
          v-for="barang in filteredBarangs" 
          :key="barang.id"
          class="bg-card rounded-2xl border border-border overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col group hover:-translate-y-1"
        >
          <!-- Elegant placeholder image styled with smooth diagonal gradients -->
          <div class="h-44 w-full bg-gradient-to-tr from-muted/50 via-muted/10 to-background flex items-center justify-center relative overflow-hidden shrink-0 border-b border-border">
            <div class="absolute -right-10 -bottom-10 w-36 h-36 rounded-full bg-primary/5 blur-xl group-hover:scale-110 transition-transform duration-300"></div>
            <div class="absolute -left-10 -top-10 w-28 h-28 rounded-full bg-indigo-500/5 blur-lg"></div>
            
            <svg class="w-12 h-12 text-muted-foreground/30 group-hover:scale-110 transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>

          <!-- Card Content Body -->
          <div class="p-4 flex-grow flex flex-col justify-between space-y-3">
            <div class="space-y-1">
              <h4 class="text-sm font-bold text-foreground truncate" :title="barang.brand">{{ barang.brand }}</h4>
              <p class="text-base font-bold text-foreground line-clamp-2 min-h-[3rem]" :title="barang.specification">
                {{ barang.specification }}
              </p>
              <p class="text-xs text-muted-foreground">
                {{ barang.category }} ({{ barang.subcategory }})
              </p>
            </div>

            <!-- Stock and Action Button -->
            <div class="pt-2 flex flex-col gap-3 justify-end">
              
              <!-- Stock count badge -->
              <div class="flex items-center gap-1.5">
                <span class="text-sm text-muted-foreground">Stok:</span>
                <span 
                  class="text-sm font-bold"
                  :class="barang.amount > 0 ? 'text-emerald-600' : 'text-rose-500'"
                >
                  {{ barang.amount > 0 ? `${barang.amount} ${barang.unit}` : 'Habis' }}
                </span>
              </div>

              <!-- Pill Buttons based on Stock Level -->
              <button 
                v-if="barang.amount > 0"
                @click="openCartModal(barang)"
                class="w-full bg-gradient-to-r from-primary to-indigo-600 hover:from-primary/95 hover:to-indigo-600/95 text-white font-medium py-2 rounded-full text-xs shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer text-center"
              >
                Tambah ke Keranjang
              </button>

              <button 
                v-else
                @click="openPoModal(barang)"
                class="w-full bg-gradient-to-r from-indigo-500 to-primary/80 hover:from-indigo-600 hover:to-primary text-white font-medium py-2 rounded-full text-xs shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer text-center"
              >
                PO / Reimburse
              </button>

            </div>
          </div>
        </div>
      </div>

      <!-- Blank / No Search Results State -->
      <div 
        v-else 
        class="bg-card rounded-2xl border border-border p-12 flex flex-col items-center justify-center text-center space-y-3 min-h-[300px]"
      >
        <AlertCircle class="w-12 h-12 text-muted-foreground/50" />
        <h4 class="text-lg font-bold">Barang tidak ditemukan</h4>
        <p class="text-sm text-muted-foreground max-w-sm">
          Maaf, tidak ada barang yang cocok dengan kata kunci pencarian atau filter yang Anda pilih saat ini.
        </p>
        <button 
          @click="clearFilters" 
          class="px-4 py-2 bg-muted text-foreground hover:bg-muted/80 text-sm font-semibold rounded-[14px] transition-colors border border-border cursor-pointer"
        >
          Reset Pencarian
        </button>
      </div>

    </div>

    <!-- ── MODAL: TAMBAH KE KERANJANG ────────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isCartModalOpen && selectedBarang" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[500px] p-[24px] flex flex-col relative"
            @click.stop
          >
            <!-- Close Button -->
            <button @click="closeCartModal" class="absolute top-4 right-4 text-muted-foreground hover:text-foreground transition-colors cursor-pointer">
              <X class="w-5 h-5" />
            </button>

            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
              <ShoppingCart class="w-5 h-5 text-primary" />
              Tambah ke Keranjang
            </h3>

            <!-- Item summary card -->
            <div class="p-3 bg-muted/40 rounded-[14px] border border-border mb-5 flex gap-3">
              <div class="w-12 h-12 rounded-lg bg-gradient-to-tr from-muted/50 to-background flex items-center justify-center shrink-0 border border-border">
                <span class="text-xs font-mono font-bold text-muted-foreground">ITEM</span>
              </div>
              <div class="overflow-hidden">
                <h4 class="text-sm font-bold text-foreground truncate">{{ selectedBarang.brand }}</h4>
                <p class="text-xs text-muted-foreground truncate">{{ selectedBarang.specification }}</p>
                <p class="text-[11px] text-primary font-medium mt-0.5">
                  {{ selectedBarang.type === 'asset' ? 'Pinjaman (Aset)' : 'Habis Pakai (Konsumabel)' }}
                </p>
              </div>
            </div>

            <!-- Date Picker for ASSET items -->
            <div v-if="selectedBarang.type === 'asset'" class="space-y-4 mb-5">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-bold text-foreground flex items-center gap-1">
                    <Calendar class="w-3.5 h-3.5 text-muted-foreground" />
                    Tanggal Mulai<span class="text-destructive">*</span>
                  </label>
                  <input 
                    type="date" 
                    v-model="cartForm.start_date"
                    class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  />
                  <div v-if="cartForm.errors.start_date" class="text-destructive text-xs mt-1">{{ cartForm.errors.start_date }}</div>
                </div>

                <div class="space-y-1.5">
                  <label class="text-xs font-bold text-foreground flex items-center gap-1">
                    <Calendar class="w-3.5 h-3.5 text-muted-foreground" />
                    Tanggal Selesai<span class="text-destructive">*</span>
                  </label>
                  <input 
                    type="date" 
                    v-model="cartForm.end_date"
                    class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  />
                  <div v-if="cartForm.errors.end_date" class="text-destructive text-xs mt-1">{{ cartForm.errors.end_date }}</div>
                </div>
              </div>
            </div>

            <!-- Quantity Selection -->
            <div class="space-y-2 mb-6">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-foreground">Jumlah yang Diminta</label>
                <span class="text-xs text-muted-foreground" v-if="selectedBarang.type === 'consumable'">
                  Tersedia: {{ selectedBarang.amount }} {{ selectedBarang.unit }}
                </span>
              </div>
              
              <div class="flex items-center gap-3">
                <button 
                  type="button"
                  @click="decrementQty"
                  class="p-2 border border-input rounded-full hover:bg-muted transition-colors cursor-pointer"
                  :disabled="cartForm.quantity <= 1"
                >
                  <Minus class="w-4 h-4 text-foreground" />
                </button>
                
                <input 
                  type="number" 
                  v-model="cartForm.quantity" 
                  min="1"
                  :max="selectedBarang.type === 'consumable' ? selectedBarang.amount : undefined"
                  class="w-20 text-center px-2 py-1.5 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                />

                <button 
                  type="button"
                  @click="incrementQty"
                  class="p-2 border border-input rounded-full hover:bg-muted transition-colors cursor-pointer"
                >
                  <Plus class="w-4 h-4 text-foreground" />
                </button>
              </div>
              <div v-if="cartForm.errors.quantity" class="text-destructive text-xs mt-1">{{ cartForm.errors.quantity }}</div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 mt-4">
              <button 
                @click="closeCartModal" 
                class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-muted transition-colors cursor-pointer"
              >
                Batal
              </button>
              <button 
                @click="submitAddToCart" 
                :disabled="cartForm.processing"
                class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-primary to-indigo-600 rounded-[14px] hover:opacity-90 transition-all disabled:opacity-50 cursor-pointer shadow-sm"
              >
                {{ cartForm.processing ? 'Memproses...' : 'Masukkan Keranjang' }}
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── MODAL: PO / REIMBURSEMENT REQUEST ──────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isPoModalOpen && selectedBarang" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[500px] p-[24px] flex flex-col relative"
            @click.stop
          >
            <!-- Close Button -->
            <button @click="closePoModal" class="absolute top-4 right-4 text-muted-foreground hover:text-foreground transition-colors cursor-pointer">
              <X class="w-5 h-5" />
            </button>

            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
              <AlertCircle class="w-5 h-5 text-indigo-500" />
              Pengajuan PO / Reimburse
            </h3>

            <!-- Item summary card -->
            <div class="p-3 bg-rose-50/20 border border-rose-100/30 rounded-[14px] mb-5 flex gap-3">
              <div class="w-12 h-12 rounded-lg bg-rose-100/10 flex items-center justify-center shrink-0 border border-rose-200/20">
                <span class="text-xs font-mono font-bold text-rose-500">PO</span>
              </div>
              <div class="overflow-hidden">
                <h4 class="text-sm font-bold text-foreground truncate">{{ selectedBarang.brand }}</h4>
                <p class="text-xs text-muted-foreground truncate">{{ selectedBarang.specification }}</p>
                <p class="text-[11px] text-rose-500 font-semibold mt-0.5">Stok Sedang Habis</p>
              </div>
            </div>

            <!-- Quantity Selection -->
            <div class="space-y-1.5 mb-4">
              <label class="text-xs font-bold text-foreground">Jumlah yang Dibutuhkan<span class="text-destructive">*</span></label>
              <input 
                type="number" 
                v-model="poForm.quantity_requested" 
                min="1"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
              />
              <div v-if="poForm.errors.quantity_requested" class="text-destructive text-xs mt-1">{{ poForm.errors.quantity_requested }}</div>
            </div>

            <!-- Reason Description -->
            <div class="space-y-1.5 mb-6">
              <label class="text-xs font-bold text-foreground">Keterangan / Alasan Kebutuhan<span class="text-destructive">*</span></label>
              <textarea 
                v-model="poForm.reason" 
                rows="3" 
                placeholder="Tuliskan alasan pengadaan barang..."
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
              ></textarea>
              <div v-if="poForm.errors.reason" class="text-destructive text-xs mt-1">{{ poForm.errors.reason }}</div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
              <button 
                @click="closePoModal" 
                class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-muted transition-colors cursor-pointer"
              >
                Batal
              </button>
              <button 
                @click="submitPo" 
                :disabled="poForm.processing"
                class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 rounded-[14px] hover:bg-indigo-700 transition-all disabled:opacity-50 cursor-pointer shadow-sm"
              >
                {{ poForm.processing ? 'Mengirim...' : 'Ajukan Kebutuhan' }}
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── FLOATING TOAST NOTIFICATION ────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showToast" class="fixed bottom-5 right-5 z-[200] max-w-sm w-full bg-card border border-border shadow-2xl rounded-2xl p-4 flex items-start gap-3 pointer-events-auto">
          <CheckCircle2 v-if="toastType === 'success'" class="w-6 h-6 text-emerald-500 shrink-0" />
          <AlertCircle v-else class="w-6 h-6 text-rose-500 shrink-0" />
          <div class="flex-grow space-y-1">
            <h4 class="text-sm font-bold text-foreground">
              {{ toastType === 'success' ? 'Berhasil' : 'Kesalahan' }}
            </h4>
            <p class="text-xs text-muted-foreground leading-relaxed">{{ toastMessage }}</p>
          </div>
          <button @click="showToast = false" class="text-muted-foreground hover:text-foreground cursor-pointer shrink-0">
            <X class="w-4 h-4" />
          </button>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<style scoped>
/* Prevent manual number input spinner styling modifications when possible */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
  -webkit-appearance: none; 
  margin: 0; 
}
</style>
