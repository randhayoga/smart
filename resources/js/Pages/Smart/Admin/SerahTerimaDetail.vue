<script setup lang="ts">
import { ref, onMounted, computed, h } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  ChevronDown, 
  ChevronUp,
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  Search,
  ArrowUpDown,
  AlertCircle
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';

interface Props {
  handover: any;
}

const props = defineProps<Props>();

// Mock internal state for items
const items = ref([
  {
    id: 1,
    brand: 'Asus ROG',
    spec: 'Zephyrus G14',
    category: 'Elektronik',
    subcategory: 'Laptop',
    quantity: 5,
    assets: [
      '2024-LAP-01-ORG-PTRE-01',
      '2024-LAP-01-ORG-PTRE-02',
      '2024-LAP-01-ORG-PTRE-03',
      '2024-LAP-01-ORG-PTRE-04',
      '2024-LAP-01-ORG-PTRE-05',
    ],
    showAssets: false
  },
  {
    id: 2,
    brand: 'Logitech',
    spec: 'MX Master 3S',
    category: 'Elektronik',
    subcategory: 'Mouse',
    quantity: 2,
    assets: [
      '2024-MOU-01-ORG-PTRE-01',
      '2024-MOU-01-ORG-PTRE-02',
    ],
    showAssets: false
  }
]);

const timeline = [
  { status: 'Permintaan dibuat', time: '10-05-2026 09:00', completed: true },
  { status: 'Di-approve', user: 'John Doe', time: '10-05-2026 14:30', completed: true },
  { status: 'Dikonfirmasi', user: 'Radifa', time: '11-05-2026 10:00', completed: true },
  { 
    status: 'Serah Terima', 
    method: 'Ambil sendiri', 
    location: 'Ruang IFS', 
    time: '12-05-2026 10:00', 
    active: true 
  },
];

// Allocation Modal State
const isAllocModalOpen = ref(false);
const activeItemToAllocate = ref<any>(null);

const openAllocModal = (item: any) => {
  activeItemToAllocate.value = item;
  isAllocModalOpen.value = true;
};

const closeAllocModal = () => {
  isAllocModalOpen.value = false;
  setTimeout(() => {
    activeItemToAllocate.value = null;
  }, 200);
};

const dummyAssets = [
  { id: 1, assetCode: 'XXXX-ABC-DE-ORG-PTRE-01', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-01', status: 'Tersedia', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
  { id: 2, assetCode: 'XXXX-ABC-DE-ORG-PTRE-02', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-02', status: 'Tersedia', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
  { id: 3, assetCode: 'XXXX-ABC-DE-ORG-PTRE-03', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-03', status: 'Tersedia', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
  { id: 4, assetCode: 'XXXX-ABC-DE-ORG-PTRE-04', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-04', status: 'Tersedia', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
  { id: 5, assetCode: 'XXXX-ABC-DE-ORG-PTRE-05', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-05', status: 'Tersedia', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
  { id: 6, assetCode: 'XXXX-ABC-DE-ORG-PTRE-06', lotCode: 'LOT-YYYY-CAT-SUB-XXXX-06', status: 'Dipinjam', condition: 'Baik', location: 'Rg. Lorem Ipsum Dolor Sit Amet' },
];

const assetSearchQuery = ref('');
const lotFilter = ref('');
const assetRowsPerPage = ref('Semua baris');
const assetTableRef = ref<any>(null);

const assetColumns: ColumnDef<any>[] = [
  {
    id: 'select',
    size: 50,
    header: ({ table }) => h('div', { class: 'text-center flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-indigo-600 focus:ring-indigo-600/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-indigo-600 focus:ring-indigo-600/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
      })
    ]),
  },
  {
    accessorKey: 'assetCode',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-0 hover:bg-transparent font-bold text-foreground justify-start'
    }, () => [
      'Kode Aset',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-mono' }, row.getValue('assetCode')),
  },
  {
    accessorKey: 'lotCode',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-0 hover:bg-transparent font-bold text-foreground justify-start'
    }, () => [
      'Kode LOT',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-mono' }, row.getValue('lotCode')),
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-0 hover:bg-transparent font-bold text-foreground justify-start'
    }, () => [
      'Status',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('status')),
  },
  {
    accessorKey: 'condition',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-0 hover:bg-transparent font-bold text-foreground justify-start'
    }, () => [
      'Kondisi',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('condition')),
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-0 hover:bg-transparent font-bold text-foreground justify-start'
    }, () => [
      'Lokasi Penyimpanan',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('location')),
  },
];

