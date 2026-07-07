<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus,
  Eye,
  Pencil
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CreateAssetModal from '../Modals/CreateAssetModal.vue';
import EditAssetModal from '../Modals/EditAssetModal.vue';
import DetailAssetModal from '../Modals/DetailAssetModal.vue';

interface Props {
  lot: {
    id: number;
    number: string;
    po_number: string;
    date_of_receipt: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id: number;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    updated_at: string;
    
    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_brand: string;
    barang_nama: string;
    barang_specification: string;
    barang_category: string;
    barang_subcategory: string;
    barang_uom: string;
  };
  units: {
    id: number;
    number: string;
    status: string;
    condition: string;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
  }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const statusFilter = ref('');
const conditionFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);
const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
};

// Asset Modal Setup (Units)
const isCreateAssetModalOpen = ref(false);
const isEditAssetModalOpen = ref(false);
const selectedAssetsForEdit = ref<any[]>([]);

const isVehicle = computed(() => {
  const category = (props.lot.barang_category || '').toLowerCase();
  const subcategory = (props.lot.barang_subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

const openCreateAssetModal = () => {
  isCreateAssetModalOpen.value = true;
};

const openEditAssetModal = (asset: any) => {
  const assetObj = (asset && typeof asset === 'object' && 'value' in asset) ? asset.value : asset;
  if (!assetObj) return;
  selectedAssetsForEdit.value = [assetObj];
  isEditAssetModalOpen.value = true;
};

const handleEditTerpilih = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length === 1) {
    openEditAssetModal(selectedRows[0].original);
  } else if (selectedRows.length > 1) {
    selectedAssetsForEdit.value = selectedRows.map((row: any) => row.original);
    isEditAssetModalOpen.value = true;
  }
};

const handleAssetSuccess = () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
};

const getExportData = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return props.units || [];
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length > 0) {
    return selectedRows.map((row: any) => row.original);
  }
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode Aset', 'Status', 'Kondisi', 'Nilai', 'Lokasi Penyimpanan', 'TNKB (Nopol)'];
  const rows = data.map((item: any) => [
    `"${item.number}"`,
    `"${getStatusLabel(item.status)}"`,
    `"${item.condition}"`,
    `"${item.price}"`,
    `"${formatLocation(item.location, item.floor, item.room)}"`,
    `"${item.vehicle_registration || '-'}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map((e: any) => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `daftar_aset_lot_${props.lot.number}_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
};

const page = usePage();

// Filtered Units list for Table
const filteredUnits = computed(() => {
  let list = props.units || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(u => 
      (u.number && u.number.toLowerCase().includes(q)) ||
      (formatLocation(u.location, u.floor, u.room).toLowerCase().includes(q))
    );
  }

  if (statusFilter.value) {
    list = list.filter(u => (u.status || '').toLowerCase() === statusFilter.value.toLowerCase());
  }

  if (conditionFilter.value) {
    list = list.filter(u => (u.condition || '').toLowerCase() === conditionFilter.value.toLowerCase());
  }

  return list;
});

// Dynamic values for dropdown filters
const availableStatuses = ['Tersedia', 'Dipinjam', 'Perbaikan', 'Rusak Total', 'Hilang', 'Pending', 'Tidak Aktif'];
const availableConditions = ['Baik', 'Kurang Baik', 'Rusak'];

const getStatusLabel = (status: string) => {
  return status || '';
};

const getConditionLabel = (cond: string) => {
  return cond || '';
};

// Table Columns configuration
const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [
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
      accessorKey: 'number',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kode Aset',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
    },
    {
      accessorKey: 'status',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Status',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const status = row.getValue('status') as string || '';
        const proposedStatus = row.original.proposed_status;
        return h(StatusBadge, {
          status,
          proposedStatus,
          class: 'rounded-sm'
        });
      }
    },
    {
      accessorKey: 'condition',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kondisi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const cond = row.getValue('condition') as string || '';
        let textClass = 'text-foreground';
        if (cond === 'Baik') textClass = 'text-emerald-600 font-semibold';
        else if (cond === 'Kurang Baik') textClass = 'text-amber-600 font-semibold';
        else if (cond === 'Rusak') textClass = 'text-rose-600 font-semibold';
        
        return h('span', { class: textClass }, cond);
      }
    },
    {
      accessorKey: 'location',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Lokasi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const r = row.original;
        return h('div', { class: 'text-muted-foreground text-sm' }, formatLocation(r.location, r.floor, r.room));
      }
    },
    {
      accessorKey: 'price',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nilai',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm font-medium' }, formatRupiah(row.original.price)),
    },
    {
      id: 'actions',
      size: 100,
      header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
      cell: ({ row }) => {
        return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
          h(Button, {
            variant: 'table-view',
            size: 'icon-sm',
            title: 'Lihat Detail',
            onClick: () => openViewAssetModal(row.original)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, 'Lihat Detail')
          ])
        ]);
      }
    }
  ];

  return cols;
});

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
  document.addEventListener('keydown', closeOnEscape);
});

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateAssetModalOpen.value) {
      isCreateAssetModalOpen.value = false;
    } else if (isEditAssetModalOpen.value) {
      isEditAssetModalOpen.value = false;
    } else if (isViewAssetModalOpen.value) {
      isViewAssetModalOpen.value = false;
    }
  }
};

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

