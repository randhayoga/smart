<script setup lang="ts">
import { ref, onMounted, computed, h, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { addNotification } from '@/stores/notificationStore';
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
  AlertCircle,
  Check
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

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
  items?: any[];
  placements?: Record<string, string>;
}

const props = defineProps<Props>();

// If items is passed from props, use it, otherwise fallback to the mock/default
const items = computed(() => props.items || [
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
    showAssets: false,
    imageUrl: null
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
    showAssets: false,
    imageUrl: null
  }
]);

const timeline = computed(() => {
  const hData = props.handover;
  if (!hData) return [];

  return [
    { status: 'Permintaan dibuat', time: hData.createdAt, completed: true },
    { status: 'Di-approve', user: hData.approval_by || hData.approver || 'Manager', time: hData.approval_at || hData.createdAt, completed: true },
    { status: 'Dikonfirmasi', user: hData.confirmation_by || 'Admin', time: hData.confirmation_at || hData.createdAt, completed: true },
    { 
      status: 'Serah Terima', 
      method: hData.method, 
      location: hData.location, 
      time: hData.time, 
      active: true 
    },
  ];
});

// Allocation Modal State
const isAllocModalOpen = ref(false);
const activeItemToAllocate = ref<any>(null);

const openAllocModal = (item: any) => {
  activeItemToAllocate.value = item;
  isAllocModalOpen.value = true;
  
  nextTick(() => {
    if (assetTableRef.value && assetTableRef.value.table) {
      // Clear selection first
      assetTableRef.value.table.resetRowSelection();
      
      const assigned = item.assets || [];
      const available = item.availableUnits || [];
      const newSelection: Record<string, boolean> = {};
      
      available.forEach((unit: any, index: number) => {
        if (assigned.includes(unit.assetCode)) {
          newSelection[index.toString()] = true;
        }
      });
      
      assetTableRef.value.table.setRowSelection(newSelection);
    }
  });
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
  const item = activeItemToAllocate.value;
  if (!item) return;

  const selection = assetTableRef.value?.table?.getState()?.rowSelection || {};
  const data = item.availableUnits || [];
  const selectedAssetNumbers = Object.keys(selection)
    .filter(key => selection[key])
    .map(index => data[Number(index)]?.assetCode)
    .filter(Boolean);

  if (selectedAssetNumbers.length !== Number(item.quantity)) {
    toast.warning(`Jumlah aset terpilih (${selectedAssetNumbers.length}) harus sama dengan jumlah yang diminta (${item.quantity}).`);
    return;
  }

  router.post(route('smart.handover.allocate', props.handover.id), {
    request_item_id: item.id,
    unit_numbers: selectedAssetNumbers
  }, {
    onSuccess: () => {
      closeAllocModal();
      item.assets = selectedAssetNumbers;
      toast.success('Alokasi Aset berhasil disimpan!');
      const itemName = (item.brand ? `${item.brand} ${item.spec || ''}` : '') || item.name || 'Aset';
      addNotification(
        'Alokasi Aset',
        `Alokasi aset untuk "${itemName}" berhasil disimpan.`,
        'success'
      );
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
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
  toast.info('Permintaan dibatalkan');
  addNotification(
    'Permintaan Dibatalkan',
    `Permintaan dibatalkan dengan catatan: ${cancelNote.value}`,
    'info'
  );
  closeCancelModal();
};

// --- ASSET PLACEMENT LIFE-CYCLE FOR ADMIN ---
// Asset placement state loaded from localStorage
const loadPlacements = (): Record<string, string> => {
  try {
    const stored = localStorage.getItem('smart_asset_placements');
    if (stored) {
      return JSON.parse(stored);
    }
  } catch (e) {
    console.error(e);
  }
  return {
    'GPU-NVIDIA-2026-001': 'Mega Mendung',
    'WBD-SAKURA-2026-101': 'Tiga Negeri',
    'MON-DELL-2026-901': 'Mega Mendung',
    'MON-DELL-2026-902': 'Tiga Negeri',
  };
};

const assetPlacements = ref<Record<string, string>>({
  ...loadPlacements(),
  ...(props.placements || {})
});

// Placement Modal State
const isAssetPlacementModalOpen = ref(false);
const selectedItemForPlacement = ref<any>(null);
const returnPlacementType = ref<'seragam' | 'beragam'>('seragam');
const singlePlacementLocation = ref('');
const beragamPlacementLocations = ref<Record<string, string>>({});
const searchQuery = ref('');
const itemsPerPage = ref<string | number>('Semua baris');
const currentPage = ref(1);
const sortAsc = ref(true);

const activeItemForPlacement = computed(() => {
  return selectedItemForPlacement.value || (items.value && items.value[0]);
});

const openAssetPlacementModal = (item: any) => {
  selectedItemForPlacement.value = item;
  searchQuery.value = '';
  currentPage.value = 1;
  
  if (item && item.assets && item.assets.length > 0) {
    // Populate beragam placements
    item.assets.forEach((asset: string) => {
      beragamPlacementLocations.value = { ...beragamPlacementLocations.value };
      beragamPlacementLocations.value[asset] = assetPlacements.value[asset] || '';
    });

    // Check if all assets have the same location and it is not empty
    const firstLoc = assetPlacements.value[item.assets[0]] || '';
    const allSame = firstLoc && item.assets.every((asset: string) => assetPlacements.value[asset] === firstLoc);
    if (allSame) {
      returnPlacementType.value = 'seragam';
      singlePlacementLocation.value = firstLoc;
    } else {
      returnPlacementType.value = 'beragam';
      singlePlacementLocation.value = '';
    }
  } else {
    returnPlacementType.value = 'seragam';
    singlePlacementLocation.value = '';
  }
  isAssetPlacementModalOpen.value = true;
};

const filteredAssets = computed(() => {
  const item = activeItemForPlacement.value;
  if (!item || !item.assets) return [];
  
  let list = item.assets.filter((asset: string) => 
    asset.toLowerCase().includes(searchQuery.value.toLowerCase())
  );

  const sortedList = [...list];
  if (sortAsc.value) {
    sortedList.sort();
  } else {
    sortedList.sort().reverse();
  }

  return sortedList;
});

const paginatedAssets = computed(() => {
  const list = filteredAssets.value;
  if (itemsPerPage.value === 'Semua baris') return list;
  
  const limit = Number(itemsPerPage.value);
  const start = (currentPage.value - 1) * limit;
  return list.slice(start, start + limit);
});

const totalPages = computed(() => {
  if (itemsPerPage.value === 'Semua baris') return 1;
  const limit = Number(itemsPerPage.value);
  return Math.ceil(filteredAssets.value.length / limit);
});

const confirmAssetPlacement = () => {
  const item = activeItemForPlacement.value;
  if (!item || !item.assets) return;

  const tempPlacements = { ...assetPlacements.value };

  if (returnPlacementType.value === 'seragam') {
    if (!singlePlacementLocation.value) {
      toast.warning('Tolong pilih lokasi penempatan aset.');
      return;
    }
    item.assets.forEach((asset: string) => {
      tempPlacements[asset] = singlePlacementLocation.value;
    });
  } else {
    const unselected = item.assets.some((asset: string) => !beragamPlacementLocations.value[asset]);
    if (unselected) {
      toast.warning('Tolong pilih lokasi penempatan untuk semua aset.');
      return;
    }
    item.assets.forEach((asset: string) => {
      tempPlacements[asset] = beragamPlacementLocations.value[asset];
    });
  }

  router.post(route('smart.placement.update'), {
    placements: tempPlacements
  }, {
    onSuccess: () => {
      assetPlacements.value = tempPlacements;
      // Save to localStorage
      try {
        localStorage.setItem('smart_asset_placements', JSON.stringify(tempPlacements));
      } catch (e) {
        console.error(e);
      }
      isAssetPlacementModalOpen.value = false;
      toast.success('Penempatan aset berhasil disimpan!');
      const itemName = (item.brand ? `${item.brand} ${item.spec || ''}` : '') || item.name || 'Aset';
      addNotification(
        'Penempatan Aset',
        `Penempatan aset untuk "${itemName}" berhasil disimpan.`,
        'success'
      );
    },
    onError: () => {
      toast.error('Gagal menyimpan penempatan aset.');
    }
  });
};

// Check if all assets in props.items have placements recorded
const isAllPlacementRecorded = computed(() => {
  if (!items.value) return true;
  for (const item of items.value) {
    if (item.assets && item.assets.length > 0) {
      for (const asset of item.assets) {
        if (!assetPlacements.value[asset]) {
          return false;
        }
      }
    }
  }
  return true;
});

const openFirstItemPlacementModal = () => {
  if (!items.value) return;
  const targetItem = items.value.find((item: any) => item.assets && item.assets.length > 0);
  if (targetItem) {
    openAssetPlacementModal(targetItem);
  }
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

    <!-- Alert / Warning Banner for Asset Placement -->
    <div v-if="!isAllPlacementRecorded" class="mb-6 p-4 rounded-xl border border-amber-200 bg-amber-50/40 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-amber-800 animate-in fade-in duration-300">
      <div class="flex items-center gap-3">
        <AlertCircle class="w-5 h-5 shrink-0 text-amber-500" />
        <p class="text-sm font-medium">
          Tindakan diperlukan: Tolong catat penempatan Aset sebelum serah terima dilakukan.
        </p>
      </div>
      <Button 
        @click="openFirstItemPlacementModal" 
        class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-4 h-9 rounded-lg text-xs md:text-sm shadow-sm transition-colors mt-2 sm:mt-0"
      >
        Catat Penempatan
      </Button>
    </div>

    <!-- Alert / Info Banner -->
    <div class="mb-6 p-4 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center gap-3 text-indigo-700">
      <Info class="w-5 h-5 shrink-0" />
      <p class="text-sm font-medium">
        Pengingat bahwa aset akan diambil pada <span class="font-bold">{{ handover?.time || 'DD/MM/YYYY jam HH:MM' }}</span>
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-sm font-medium text-muted-foreground mb-3">Detail:</h3>
          <div class="space-y-2">
            <h2 class="text-lg md:text-xl font-extrabold text-foreground mb-3">
              {{ handover?.number || '#Nomor_Permintaan' }}
            </h2>
            
            <div class="space-y-1.5 text-sm text-foreground">
              <p>
                <span class="text-muted-foreground">Dibuat oleh:</span> 
                <span class="font-semibold">{{ handover?.requester }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold">{{ handover?.approval_by || handover?.approver || 'Manager' }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold">{{ handover?.createdAt }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">{{ handover?.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ handover?.pemanfaatanDetail }})</span>
              </p>
              <p v-if="handover?.durationStart">
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">{{ handover?.durationStart }} s.d. {{ handover?.durationEnd }} ({{ handover?.durationDays }} hari, {{ handover?.durationHours }} jam)</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-4">Daftar barang:</h3>
          
          <AssetItemCard 
            v-for="item in items" 
            :key="item.id"
            :brand="item.spec ? `${item.brand} ${item.spec}` : item.brand"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
            :imageUrl="item.imageUrl"
            :placements="assetPlacements"
          >
            <template #footer>
              <div class="flex gap-2.5">
                <button 
                  v-if="['approve', 'confirm'].includes(handover?.status)"
                  @click="openAllocModal(item)"
                  class="px-5 py-2.5 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm cursor-pointer"
                >
                  Pilih Alokasi Aset
                </button>
                <button 
                  v-if="['approve', 'confirm'].includes(handover?.status) && item.assets && item.assets.length > 0"
                  @click="openAssetPlacementModal(item)"
                  class="px-5 py-2.5 bg-[#00BCD4] hover:bg-[#00ACC1] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm cursor-pointer"
                >
                  Catat Penempatan Aset
                </button>
              </div>
            </template>
          </AssetItemCard>
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm relative overflow-hidden">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-6">Tahapan:</h3>
          
          <!-- Vertical Timestep Stepper -->
          <div class="relative pl-8 space-y-8 before:absolute before:left-[15px] before:top-[10px] before:bottom-[10px] before:w-[2px] before:bg-border">
            <div 
              v-for="(step, index) in timeline" 
              :key="index" 
              class="relative"
            >
              <!-- Icon/Indicator -->
              <div class="absolute -left-[32px] top-0 w-8 h-8 rounded-full bg-card flex items-center justify-center z-10">
                <!-- Status Done (Green Check Circle) -->
                <div 
                  v-if="step.completed" 
                  class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card"
                >
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
                
                <!-- Status Active / Current (Pulsing Indigo Alert/Exclamation) -->
                <div 
                  v-else-if="step.active" 
                  class="w-7 h-7 rounded-full border-2 border-[#6366F1] flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-[#6366F1]/20 opacity-40 animate-ping"></span>
                  <span class="text-sm font-extrabold text-[#6366F1]">!</span>
                </div>

                <!-- Status Pending/Next (Grey Dot) -->
                <div 
                  v-else 
                  class="w-6 h-6 rounded-full border-2 border-muted-foreground/30 flex items-center justify-center bg-card"
                >
                  <div class="w-2 h-2 rounded-full bg-muted-foreground/30"></div>
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 
                    class="text-sm font-bold"
                    :class="{
                      'text-green-600': step.completed,
                      'text-[#6366F1]': step.active && !step.completed,
                      'text-muted-foreground': !step.completed && !step.active
                    }"
                  >
                    {{ step.status }}
                  </h4>
                  <p v-if="step.user" class="text-xs font-semibold text-green-600 mt-0.5">
                    oleh {{ step.user }}
                  </p>
                  <p v-if="step.time && !step.active" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  
                  <div v-if="step.active" class="mt-2 space-y-1 text-xs text-muted-foreground">
                    <p><span class="font-semibold text-foreground/80">Metode:</span> {{ step.method }}</p>
                    <p><span class="font-semibold text-foreground/80">Tempat:</span> {{ step.location }}</p>
                    <p><span class="font-semibold text-foreground/80">Waktu:</span> {{ step.time }}</p>
                  </div>
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
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !lotFilter ? 'text-muted-foreground' : 'text-foreground']">
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
                      <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (assetRowsPerPage === 'Semua baris' || !assetRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
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
                :data="activeItemToAllocate?.availableUnits || []" 
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
                <p>Dibuat oleh: {{ handover?.requester }}</p>
                <p>PIC Approval: {{ handover?.approval_by || handover?.approver || 'Manager' }}</p>
                <p>Waktu dibuat: {{ handover?.createdAt }}</p>
                <p>Pemanfaatan: {{ handover?.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ handover?.pemanfaatanDetail }})</p>
                <p v-if="handover?.durationStart">Durasi: {{ handover?.durationStart }} s.d. {{ handover?.durationEnd }} ({{ handover?.durationDays }} hari, {{ handover?.durationHours }} jam)</p>
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

    <!-- ============================================================
         Modal Pilih Penempatan Aset (Teleport & Backdrop)
         ============================================================ -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isAssetPlacementModalOpen && activeItemForPlacement" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-card text-foreground rounded-[20px] shadow-2xl w-full max-w-[800px] flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-card shrink-0">
              <div>
                <h3 class="text-lg font-bold text-foreground">Pilih Penempatan Aset</h3>
                <p class="text-xs text-muted-foreground mt-1">
                  Tolong pilih lokasi penempatan untuk aset yang akan diserahterimakan
                </p>
              </div>
              <button @click="isAssetPlacementModalOpen = false" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <div class="border-b border-border"></div>

            <!-- Item Card Detail -->
            <div class="px-6 py-5 flex gap-4 items-center shrink-0">
              <!-- Thumbnail -->
              <div class="w-[84px] h-[84px] rounded-[16px] bg-gradient-to-br from-gray-100 to-gray-200 border border-gray-200/50 overflow-hidden shrink-0 flex items-center justify-center shadow-sm">
                <img 
                  v-if="activeItemForPlacement.imageUrl" 
                  :src="activeItemForPlacement.imageUrl.startsWith('http') || activeItemForPlacement.imageUrl.startsWith('/') ? activeItemForPlacement.imageUrl : '/storage/' + activeItemForPlacement.imageUrl" 
                  class="w-full h-full object-cover" 
                />
                <div v-else class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 opacity-60"></div>
              </div>
              <!-- Details -->
              <div class="min-w-0 flex-grow">
                <h4 class="text-base md:text-lg font-bold text-foreground leading-snug">
                  {{ activeItemForPlacement.brand }}
                </h4>
                <p class="text-xs md:text-sm text-muted-foreground mt-0.5">
                  {{ activeItemForPlacement.category }} ({{ activeItemForPlacement.subcategory }})
                </p>
                <p class="text-xs md:text-sm text-muted-foreground mt-1.5">
                  Jumlah dipinjam: <span class="text-foreground font-medium">{{ activeItemForPlacement.quantity }} satuan</span>
                </p>
              </div>
            </div>

            <div class="border-b border-border"></div>

            <!-- Tab Switch & Main Content -->
            <div class="p-6 flex-grow overflow-y-auto max-h-[400px] space-y-5">
              <!-- Switch -->
              <div class="flex gap-2.5">
                <button 
                  type="button"
                  @click="returnPlacementType = 'seragam'"
                  class="px-5 py-2 text-xs font-bold rounded-full border transition-all"
                  :class="returnPlacementType === 'seragam' 
                    ? 'border-[#6366F1] text-[#6366F1] bg-[#6366F1]/5' 
                    : 'border-border text-foreground hover:bg-muted'"
                >
                  Seragam
                </button>
                <button 
                  type="button"
                  @click="returnPlacementType = 'beragam'"
                  class="px-5 py-2 text-xs font-bold rounded-full border transition-all"
                  :class="returnPlacementType === 'beragam' 
                    ? 'border-[#6366F1] text-[#6366F1] bg-[#6366F1]/5' 
                    : 'border-border text-foreground hover:bg-muted'"
                >
                  Beragam
                </button>
              </div>

              <!-- Seragam View -->
              <div v-if="returnPlacementType === 'seragam'" class="flex items-center gap-4 py-3">
                <label class="text-sm font-semibold text-foreground shrink-0">
                  Lokasi penempatan aset:
                </label>
                <div class="w-full max-w-[280px]">
                  <Select v-model="singlePlacementLocation">
                    <SelectTrigger class="w-full rounded-full border-input bg-background h-10 px-4">
                      <SelectValue placeholder="Pilih tempat" />
                    </SelectTrigger>
                    <SelectContent class="bg-card border border-border rounded-xl shadow-lg z-[10000]">
                      <SelectItem value="Mega Mendung">Mega Mendung</SelectItem>
                      <SelectItem value="Tiga Negeri">Tiga Negeri</SelectItem>
                      <SelectItem value="Gudang GA">Gudang GA</SelectItem>
                      <SelectItem value="Ruang IT">Ruang IT</SelectItem>
                      <SelectItem value="Ruang IFS">Ruang IFS</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- Beragam View -->
              <div v-else class="space-y-4">
                <!-- Search & Items Per Page -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                  <div class="flex flex-col gap-1.5 flex-grow max-w-xs">
                    <span class="text-xs text-muted-foreground font-medium">Filter</span>
                    <input 
                      type="text" 
                      v-model="searchQuery" 
                      placeholder="Cari Kode Aset..." 
                      class="h-10 w-full px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-[#6366F1]/20 focus:border-[#6366F1] transition-colors text-foreground"
                    />
                  </div>
                  
                  <div class="flex items-center gap-3 text-sm text-foreground shrink-0 mb-0.5">
                    <span class="font-medium text-muted-foreground">Baris per halaman</span>
                    <select 
                      v-model="itemsPerPage" 
                      class="h-10 px-4 pr-8 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-[#6366F1]/20 focus:border-[#6366F1] transition-colors text-foreground cursor-pointer appearance-none relative"
                      style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22/%3E%3C/svg%3E'); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                    >
                      <option value="Semua baris">Semua baris</option>
                      <option :value="5">5</option>
                      <option :value="10">10</option>
                      <option :value="25">25</option>
                    </select>
                  </div>
                </div>

                <!-- Table -->
                <div class="border border-border rounded-xl overflow-hidden bg-card">
                  <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted/15">
                      <tr>
                        <th 
                          scope="col" 
                          @click="sortAsc = !sortAsc"
                          class="px-6 py-3.5 text-left text-sm font-bold text-foreground cursor-pointer hover:bg-muted/30 select-none w-1/2 transition-colors"
                        >
                          <div class="flex items-center gap-1.5">
                            <span>Kode Aset</span>
                            <ArrowUpDown class="w-3.5 h-3.5 opacity-60 text-muted-foreground" />
                          </div>
                        </th>
                        <th 
                          scope="col" 
                          class="px-6 py-3.5 text-left text-sm font-bold text-foreground w-1/2"
                        >
                          Penempatan Aset
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                      <tr v-for="asset in paginatedAssets" :key="asset" class="hover:bg-muted/5 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-foreground">
                          {{ asset }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-foreground">
                          <div class="relative w-full max-w-[240px]">
                            <Select v-model="beragamPlacementLocations[asset]">
                              <SelectTrigger class="w-full rounded-full border-input bg-background h-10 px-4">
                                <SelectValue placeholder="Pilih tempat" />
                              </SelectTrigger>
                              <SelectContent class="bg-card border border-border rounded-xl shadow-lg z-[10000]">
                                <SelectItem value="Mega Mendung">Mega Mendung</SelectItem>
                                <SelectItem value="Tiga Negeri">Tiga Negeri</SelectItem>
                                <SelectItem value="Gudang GA">Gudang GA</SelectItem>
                                <SelectItem value="Ruang IT">Ruang IT</SelectItem>
                                <SelectItem value="Ruang IFS">Ruang IFS</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="flex justify-end items-center gap-2 pt-2">
                  <button 
                    type="button"
                    @click="currentPage > 1 && (currentPage--)"
                    :disabled="currentPage === 1"
                    class="text-xs font-bold px-3 py-2 rounded-lg text-foreground hover:bg-muted disabled:opacity-40 disabled:hover:bg-transparent transition-all flex items-center gap-1"
                  >
                    &lsaquo; Sebelumnya
                  </button>
                  <button 
                    v-for="page in totalPages" 
                    :key="page"
                    type="button"
                    @click="currentPage = page"
                    class="text-xs font-bold w-8 h-8 rounded-full flex items-center justify-center transition-all border"
                    :class="currentPage === page 
                      ? 'border-[#6366F1]/30 bg-[#6366F1]/10 text-[#6366F1]' 
                      : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground'"
                  >
                    {{ page }}
                  </button>
                  <button 
                    type="button"
                    @click="currentPage < totalPages && (currentPage++)"
                    :disabled="currentPage === totalPages"
                    class="text-xs font-bold px-3 py-2 rounded-lg text-foreground hover:bg-muted disabled:opacity-40 disabled:hover:bg-transparent transition-all flex items-center gap-1"
                  >
                    Selanjutnya &rsaquo;
                  </button>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-border bg-card shrink-0">
              <Button 
                variant="outline"
                @click="isAssetPlacementModalOpen = false"
                class="rounded-full h-10 px-6 font-bold text-sm border-input hover:bg-muted transition-colors"
              >
                Batal
              </Button>
              <Button 
                @click="confirmAssetPlacement"
                class="rounded-full h-10 px-6 font-bold text-sm bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm transition-colors"
              >
                Konfirmasi Penempatan Aset
              </Button>
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