const handleConfirmAllocation = () => {
  alert('Alokasi Aset berhasil disimpan!');
  closeAllocModal();
};

// Cancellation Modal State
const isCancelModalOpen = ref(false);
const cancelNote = ref('');

const openCancelModal = () => {
  isCancelModalOpen.value = true;
};

const closeCancelModal = () => {
  isCancelModalOpen.value = false;
  setTimeout(() => {
    cancelNote.value = '';
  }, 200);
};

const confirmCancel = () => {
  alert('Permintaan dibatalkan dengan catatan: ' + cancelNote.value);
  closeCancelModal();
};
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/handover">Serah Terima</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ handover?.number || '#Request_ID' }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-4">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan</h1>
    </div>

    <!-- Alert / Info Banner -->
    <div class="mb-6 p-4 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center gap-3 text-indigo-700">
      <Info class="w-5 h-5 shrink-0" />
      <p class="text-sm font-medium">
        Pengingat bahwa aset akan diambil pada <span class="font-bold">{{ handover?.time || 'DD-MM-YYYY jam HH:MM' }}</span>
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <div class="space-y-4">
            <h2 class="text-lg font-bold text-foreground">{{ handover?.number || '#Nomor_Permintaan' }}</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-12 text-sm">
              <div class="space-y-1">
                <p class="text-muted-foreground">Dibuat oleh</p>
                <p class="font-semibold text-foreground">{{ handover?.requester || 'John Doe' }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">PIC Approval</p>
                <p class="font-semibold text-foreground">Jane Doe</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">Waktu dibuat</p>
                <p class="font-semibold text-foreground">10-05-2026 09:00</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">Pemanfaatan</p>
                <p class="font-semibold text-foreground">Internal Project (PRJ-2024-001/Finance)</p>
              </div>
              <div class="md:col-span-2 space-y-1">
                <p class="text-muted-foreground">Durasi</p>
                <p class="font-semibold text-foreground">12-05-2026 10:00 s.d. 19-05-2026 10:00 (7 hari, 0 jam)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm space-y-6">
          <h3 class="text-lg font-bold text-foreground">Daftar barang:</h3>
          
          <AssetItemCard 
            v-for="item in items" 
            :key="item.id"
            :brand="`${item.brand} ${item.spec}`"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
          >
            <template #footer>
              <button 
                @click="openAllocModal(item)"
                class="px-5 py-2 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-semibold rounded-[14px] transition-all shadow-sm"
              >
                Pilih Alokasi Aset
              </button>
            </template>
          </AssetItemCard>
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <h3 class="text-lg font-bold text-foreground mb-6">Tahapan:</h3>
          
          <div class="relative space-y-8 pl-4">
            <!-- Vertical Line -->
            <div class="absolute left-7 top-2 bottom-8 w-0.5 bg-border"></div>

            <div v-for="(step, index) in timeline" :key="index" class="relative flex items-start gap-4">
              <!-- Icon Container -->
              <div class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-card">
                <CheckCircle2 v-if="step.completed" class="w-8 h-8 text-green-500" />
                <div v-else-if="step.active" class="w-8 h-8 rounded-full border-2 border-indigo-600 flex items-center justify-center bg-indigo-50">
                   <div class="w-2.5 h-2.5 rounded-full bg-indigo-600 animate-pulse"></div>
                </div>
                <div v-else class="w-8 h-8 rounded-full border-2 border-border bg-muted flex items-center justify-center">
                  <Clock class="w-4 h-4 text-muted-foreground" />
                </div>
              </div>

              <div class="space-y-1">
                <p class="font-bold text-sm" :class="step.completed ? 'text-green-600' : step.active ? 'text-indigo-600' : 'text-muted-foreground'">
                  {{ step.status }}
                </p>
                <div v-if="step.user" class="text-xs text-foreground font-medium">Oleh {{ step.user }}</div>
                <div v-if="step.time" class="text-xs text-muted-foreground">{{ step.time }}</div>
                
                <div v-if="step.active" class="mt-2 space-y-1">
                  <p class="text-xs font-semibold text-indigo-700">Metode: {{ step.method }}</p>
                  <p class="text-xs font-semibold text-indigo-700">Tempat: {{ step.location }}</p>
                  <p class="text-xs font-semibold text-indigo-700">Waktu: {{ step.time }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-8">
            <button @click="openCancelModal" class="w-full py-2.5 bg-[#D9534F] hover:bg-[#C9302C] text-white font-bold rounded-[14px] transition-all shadow-sm active:scale-[0.98]">
              Batalkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pemilihan Alokasi Aset Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isAllocModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[1100px] max-h-[90vh] flex flex-col overflow-hidden"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-start justify-between p-6 border-b border-border bg-card z-10 shrink-0">
              <div>
                <h3 class="text-xl font-bold mb-1">Pemilihan Alokasi Aset</h3>
                <p class="text-sm text-muted-foreground">Pilih {{ activeItemToAllocate?.quantity || 0 }} aset dari tabel di bawah:</p>
              </div>
              <button @click="closeAllocModal" class="p-1 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto flex-grow bg-card">
              <!-- Filters -->
              <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-4">
                <div class="flex items-end gap-3 w-full max-w-xl">
                  <div class="space-y-1.5 flex-1 max-w-xs">
                    <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                    <TableSearch 
                      v-model="assetSearchQuery"
                      placeholder="Cari Kode Aset..." 
                    />
                  </div>
                  <div class="flex-1 max-w-[200px]">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                          {{ lotFilter || 'Semua LOT' }}
                          <ChevronDown class="w-4 h-4 opacity-50" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="rounded-[14px]" style="width: var(--radix-dropdown-menu-trigger-width); min-width: var(--radix-dropdown-menu-trigger-width);">
                        <DropdownMenuItem @select="lotFilter = ''">Semua LOT</DropdownMenuItem>
                        <DropdownMenuItem @select="lotFilter = 'LOT-1'">LOT-1</DropdownMenuItem>
                        <DropdownMenuItem @select="lotFilter = 'LOT-2'">LOT-2</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <span>Baris per halaman</span>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal">
                        {{ assetRowsPerPage }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="rounded-[14px]" style="width: var(--radix-dropdown-menu-trigger-width); min-width: var(--radix-dropdown-menu-trigger-width);">
                      <DropdownMenuItem @select="assetRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                      <DropdownMenuItem @select="assetRowsPerPage = '10'">10</DropdownMenuItem>
                      <DropdownMenuItem @select="assetRowsPerPage = '25'">25</DropdownMenuItem>
                      <DropdownMenuItem @select="assetRowsPerPage = '50'">50</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>

              <!-- Table -->
              <DataTable 
                ref="assetTableRef"
                :columns="assetColumns" 
                :data="dummyAssets" 
                :filter-value="assetSearchQuery"
                :show-selection-count="false"
              />
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between p-6 border-t border-border bg-card shrink-0">
              <span class="text-sm font-semibold text-foreground">
                {{ Object.keys(assetTableRef?.table?.getState()?.rowSelection || {}).length }} aset terpilih dari {{ activeItemToAllocate?.quantity || 0 }} yang diminta
              </span>
              <button 
                @click="handleConfirmAllocation"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Konfirmasi Alokasi Aset
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Pembatalan Permintaan/Peminjaman Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isCancelModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[886px] min-h-[515px] flex flex-col overflow-hidden"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-border bg-card shrink-0">
              <h3 class="text-xl font-bold">Pembatalan Permintaan/Peminjaman</h3>
              <button @click="closeCancelModal" class="p-1 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto flex-grow bg-card space-y-4">
              <div class="space-y-1 text-sm text-foreground">
                <p class="font-bold mb-2">{{ handover?.number || '#Nomor_Permintaan/#Nomor_Peminjaman' }}</p>
                <p>Dibuat oleh: {{ handover?.requester || 'John Doe' }}</p>
                <p>PIC Approval: Jane Doe</p>
                <p>Waktu dibuat: 10/05/2026 09:00</p>
                <p>Pemanfaatan: Internal Project (PRJ-2024-001/Finance)</p>
                <p>Durasi: 12-05-2026 10:00 s.d. 19-05-2026 10:00 (7 hari, 0 jam)</p>
              </div>

              <div class="border-t border-border pt-4">
                <p class="font-bold text-[#D9534F] mb-4">Apakah Anda yakin untuk membatalkan permintaan/peminjaman ini?</p>
                
                <div class="space-y-2">
                  <label class="text-sm font-medium text-foreground">Catatan (opsional):</label>
                  <textarea 
                    v-model="cancelNote"
                    rows="4" 
                    placeholder="Ketik alasan pembatalan di sini..." 
                    class="w-full p-3 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-border bg-card shrink-0">
              <button 
                @click="closeCancelModal"
                class="px-6 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Tidak
              </button>
              <button 
                @click="confirmCancel"
                class="px-6 py-2.5 bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Iya
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
