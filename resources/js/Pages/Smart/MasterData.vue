<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { 
  Search, 
  ChevronDown, 
  ArrowUpDown, 
  ArrowUp,
  ArrowDown,
  Plus, 
  Pencil, 
  Trash2,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  X
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

const tabs = [
  'Kategori', 'Subkategori', 'Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'
];

const activeTab = ref('Kategori');

const dummyCategories = [
  { code: 'ATK', name: 'Alat Tulis Kantor' },
  { code: 'FUR', name: 'Furnitur' },
  { code: 'ELE', name: 'Elektronik' },
  { code: 'VEH', name: 'Kendaraan' },
];

const dummySubcategories = [
  { code: 'ATK-KER', name: 'Kertas', parent: 'Alat Tulis Kantor', parentCode: 'ATK' },
  { code: 'FUR-MEJ', name: 'Meja', parent: 'Furnitur', parentCode: 'FUR' },
  { code: 'ELE-LAP', name: 'Laptop', parent: 'Elektronik', parentCode: 'ELE' },
  { code: 'VEH-MOB', name: 'Mobil', parent: 'Kendaraan', parentCode: 'VEH' },
];

const dummySatuan = [
  { name: 'Rim' },
  { name: 'Unit' },
  { name: 'Botol' },
  { name: 'Centimeter' },
];

const dummyMerek = [
  { name: 'Asus' },
  { name: 'Acer' },
  { name: 'Dell' },
  { name: 'Lenovo' },
];

const dummyOrganizer = [
  { name: 'AAA' },
  { name: 'BBB' },
  { name: 'CCC' },
  { name: 'DDD' },
];

const dummyVendors = [
  { name: 'AAA' },
  { name: 'BBB' },
  { name: 'CCC' },
  { name: 'DDD' },
];

const dummyLokasi = [
  { name: 'Ruang IFS' },
  { name: 'Mega Mendung' },
  { name: 'Tiga Negeri' },
  { name: 'Gudang' },
];

const sortKey = ref('code');
const sortOrder = ref('asc');
const searchQuery = ref('');
const parentFilter = ref('');
const rowsPerPage = ref('Semua baris');

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortOrder.value = 'asc';
  }
};

const displayData = computed(() => {
  let data: any[] = [];
  if (activeTab.value === 'Kategori') data = [...dummyCategories];
  else if (activeTab.value === 'Subkategori') data = [...dummySubcategories];
  else if (activeTab.value === 'Satuan') data = [...dummySatuan];
  else if (activeTab.value === 'Merek') data = [...dummyMerek];
  else if (activeTab.value === 'Organizer') data = [...dummyOrganizer];
  else if (activeTab.value === 'Vendor') data = [...dummyVendors];
  else if (activeTab.value === 'Lokasi') data = [...dummyLokasi];
  
  // Apply Search Filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    data = data.filter(item => 
      (item.name && item.name.toLowerCase().includes(query)) ||
      (item.code && item.code.toLowerCase().includes(query))
    );
  }

  // Apply Parent Filter (Subkategori only)
  if (activeTab.value === 'Subkategori' && parentFilter.value) {
    data = data.filter(item => item.parentCode === parentFilter.value);
  }
  
  if (sortKey.value) {
    data.sort((a, b) => {
      let aVal = (a as any)[sortKey.value] || '';
      let bVal = (b as any)[sortKey.value] || '';
      if (typeof aVal === 'string') aVal = aVal.toLowerCase();
      if (typeof bVal === 'string') bVal = bVal.toLowerCase();
      
      if (aVal < bVal) return sortOrder.value === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortOrder.value === 'asc' ? 1 : -1;
      return 0;
    });
  }
  
  return data;
});

const isEditModalOpen = ref(false);
const editingItem = ref<any>(null);

const isCreateModalOpen = ref(false);
const newItem = ref({ code: '', name: '', parentCode: '' });

const openEditModal = (item: any) => {
  editingItem.value = { ...item };
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
  setTimeout(() => {
    editingItem.value = null;
  }, 200);
};

