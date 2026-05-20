<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Eye,
  Trash2,
  Plus,
  X
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Props {
  itemId: string | number;
}

const props = defineProps<Props>();

// Dummy Data
const dummyLots = [
  { id: 1, lotCode: 'LOT-YYYY-CAT-SUB-XXXX-001', poNumber: 'PO-001', entryDate: 'DD-MM-YYYY', organizer: 'XXX', assetCount: 'XX' },
  { id: 2, lotCode: 'LOT-YYYY-CAT-SUB-XXXX-002', poNumber: 'PO-002', entryDate: 'DD-MM-YYYY', organizer: 'XXX', assetCount: 'XX' },
];

const searchQuery = ref('');
const timeFilter = ref('');
const organizerFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

const columns: ColumnDef<any>[] = [
  {
    id: 'select',
    size: 50,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
      })
    ]),
  },
  {
    accessorKey: 'lotCode',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode LOT',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('lotCode')),
  },
  {
    accessorKey: 'poNumber',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nomor PO',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 font-medium' }, row.getValue('poNumber')),
  },
  {
    accessorKey: 'entryDate',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Tanggal Masuk',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('entryDate')),
  },
  {
    accessorKey: 'organizer',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Organizer',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('organizer')),
  },
  {
    accessorKey: 'assetCount',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Jml. Aset',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('assetCount')),
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
      h('button', {
        onClick: () => alert('View LOT'),
        class: 'p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50'
      }, [h(Eye, { class: 'w-3.5 h-3.5' })]),
      h('button', {
        onClick: () => alert('Delete LOT'),
        class: 'p-2 bg-[#D9534F] hover:bg-[#C9302C] text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500/50'
      }, [h(Trash2, { class: 'w-3.5 h-3.5' })])
    ]),
  },
];

watch(rowsPerPage, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    if (val === 'Semua baris' || !val) {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(val));
    }
  }
});

onMounted(() => {
  if (dataTableRef.value && dataTableRef.value.table && rowsPerPage.value === 'Semua baris') {
    dataTableRef.value.table.setPageSize(999999);
  }
});

// Edit Modal Logic
const isEditModalOpen = ref(false);
const editForm = ref({
  kode: 'CAT-SUB-XXXX',
  kategori: 'Kategori sekarang',
  subkategori: 'Subkategori sekarang',
  satuan: 'Satuan sekarang',
  merek: 'Merek sekarang',
  spesifikasi: 'Spesifikasi sekarang',
  foto: null as File | null,
  fotoName: 'Foto_Sekarang.extension'
});

const openEditModal = () => {
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
};

const handleEditFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    alert('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    alert('Gagal! Ukuran foto maksimal 1MB');
    return;
  }

  editForm.value.foto = file;
  editForm.value.fotoName = file.name;
};

const triggerEditFileInput = () => {
  const input = document.getElementById('edit-photo-upload') as HTMLInputElement;
  input?.click();
};

const isEditFormValid = computed(() => {
  return editForm.value.satuan && editForm.value.merek && editForm.value.spesifikasi;
});

const handleSaveChanges = () => {
  if (!isEditFormValid.value) return;
  alert('Berhasil! Perubahan detail barang telah disimpan.');
  closeEditModal();
};

// Delete Modal Logic
const isDeleteModalOpen = ref(false);
const deleteConfirmationCode = ref('');

const openDeleteModal = () => {
  isDeleteModalOpen.value = true;
  deleteConfirmationCode.value = '';
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
};

const isDeleteValid = computed(() => {
  return deleteConfirmationCode.value === editForm.value.kode;
});

const handleDelete = () => {
  if (!isDeleteValid.value) return;
  alert('Berhasil! Barang beserta seluruh LOT dan Aset-nya telah dihapus.');
  closeDeleteModal();
  router.get('/smart/inventory');
};
</script>

