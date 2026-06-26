<script setup lang="ts">
import { ref, computed, h } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TableSearch from '@/Components/TableSearch.vue';
import { ChevronDown, X } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import ProductCard from '@/Components/ProductCard.vue';
import { ScrollArea } from "@/Components/ui/scroll-area";
import { toast } from 'vue-sonner';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/Components/ui/dialog";
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/Components/ui/number-field";

interface Category {
  id: number;
  name: string;
}

interface BarangVariant {
  id: number;
  number: string;
  name: string;
  brand: string;
  specification: string | null;
  imageUrl: string | null;
  uom?: string;
}

interface Item {
  id: number;
  barang_id: number | null;
  code: string;
  category: string;
  category_id: number;
  category_name: string;
  subcategory_name: string;
  is_consumable: boolean;
  brand: string;
  spec: string;
  stock: number;
  imageUrl?: string;
  barangs?: BarangVariant[];
  uom?: string;
}

interface Props {
  user?: any;
  items?: Item[];
  categories?: Category[];
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  categories: () => []
});

const searchQuery = ref('');
const selectedCategory = ref('Semua kategori');
const selectedSort = ref('Urutkan: A-Z');


const clearFilter = () => {
  searchQuery.value = '';
  selectedCategory.value = 'Semua kategori';
  selectedSort.value = 'Urutkan: A-Z';
};

const isModalOpen = ref(false);
const selectedProduct = ref<Item | null>(null);
const quantity = ref(1);
const selectedBarangId = ref<number | null>(null);

const selectedVariant = computed(() => {
  if (!selectedProduct.value || !selectedBarangId.value) return null;
  return selectedProduct.value.barangs?.find(b => b.id === selectedBarangId.value) || null;
});

const selectedUom = computed(() => {
  if (!selectedProduct.value) return 'satuan';
  const selectedBarang = selectedProduct.value.barangs?.find(b => b.id === selectedBarangId.value);
  if (selectedBarang && selectedBarang.uom) {
    return selectedBarang.uom;
  }
  return selectedProduct.value.uom || 'satuan';
});

const openAddToCartModal = (product: Item) => {
  selectedProduct.value = product;
  quantity.value = 1;
  selectedBarangId.value = null;
  isModalOpen.value = true;
};

const getBarangDisplayName = (barang: BarangVariant) => {
  const parts = [];
  if (barang.brand && barang.brand !== '-') {
    parts.push(barang.brand);
  }
  if (barang.name) {
    parts.push(barang.name);
  }
  if (barang.specification && barang.specification !== '-') {
    parts.push(barang.specification);
  }
  if (parts.length === 0) {
    return barang.number || 'Varian Tanpa Nama';
  }
  return parts.join(' ');
};

const handleConfirmAddToCart = () => {
  if (!selectedProduct.value) return;

  const isConsumable = selectedProduct.value.is_consumable;
  const routeName = isConsumable ? 'smart.asset-cart.store' : 'smart.borrow-cart.store';

  router.post(route(routeName), {
    subcategory_id: selectedProduct.value.id,
    barang_id: selectedBarangId.value,
    quantity: quantity.value,
  }, {
    onSuccess: () => {
      isModalOpen.value = false;
      
      const cartName = isConsumable ? 'keranjang habis pakai' : 'keranjang pinjam';
      const cartUrl = isConsumable ? '/smart/asset-cart' : '/smart/borrow-cart';

      toast.success(
        h('span', [
          'Berhasil menambahkan ',
          h('span', { class: 'font-semibold' }, quantity.value),
          ' barang ke ',
          h('a', {
            href: cartUrl,
            class: 'underline font-semibold text-primary hover:text-indigo-700 transition-colors cursor-pointer',
            onClick: (e: MouseEvent) => {
              e.preventDefault();
              router.visit(cartUrl);
            }
          }, cartName)
        ])
      );
    },
    onError: (errors) => {
      toast.error(Object.values(errors)[0] as string);
    }
  });
};

const filteredAndSortedItems = computed(() => {
  let list = [...props.items];

  // Search filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(item => 
      item.subcategory_name.toLowerCase().includes(q)
    );
  }

  // Category filter
  if (selectedCategory.value !== 'Semua kategori') {
    list = list.filter(item => item.category_name === selectedCategory.value);
  }

  // Sort
  if (selectedSort.value === 'Urutkan: A-Z') {
    list.sort((a, b) => (a.subcategory_name || '').localeCompare(b.subcategory_name || ''));
  } else if (selectedSort.value === 'Urutkan: Z-A') {
    list.sort((a, b) => (b.subcategory_name || '').localeCompare(a.subcategory_name || ''));
  }

  return list;
});
</script>