const totalAsetTerpilihCount = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return 0;
  return Object.keys(dataTableRef.value.table.getState().rowSelection).length;
});
</script>

<template>
  <div class="space-y-4">
    <!-- Detail LOT Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">Detail LOT</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
          <img v-if="props.lot.imageUrl" :src="'/storage/' + props.lot.imageUrl" class="w-full h-full object-cover" />
          <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
        </div>

        <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-4">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.lot.barang_code }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.lot.barang_brand }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.lot.barang_nama }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
            <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
            <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
            <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
          </div>
          <div class="md:col-span-8">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.number }}</p>
            <p class="text-foreground">Jumlah stok tersedia: {{ props.units.filter(u => u.status === 'Tersedia').length }}</p>
            <p class="text-foreground">Jumlah stok diawal: {{ props.units.length }}</p>
            <p class="text-foreground">Lokasi <span class="italic">default</span>: {{ formatLocation(props.lot.location, props.lot.floor, props.lot.room) }}</p>
            <p class="text-foreground">Nomor PO: {{ props.lot.po_number }}</p>
            <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(props.lot.date_of_receipt) }}</p>
            <p class="text-foreground">Harga satuan <span class="italic">default</span>: {{ formatRupiah(props.lot.unitPrice) }}</p>
            <p class="text-foreground">Organizer: {{ props.lot.organizer }}</p>
            <p class="text-foreground">Vendor: {{ props.lot.vendor }}</p>
            <p class="text-foreground">Pembaruan terakhir: {{ props.lot.updated_at }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar Aset Card -->
    <div class="bg-card rounded-xl border border-border px-4 py-3 shadow-sm overflow-hidden">
      <div class="no-print">
        <h2 class="text-lg font-bold text-foreground mb-4">Daftar Aset</h2>

        <!-- Filters Row -->
        <div class="mb-4 flex flex-wrap items-end gap-4">
          <div class="space-y-1.5 flex-grow min-w-[250px] max-w-sm">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
            <TableSearch 
              v-model="searchQuery"
              placeholder="Cari Aset..." 
            />
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ statusFilter ? getStatusLabel(statusFilter) : 'Semua status' }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="statusFilter = ''">Semua status</DropdownMenuItem>
              <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="statusFilter = st">
                {{ getStatusLabel(st) }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !conditionFilter ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ conditionFilter ? getConditionLabel(conditionFilter) : 'Semua kondisi' }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="conditionFilter = ''">Semua kondisi</DropdownMenuItem>
              <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="conditionFilter = cond">
                {{ getConditionLabel(cond) }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
            <span>Baris per halaman</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
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
        </div>

        <!-- Actions Row -->
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4 pt-2">
          <div class="space-y-2 flex-1 min-w-0">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
            <div class="flex flex-wrap gap-2">
              <!-- Edit Terpilih -->
              <Button 
                @click="handleEditTerpilih()"
                :disabled="totalAsetTerpilihCount === 0"
                variant="more-round-warning"
              >
                <Pencil class="w-4 h-4" />
                <span class="hidden sm:inline">Edit Terpilih</span>
              </Button>
              <ExportButtonGroup 
                @export-excel="handleExportExcel"
                @export-csv="handleExportCSV"
              />
            </div>
          </div>
          
          <Button @click="openCreateAssetModal" variant="primary" size="lg">
            <Plus class="w-4 h-4" />
            <span>Aset Baru</span>
          </Button>
        </div>
      </div>

      <!-- Table -->
      <div class="pb-2">
        <DataTable 
          ref="dataTableRef"
          :columns="columns" 
          :data="filteredUnits" 
          :filter-value="searchQuery"
          :default-sorting="[{ id: 'number', desc: false }]"
        />
      </div>

      <div class="text-xs text-muted-foreground pl-1 mt-1">
        {{ totalAsetTerpilihCount }} dari {{ filteredUnits.length }} baris dipilih
      </div>
    </div>
  </div>

  <CreateAssetModal
    v-model:open="isCreateAssetModalOpen"
    :lot="props.lot"
    :units="props.units"
    :barang="{ category: props.lot.barang_category }"
    :locations="props.locations"
    :floors="props.floors"
    :rooms="props.rooms"
    @success="handleAssetSuccess"
  />

  <EditAssetModal
    v-model:open="isEditAssetModalOpen"
    :items="selectedAssetsForEdit"
    :lot="props.lot"
    :barang="{ category: props.lot.barang_category }"
    :locations="props.locations"
    :floors="props.floors"
    :rooms="props.rooms"
    @success="handleAssetSuccess"
  />

  <!-- Detail Asset Modal (Units) -->
  <DetailAssetModal
    v-model:open="isViewAssetModalOpen"
    :asset="selectedAssetForView"
    :lot="props.lot"
    @edit="openEditAssetModal"
  />
</template>