<template>
  <AppLayout title="Detail Barang">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">Manajemen Stok</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">CAT-SUB-XXXX</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2">
        <button class="px-5 py-2 rounded-full border border-indigo-600 text-indigo-600 font-bold text-sm bg-indigo-50/50">
          Detail
        </button>
        <button class="px-5 py-2 rounded-full text-muted-foreground font-semibold text-sm hover:bg-muted/50 transition-colors">
          Daftar Aset
        </button>
      </div>

      <div class="flex items-center gap-3">
        <button @click="openEditModal" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-sm">
          Edit Detail Barang
        </button>
        <button @click="openDeleteModal" class="px-6 py-2.5 bg-[#D9534F] hover:bg-[#C9302C] text-white font-bold rounded-xl transition-all shadow-sm">
          Hapus Barang
        </button>
      </div>
    </div>

    <div class="space-y-6">
      <!-- Detail Barang Card -->
      <div class="bg-card rounded-xl border border-border p-6 shadow-sm">
        <h2 class="text-lg font-bold text-foreground mb-4">Detail Barang</h2>
        
        <div class="flex flex-col md:flex-row gap-6">
          <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
            <img src="https://placehold.co/400x400?text=Item" class="w-full h-full object-cover opacity-50" />
          </div>

          <div class="flex-grow space-y-1">
            <div class="flex flex-col gap-1 mb-3">
              <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> CAT-SUB-XXXX</p>
              <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> Merek</p>
              <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> Spesifikasi</p>
            </div>
            
            <p class="text-foreground">Kategori: Kategori</p>
            <p class="text-foreground">Subkategori: Subkategori</p>
            <p class="text-foreground">Jumlah LOT: XX</p>
            <p class="text-foreground">Jumlah stok: XXX</p>
            <p class="text-foreground">Satuan: Lorem</p>
            <p class="text-foreground">Pembaruan terakhir: DD-MM-YYYY HH:MM</p>
          </div>
        </div>
      </div>

      <!-- Daftar LOT Card -->
      <div class="bg-card rounded-xl border border-border p-6 shadow-sm overflow-hidden">
        <h2 class="text-lg font-bold text-foreground mb-4">Daftar LOT</h2>

        <!-- Filters Row -->
        <div class="mb-4 flex flex-wrap items-end gap-4">
          <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
            <TableSearch 
              v-model="searchQuery"
              placeholder="Cari Kode LOT atau nomor PO..." 
            />
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" class="w-[200px] justify-between rounded-[14px] font-normal text-muted-foreground">
                <span class="truncate">{{ timeFilter || 'Semua kurun waktu' }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="timeFilter = ''">Semua kurun waktu</DropdownMenuItem>
              <DropdownMenuItem @select="timeFilter = 'Hari ini'">Hari ini</DropdownMenuItem>
              <DropdownMenuItem @select="timeFilter = 'Bulan ini'">Bulan ini</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" class="w-[200px] justify-between rounded-[14px] font-normal text-muted-foreground">
                <span class="truncate">{{ organizerFilter || 'Semua organizer' }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="organizerFilter = ''">Semua organizer</DropdownMenuItem>
              <DropdownMenuItem @select="organizerFilter = 'XXX'">XXX</DropdownMenuItem>
              <DropdownMenuItem @select="organizerFilter = 'YYY'">YYY</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
            <span>Baris per halaman</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal text-muted-foreground">
                  {{ rowsPerPage }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <button class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
            <Plus class="w-4 h-4" />
            LOT Baru
          </button>
        </div>

        <!-- Table -->
        <div class="pb-2">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="dummyLots" 
            :filter-value="searchQuery"
          />
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
          >
            <div v-if="isEditModalOpen" class="bg-background rounded-[24px] shadow-lg w-full max-w-4xl overflow-hidden" @click.stop>
              <!-- Modal Header -->
              <div class="flex items-center justify-between p-5 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Edit Detail Barang</h3>
                <button @click="closeEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kode Barang</label>
                      <input 
                        type="text" 
                        v-model="editForm.kode"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kategori</label>
                      <input 
                        type="text" 
                        v-model="editForm.kategori"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Subkategori</label>
                      <input 
                        type="text" 
                        v-model="editForm.subkategori"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Satuan<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground bg-background">
                            {{ editForm.satuan || 'Pilih satuan' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[380px] rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="editForm.satuan = 'Lorem'">Lorem</DropdownMenuItem>
                          <DropdownMenuItem @select="editForm.satuan = 'Satuan sekarang'">Satuan sekarang</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Merek<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground bg-background">
                            {{ editForm.merek || 'Pilih merek' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[380px] rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="editForm.merek = 'Merek baru'">Merek baru</DropdownMenuItem>
                          <DropdownMenuItem @select="editForm.merek = 'Merek sekarang'">Merek sekarang</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Spesifikasi<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="editForm.spesifikasi"
                        maxlength="255"
                        placeholder="Input spesifikasinya di sini..." 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Foto <span class="italic text-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex-grow px-4 py-2 text-sm border border-input rounded-[14px] bg-background text-foreground truncate flex items-center h-10">
                          {{ editForm.fotoName || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="edit-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleEditFileUpload"
                        />
                        <button 
                          @click="triggerEditFileInput"
                          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-[14px] transition-colors h-10"
                        >
                          Pilih File
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="p-6 pt-2 flex items-center justify-between">
                <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
                <div class="flex items-center gap-3">
                  <button 
                    @click="closeEditModal"
                    class="px-6 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-bold rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleSaveChanges"
                    :disabled="!isEditFormValid"
                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
                  >
                    Simpan Perubahan
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
          >
            <div v-if="isDeleteModalOpen" class="bg-background rounded-[24px] shadow-lg w-full max-w-[886px] overflow-hidden" @click.stop>
              <!-- Modal Header -->
              <div class="flex items-center justify-between p-5 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Konfirmasi Penghapusan Barang</h3>
                <button @click="closeDeleteModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6">
                <div class="flex flex-col md:flex-row gap-6 mb-6">
                  <div class="w-40 h-40 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                    <img src="https://placehold.co/400x400?text=Item" class="w-full h-full object-cover opacity-50" />
                  </div>

                  <div class="flex-grow space-y-1">
                    <div class="flex flex-col gap-1 mb-2">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> CAT-SUB-XXXX</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> Merek</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> Spesifikasi</p>
                    </div>
                    
                    <p class="text-foreground text-sm">Kategori: Kategori</p>
                    <p class="text-foreground text-sm">Subkategori: Subkategori</p>
                    <p class="text-foreground text-sm">Jumlah LOT: XX</p>
                    <p class="text-foreground text-sm">Jumlah stok: XXX</p>
                    <p class="text-foreground text-sm">Satuan: Lorem</p>
                    <p class="text-foreground text-sm">Pembaruan terakhir: DD-MM-YYYY HH:MM</p>
                  </div>
                </div>

                <div class="border-t border-border pt-6">
                  <p class="text-[#D9534F] font-bold mb-2">Menghapus Barang ini akan menghapus semua LOT dan Aset yang menjadi bagian dari barang ini!</p>
                  <p class="text-sm text-foreground mb-3">Ketik kode barang ini lalu tekan tombol Konfirmasi untuk menghapus barang ini<span class="text-[#D9534F]">*</span></p>
                  
                  <input 
                    type="text" 
                    v-model="deleteConfirmationCode"
                    placeholder="KODE BARANG..." 
                    class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-[#D9534F] transition-colors h-10"
                  />
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="p-6 pt-2 flex items-center justify-between">
                <p class="text-sm text-[#D9534F] italic font-medium">*Wajib diisi</p>
                <div class="flex items-center gap-3">
                  <button 
                    @click="closeDeleteModal"
                    class="px-6 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-bold rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleDelete"
                    :disabled="!isDeleteValid"
                    class="px-6 py-2.5 bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
                  >
                    Konfirmasi Penghapusan
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