<template>
  <AppLayout title="Pilih Barang">
    <div class="space-y-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900 leading-none mb-6">Pilih barang</h1>
        
        <!-- Filter & Search Section -->
        <div class="space-y-3 mb-5">
          <div class="flex flex-wrap items-end gap-4">
            <!-- Search Row -->
            <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Pencarian</label>
              <TableSearch 
                v-model="searchQuery" 
                placeholder="Cari subkategori..." 
                bg-class="bg-white"
              />
            </div>

            <!-- Filter Row -->
            <div class="space-y-1.5">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
              <div class="flex items-center gap-3">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', selectedCategory === 'Semua kategori' ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ selectedCategory }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="selectedCategory = 'Semua kategori'">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem 
                      v-for="cat in props.categories" 
                      :key="cat.id" 
                      @select="selectedCategory = cat.name"
                    >
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Sort -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', selectedSort === 'Urutkan: A-Z' ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ selectedSort }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="selectedSort = 'Urutkan: A-Z'">Urutkan: A-Z</DropdownMenuItem>
                    <DropdownMenuItem @select="selectedSort = 'Urutkan: Z-A'">Urutkan: Z-A</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Clear Filter Button -->
                <Button variant="destructive" @click="clearFilter" class="hover:opacity-70 rounded-[14px] px-6 font-semibold text-white">
                  Hapus filter
                </Button>
              </div>
            </div> 
          </div>
        </div>

        <p class="text-sm text-muted-foreground font-medium mb-3">Hasil Pencarian dan Filter:</p>
        <!-- Grid -->
        <ScrollArea class="border border-border rounded-[14px] bg-card h-[calc(100vh-320px)] sm:h-[calc(100vh-270px)]">
          <div class="p-6">
            <div class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-6">
              <ProductCard 
                v-for="item in filteredAndSortedItems" 
                :key="item.id" 
                :subcategory-name="item.subcategory_name" 
                :category-name="item.category_name"
                :image-url="item.imageUrl"
                :disabled="!item.barang_id"
                @add-to-cart="openAddToCartModal(item)"
              />
            </div>
          </div>
        </ScrollArea>
      </div>
    </div>

    <!-- Modal Tambah ke Keranjang -->
    <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
      <DialogContent class="sm:max-w-[1000px] rounded-[14px] bg-card shadow-2xl p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
          <div>
            <DialogTitle class="text-lg font-bold text-foreground">Tambah ke Keranjang</DialogTitle>
            <DialogDescription class="sr-only">
              Formulir untuk menentukan jumlah barang yang ingin ditambahkan ke keranjang.
            </DialogDescription>
          </div>
          <button @click="isModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>
        
        <div v-if="selectedProduct">
          <!-- Modal Body -->
          <div class="px-6 py-3 overflow-y-auto max-h-[70vh] space-y-6">
            <!-- Barang Terpilih -->
            <div class="space-y-1">
              <span class="text-sm font-medium text-foreground block">Barang terpilih:</span>
              <div class="flex items-center gap-4">
                <!-- Image -->
                <div class="w-40 h-40 shrink-0 bg-muted rounded-[14px] overflow-hidden flex items-center justify-center border border-border relative">
                  <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
                  <img v-if="selectedVariant && selectedVariant.imageUrl" :src="selectedVariant.imageUrl.startsWith('http') || selectedVariant.imageUrl.startsWith('/') ? selectedVariant.imageUrl : '/storage/' + selectedVariant.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
                  <img v-else-if="selectedProduct.imageUrl" :src="selectedProduct.imageUrl.startsWith('http') || selectedProduct.imageUrl.startsWith('/') ? selectedProduct.imageUrl : '/storage/' + selectedProduct.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
                  <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
                </div>
                <!-- Info -->
                <div class="flex flex-col justify-center">
                  <template v-if="!selectedBarangId">
                    <h3 class="text-lg font-bold text-foreground leading-snug">{{ selectedProduct.subcategory_name }}</h3>
                    <p class="text-sm text-muted-foreground leading-normal">{{ selectedProduct.category_name }}</p>
                    <p class="text-xs text-muted-foreground italic">*gambar hanya illustrasi</p>
                  </template>
                  <template v-else-if="selectedVariant">
                    <span v-if="selectedVariant.brand && selectedVariant.brand !== '-'" class="text-lg font-bold text-foreground leading-tight">
                      {{ selectedVariant.brand }}
                    </span>
                    <h3 class="text-lg font-bold text-foreground leading-snug">
                      {{ selectedVariant.name }}{{ selectedVariant.specification && selectedVariant.specification !== '-' ? ' ' + selectedVariant.specification : '' }}
                    </h3>
                    <p class="text-sm text-muted-foreground leading-normal">
                      {{ selectedProduct.category_name }} ({{ selectedProduct.subcategory_name }})
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      {{ selectedVariant.number }}
                    </p>
                  </template>
                </div>
              </div>
            </div>

            <!-- Pilih Varian -->
            <div class="space-y-1.5">
              <span class="text-sm font-medium text-foreground block">Pilih varian <span class="text-rose-500">*</span></span>
              <div class="border border-border rounded-[14px] p-4 bg-background flex flex-wrap gap-2.5">
                <!-- Tidak Spesifik -->
                <Button
                  type="button"
                  :variant="selectedBarangId === null ? 'primary-border' : 'white'"
                  size="lg"
                  @click="selectedBarangId = null"
                >
                  Tidak Spesifik
                </Button>

                <!-- Variants -->
                <Button
                  v-for="barang in selectedProduct.barangs"
                  :key="barang.id"
                  type="button"
                  :variant="selectedBarangId === barang.id ? 'primary-border' : 'white'"
                  size="lg"
                  @click="selectedBarangId = barang.id"
                >
                  {{ getBarangDisplayName(barang) }}
                </Button>
              </div>
            </div>

            <!-- Jumlah -->
            <div class="space-y-1.5 pb-3">
              <label class="text-sm font-medium text-foreground block">
                Jumlah barang yang diminta <span class="text-rose-500">*</span>
              </label>
              <div class="flex items-center gap-3">
                <NumberField v-model="quantity" :min="1" :max="999999" locale="id-ID" class="w-32">
                  <NumberFieldContent>
                    <NumberFieldDecrement />
                    <NumberFieldInput />
                    <NumberFieldIncrement />
                  </NumberFieldContent>
                </NumberField>
                <span class="text-sm font-medium text-muted-foreground">{{ selectedUom }}</span>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="py-3 px-4 border-t border-border flex items-center justify-between">
            <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
            <div class="flex items-center gap-3">
              <Button 
                @click="isModalOpen = false"
                variant="white"
                size="xl"
              >
                Batal
              </Button>
              <Button 
                @click="handleConfirmAddToCart"
                variant="primary"
                size="xl"
              >
                Tambah ke Keranjang
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
