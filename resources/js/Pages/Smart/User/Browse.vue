<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Input } from "@/Components/ui/input";
import { Search, ChevronDown } from 'lucide-vue-next';
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
  if (!selectedProduct.value) return;

  router.post(route('smart.browse.add-to-cart'), {
    barang_id: selectedProduct.value.id,
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
      item.brand.toLowerCase().includes(q) || 
      item.spec.toLowerCase().includes(q) || 
      item.code.toLowerCase().includes(q)
    );
  }

  // Category filter
  if (selectedCategory.value !== 'Semua kategori') {
    list = list.filter(item => item.category_name === selectedCategory.value);
  }



  // Sort
  if (selectedSort.value === 'Urutkan: A-Z') {
    list.sort((a, b) => a.brand.localeCompare(b.brand));
  } else if (selectedSort.value === 'Urutkan: Z-A') {
    list.sort((a, b) => b.brand.localeCompare(a.brand));
  }

  return list;
});
</script>

<template>
  <AppLayout title="Pilih Barang">
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-foreground mb-4">Pilih barang</h1>
        
        <!-- Search bar -->
        <div class="relative w-full max-w-full sm:max-w-xl md:max-w-2xl mb-6">
          <Input 
            v-model="searchQuery" 
            placeholder="Cari barang..." 
            class="w-full pl-4 pr-12 py-2 h-10 border border-input rounded-full focus:outline-none focus:ring-1 focus:ring-primary/20 bg-background"
          />
          <Search class="h-5 w-5 text-muted-foreground absolute right-4 top-1/2 -translate-y-1/2" />
        </div>

        <!-- Filters -->
        <div class="space-y-2 mb-8">
          <label class="text-sm text-foreground block font-medium">Filter</label>
          <div class="flex flex-wrap items-center gap-4">
            <!-- Category Filter -->
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-64 justify-between rounded-full font-normal h-10', selectedCategory === 'Semua kategori' ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ selectedCategory }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-64 rounded-[14px]" align="start">
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
                <Button variant="outline" :class="['w-48 justify-between rounded-full font-normal h-10', selectedSort === 'Urutkan: A-Z' ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ selectedSort }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-48 rounded-[14px]" align="start">
                <DropdownMenuItem @select="selectedSort = 'Urutkan: A-Z'">Urutkan: A-Z</DropdownMenuItem>
                <DropdownMenuItem @select="selectedSort = 'Urutkan: Z-A'">Urutkan: Z-A</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>



            <!-- Clear Filter Button -->
            <Button @click="clearFilter" class="bg-[#cb3a31] hover:bg-[#b02e26] text-white rounded-full px-6 h-10 font-semibold">
              Hapus filter
            </Button>
          </div>
        </div>

        <p class="text-sm text-foreground mb-4">Hasil Pencarian dan Filter:</p>

        <!-- Grid -->
        <div class="border border-border rounded-[14px] p-6 bg-card">
          <div class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-6">
            <ProductCard 
              v-for="item in filteredAndSortedItems" 
              :key="item.id" 
              :brand="item.brand" 
              :spec="item.spec" 
              :category="item.category"
              :stock="item.stock"
              :imageUrl="item.imageUrl"
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
            <img v-if="selectedProduct.imageUrl" :src="selectedProduct.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
            <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
          </div>

          <!-- Content & Actions (Right Column) -->
          <div class="flex flex-col flex-grow">
            <!-- Info -->
            <h3 class="text-xl font-bold text-foreground">{{ selectedProduct.brand }}</h3>
            <p class="text-lg font-semibold text-foreground mb-1">{{ selectedProduct.spec }}</p>
            <p class="text-sm text-muted-foreground">{{ selectedProduct.category }}</p>
            <p class="text-sm text-muted-foreground">{{ selectedProduct.code }}</p>

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
