<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TableSearch from '@/Components/TableSearch.vue';
import { ChevronDown } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import ProductCard from '@/Components/ProductCard.vue';
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
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/Components/ui/dialog";

interface Category {
  id: number;
  name: string;
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

const openAddToCartModal = (product: Item) => {
  selectedProduct.value = product;
  quantity.value = 1;
  isModalOpen.value = true;
};

const handleConfirmAddToCart = () => {
  if (!selectedProduct.value || !selectedProduct.value.barang_id) return;

  router.post(route('smart.browse.add-to-cart'), {
    barang_id: selectedProduct.value.barang_id,
    quantity: quantity.value,
  }, {
    onSuccess: () => {
      isModalOpen.value = false;
      toast.success(`Berhasil menambahkan ${quantity.value} item ke keranjang!`);
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
        <div class="border border-border rounded-[14px] p-6 bg-card">
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
      </div>
    </div>

    <!-- Modal Tambah ke Keranjang -->
    <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
      <DialogContent class="sm:max-w-[886px] rounded-[14px]">
        <DialogHeader>
          <DialogTitle class="text-xl font-bold">Tambah ke Keranjang</DialogTitle>
          <DialogDescription class="sr-only">
            Formulir untuk menentukan jumlah barang yang ingin ditambahkan ke keranjang.
          </DialogDescription>
        </DialogHeader>
        
        <hr class="border-border my-0" />
        
        <div v-if="selectedProduct" class="mt-4 flex flex-col sm:flex-row gap-6">
          <!-- Image (Left Column) -->
          <div class="w-32 h-32 sm:w-[180px] sm:h-[180px] shrink-0 bg-muted rounded-[14px] overflow-hidden flex items-center justify-center border border-border relative">
            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
            <img v-if="selectedProduct.imageUrl" :src="selectedProduct.imageUrl.startsWith('http') || selectedProduct.imageUrl.startsWith('/') ? selectedProduct.imageUrl : '/storage/' + selectedProduct.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
            <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
          </div>

          <!-- Content & Actions (Right Column) -->
          <div class="flex flex-col flex-grow">
            <!-- Info -->
            <h3 class="text-xl font-bold text-foreground">{{ selectedProduct.subcategory_name }}</h3>
            <p class="text-sm text-muted-foreground">{{ selectedProduct.category_name }}</p>

            <hr class="border-border my-6" />

            <!-- Jumlah -->
            <div class="mb-8">
              <label class="text-sm font-medium text-foreground block mb-3">
                Atur jumlah barang yang akan diminta (dalam satuan)<span class="text-rose-500">*</span>
              </label>
              <div class="flex items-center">
                <div class="flex items-center border border-input rounded-[14px] bg-background">
                  <button 
                    type="button" 
                    class="w-10 h-10 flex items-center justify-center text-muted-foreground hover:bg-muted/50 rounded-l-[14px] transition-colors"
                    @click="quantity > 1 && quantity--"
                  >
                    <span class="text-lg leading-none">-</span>
                  </button>
                  <div class="w-12 h-10 flex items-center justify-center border-x border-input text-sm font-medium">
                    {{ quantity }}
                  </div>
                  <button 
                    type="button" 
                    class="w-10 h-10 flex items-center justify-center text-muted-foreground hover:bg-muted/50 rounded-r-[14px] transition-colors"
                    @click="quantity++"
                  >
                    <span class="text-lg leading-none">+</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-auto">
              <div class="text-xs text-rose-500 italic">*Wajib diisi</div>
              <div class="flex gap-3 w-full sm:w-auto">
                <Button variant="outline" @click="isModalOpen = false" class="rounded-[14px] w-full sm:w-auto h-[42px] px-8">
                  Batal
                </Button>
                <Button @click="handleConfirmAddToCart" class="bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-[14px] w-full sm:w-auto h-[42px] px-8">
                  Tambah ke Keranjang
                </Button>
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