const openCreateModal = () => {
  newItem.value = { code: '', name: '', parentCode: '' };
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

// Reset filters when tab changes
watch(activeTab, () => {
  searchQuery.value = '';
  parentFilter.value = '';
});
</script>

<template>
  <AppLayout title="Master Data">
    <template #header>
      <h1 class="text-xl font-medium text-foreground">
        Master Data
      </h1>
    </template>
    
    <div class="space-y-4">
      <!-- Tabs -->
      <div class="flex overflow-x-auto pb-2 scrollbar-hide">
        <div class="flex items-center border border-border rounded-full bg-card p-1 shadow-sm w-max">
          <button
            v-for="tab in tabs"
            :key="tab"
            @click="activeTab = tab"
            class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors whitespace-nowrap"
            :class="[
              activeTab === tab 
                ? 'border border-primary text-primary bg-primary/10' 
                : 'text-muted-foreground hover:text-primary hover:bg-primary/10'
            ]"
          >
            {{ tab }}
          </button>
        </div>
      </div>

      <!-- Main Card -->
      <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-5">
          <h2 class="text-lg font-bold text-foreground">Daftar {{ activeTab }}</h2>
          
          <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <!-- Search -->
            <div class="flex items-end gap-3 w-full max-w-xl">
              <div class="space-y-1.5 flex-1 max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <input 
                  type="text" 
                  v-model="searchQuery"
                  :placeholder="`Cari Nama ${activeTab}...`" 
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
              <div v-if="activeTab === 'Subkategori'" class="flex-1 max-w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ parentFilter ? (dummyCategories.find(c => c.code === parentFilter)?.name || 'Semua Kategori Induk') : 'Semua Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem @select="parentFilter = ''">Semua Kategori Induk</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in dummyCategories" :key="cat.code" @select="parentFilter = cat.code">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <!-- Right Actions -->
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center gap-3 text-sm text-muted-foreground">
                <span>Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal">
                      {{ rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px]">
                    <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <button @click="openCreateModal" class="flex items-center gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm">
                <Plus class="w-4 h-4" />
                <span>{{ activeTab }} Baru</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="px-[10px]">
          <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden"> 
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left table-fixed">
                <thead class="text-xs text-foreground bg-muted/50 border-b border-border">
              <tr>
                <th v-if="!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab)" scope="col" class="px-6 py-4 font-semibold w-[400px]">
                  <div @click="toggleSort('code')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'code' }">
                    Kode {{ activeTab }}
                    <ArrowUp v-if="sortKey === 'code' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                    <ArrowDown v-else-if="sortKey === 'code' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                    <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                  </div>
                </th>
                <th scope="col" class="px-6 py-4 font-semibold w-auto">
                  <div @click="toggleSort('name')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'name' }">
                    Nama {{ activeTab }}
                    <ArrowUp v-if="sortKey === 'name' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                    <ArrowDown v-else-if="sortKey === 'name' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                    <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                  </div>
                </th>
                <th v-if="activeTab === 'Subkategori'" scope="col" class="px-6 py-4 font-semibold w-[250px]">
                  <div @click="toggleSort('parent')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'parent' }">
                    Kategori Induk
                    <ArrowUp v-if="sortKey === 'parent' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                    <ArrowDown v-else-if="sortKey === 'parent' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                    <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                  </div>
                </th>
                <th scope="col" class="px-6 py-4 font-semibold text-right w-[120px]">
                  Aksi
                </th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(item, index) in displayData" 
                :key="item.code || item.name"
                class="border-b border-border hover:bg-muted/30 transition-colors"
                :class="{ 'border-none': index === displayData.length - 1 }"
              >
                <td v-if="!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab)" class="px-6 py-4 text-muted-foreground truncate">
                  {{ item.code }}
                </td>
                <td class="px-6 py-4 text-foreground truncate">
                  {{ item.name }}
                </td>
                <td v-if="activeTab === 'Subkategori'" class="px-6 py-4 text-muted-foreground truncate">
                  {{ item.parent }}
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button @click="openEditModal(item)" class="p-2 bg-amber-400 hover:bg-amber-500 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-400/50">
                      <Pencil class="w-3.5 h-3.5" />
                    </button>
                    <button class="p-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50">
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
            </div>
          </div>
        </div>

        <!-- Footer Pagination -->
        <div class="p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
          <div>
            0 dari X baris dipilih
          </div>
          
          <div class="flex items-center gap-1">
            <button class="flex items-center gap-1 px-2 py-1 hover:text-foreground transition-colors disabled:opacity-50">
              <ChevronLeft class="w-4 h-4" />
              <span>Sebelumnya</span>
            </button>
            
            <div class="flex items-center gap-1 px-2">
              <button class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-accent text-foreground transition-colors">1</button>
              <button class="w-8 h-8 flex items-center justify-center rounded-full border border-border bg-background font-medium text-foreground transition-colors shadow-sm">2</button>
              <button class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-accent text-foreground transition-colors">3</button>
              <span class="px-1"><MoreHorizontal class="w-4 h-4 text-muted-foreground" /></span>
            </div>

            <button class="flex items-center gap-1 px-2 py-1 hover:text-foreground transition-colors">
              <span>Selanjutnya</span>
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isEditModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[1200px] min-h-[261px] p-[24px] flex flex-col"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-border pb-4 mb-6">
              <h3 class="text-lg font-bold">Edit {{ activeTab }}</h3>
              <button @click="closeEditModal" class="text-muted-foreground hover:text-foreground transition-colors">
                <X class="w-5 h-5" />
              </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-if="activeTab === 'Subkategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kategori Induk<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  :value="editingItem?.parent" 
                  disabled 
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Subkategori<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  :value="editingItem?.code" 
                  disabled 
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Subkategori<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  v-if="editingItem"
                  v-model="editingItem.name" 
                  maxlength="255"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab)">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode {{ activeTab }} (tidak dapat diubah)</label>
                <input 
                  type="text" 
                  :value="editingItem?.code" 
                  disabled 
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  v-if="editingItem"
                  v-model="editingItem.name" 
                  maxlength="255"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
            </div>

            <div class="mb-8 flex-grow" v-else>
              <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
              <input 
                type="text" 
                v-if="editingItem"
                v-model="editingItem.name" 
                maxlength="255"
                :placeholder="`${activeTab} sekarang`"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
              />
            </div>
            
            <div class="flex items-center justify-between mt-auto">
              <span class="text-sm text-destructive italic">*Wajib diisi</span>
              <div class="flex items-center gap-3">
                <button @click="closeEditModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-accent transition-colors">
                  Batal
                </button>
                <button class="px-4 py-2 text-sm font-medium text-primary-foreground bg-primary rounded-[14px] hover:bg-primary/90 transition-colors">
                  Simpan Perubahan
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Create Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[1200px] min-h-[261px] p-[24px] flex flex-col"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-border pb-4 mb-6">
              <h3 class="text-lg font-bold">Pembuatan {{ activeTab }} Baru</h3>
              <button @click="closeCreateModal" class="text-muted-foreground hover:text-foreground transition-colors">
                <X class="w-5 h-5" />
              </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-if="activeTab === 'Subkategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kategori Induk<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ newItem.parentCode ? (dummyCategories.find(c => c.code === newItem.parentCode)?.name || 'Pilih Kategori Induk') : 'Pilih Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem v-for="cat in dummyCategories" :key="cat.code" @select="newItem.parentCode = cat.code">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Subkategori<span class="text-destructive">*</span></label>
                <div 
                  class="flex rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors"
                  :class="{ 'opacity-50 bg-muted/50': !newItem.parentCode }"
                >
                  <span class="pl-3 py-2 text-sm text-muted-foreground flex items-center bg-transparent select-none whitespace-nowrap">
                    {{ newItem.parentCode ? newItem.parentCode + '-' : 'KOD-' }}
                  </span>
                  <input 
                    type="text" 
                    v-model="newItem.code" 
                    @input="newItem.code = newItem.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                    maxlength="3"
                    :disabled="!newItem.parentCode"
                    placeholder="3 huruf kapital..."
                    class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                    :class="{ 'cursor-not-allowed': !newItem.parentCode }"
                  />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Subkategori<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  v-model="newItem.name" 
                  maxlength="255"
                  placeholder="Input nama kategorinya di sini..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab)">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode {{ activeTab }}<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  v-model="newItem.code" 
                  @input="newItem.code = newItem.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                  maxlength="3"
                  placeholder="Input kodenya di sini (tiga huruf kapital)..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
                <input 
                  type="text" 
                  v-model="newItem.name" 
                  maxlength="255"
                  placeholder="Input nama kategorinya di sini..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                />
              </div>
            </div>

            <div class="mb-8 flex-grow" v-else>
              <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
              <input 
                type="text" 
                v-model="newItem.name" 
                maxlength="255"
                :placeholder="`Input nama ${activeTab}nya di sini...`"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
              />
            </div>
            
            <div class="flex items-center justify-between mt-auto">
              <span class="text-sm text-destructive italic">*Wajib diisi</span>
              <div class="flex items-center gap-3">
                <button @click="closeCreateModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-accent transition-colors">
                  Batal
                </button>
                <button class="px-4 py-2 text-sm font-medium text-primary-foreground bg-primary rounded-[14px] hover:bg-primary/90 transition-colors">
                  Buat {{ activeTab }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
