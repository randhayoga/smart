<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  Pencil, 
  Trash2,
  X
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import Heading from '@/Components/Heading.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

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

const searchQuery = ref('');
const parentFilter = ref('');
const rowsPerPage = ref('Semua baris');

const displayData = computed(() => {
  if (activeTab.value === 'Kategori') return [...dummyCategories];
  if (activeTab.value === 'Subkategori') return [...dummySubcategories];
  if (activeTab.value === 'Satuan') return [...dummySatuan];
  if (activeTab.value === 'Merek') return [...dummyMerek];
  if (activeTab.value === 'Organizer') return [...dummyOrganizer];
  if (activeTab.value === 'Vendor') return [...dummyVendors];
  if (activeTab.value === 'Lokasi') return [...dummyLokasi];
  return [];
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

const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [];

  // Code column (if applicable)
  if (!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab.value)) {
    cols.push({
      accessorKey: 'code',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'px-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          `Kode ${activeTab.value}`,
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground font-mono text-sm truncate' }, row.getValue('code')),
    });
  }

  // Name column
  cols.push({
    accessorKey: 'name',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        `Nama ${activeTab.value}`,
        h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'pl-2 text-foreground truncate' }, row.getValue('name')),
  });

  // Parent column (Subkategori only)
  if (activeTab.value === 'Subkategori') {
    cols.push({
      accessorKey: 'parent',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Kategori Induk',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('parent')),
      // Enable filtering by parentCode if we want to use the dropdown for this column
      filterFn: (row, id, value) => {
        if (!value) return true;
        return row.original.parentCode === value;
      }
    });
  }

  // Actions column
  cols.push({
    id: 'actions',
    size: 84,
    header: () => h('div', 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-2' }, [
        h('button', {
          onClick: () => openEditModal(item),
          class: 'p-2 bg-amber-400 hover:bg-amber-500 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-400/50'
        }, [h(Pencil, { class: 'w-3.5 h-3.5' })]),
        h('button', {
          onClick: () => openDeleteModal(item),
          class: 'p-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50'
        }, [h(Trash2, { class: 'w-3.5 h-3.5' })])
      ]);
    },
  });

  return cols;
});

const dataTableRef = ref<any>(null);

// Sync parentFilter with the parent column filter in DataTable
watch(parentFilter, (val) => {
  if (activeTab.value === 'Subkategori' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('parent')?.setFilterValue(val);
  }
});

// Delete Logic
const isDeleteModalOpen = ref(false);
const itemToDelete = ref<any>(null);

const openDeleteModal = (item: any) => {
  itemToDelete.value = item;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemToDelete.value = null;
};

const handleConfirmDelete = () => {
  alert(`Berhasil menghapus ${activeTab.value}: ${itemToDelete.value?.name || itemToDelete.value?.code}`);
  closeDeleteModal();
};
</script>

<template>
  <AppLayout title="Master Data">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/master">Master Data</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>
    
    <div class="space-y-1">
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
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-5">
          <Heading as="h2">Daftar {{ activeTab }}</Heading>
          
          <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <!-- Search -->
            <div class="flex items-end gap-3 w-full max-w-xl">
              <div class="space-y-1.5 flex-1 max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <TableSearch 
                  v-model="searchQuery"
                  :placeholder="`Cari Kode atau Nama ${activeTab}...`" 
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
            <div class="flex flex-wrap items-center gap-3">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
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

              <button @click="openCreateModal" class="flex items-center gap-1.5 bg-gradient-primary hover:bg-primary/90 text-primary-foreground px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm">
                <Plus class="w-4 h-4" />
                <span>{{ activeTab }} Baru</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="displayData" 
            :filter-value="searchQuery"
            :show-selection-count=false
          />
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

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="activeTab"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />
  </AppLayout>
</template>
