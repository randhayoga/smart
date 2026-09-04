<script setup lang="ts">
/**
 * Request History Detail Page
 * Displays full details, multi-step lifecycle stepper, item list,
 * handover scheduling, return scheduling, and asset placement tracking for a request/loan.
 */
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { addNotification } from '@/stores/notificationStore';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import RequestCancelModal from '@/Pages/Smart/User/Modals/RequestCancelModal.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import { formatDate } from '@/lib/utils';
import {
  Stepper,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperSeparator,
  StepperTitle,
  StepperDescription,
} from '@/Components/ui/stepper';
import {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/Components/ui/dialog";
import { 
  ArrowLeft,
  Check,
  CheckCircle2, 
  Clock, 
  X, 
  AlertCircle,
  ArrowUpDown,
  Trash2
} from 'lucide-vue-next';
import { type RequestStatus, type RawRequestStatus } from '@/lib/requestStatus';

// --- Data Types & Props ---
interface RequestItem {
  id: number;
  barang_id?: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  is_consumable?: boolean;
  uom?: string;
  assets?: string[];
  status?: string;
}

interface RequestHistory {
  id: number;
  uuid?: string;
  number: string;
  type: 'permintaan' | 'peminjaman';
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: RequestStatus | string;
  raw_status: RawRequestStatus | string;
  created_at: string; // format YYYY-MM-DD
  items: RequestItem[];
  approval?: {
    id?: number;
    note?: string | null;
    decision?: string | null;
    decided_at?: string | null;
    approver_name?: string | null;
  } | null;
  approval_by?: string | null;
  approval_at?: string | null;
  confirmation_by?: string | null;
  confirmation_at?: string | null;
  approver_name?: string | null;
  return_confirmed_by?: string | null;
  handover_method?: string | null;
  handover_time?: string | null;
  handover_location?: string | null;
  handover_note?: string | null;
  logs?: any[];
}

const props = defineProps<{
  requestId: string | number;
  user?: any;
  request: RequestHistory;
  placements?: Record<string, string>;
}>();

const requestState = ref<RequestHistory>(props.request);

watch(() => props.request, (newVal) => {
  requestState.value = newVal;
}, { deep: true });

const request = computed((): RequestHistory => {
  return requestState.value;
});

const isPeminjaman = computed(() => request.value?.type === 'peminjaman');
const typeLabel = computed(() => isPeminjaman.value ? 'peminjaman' : 'permintaan');
const typeLabelTitle = computed(() => isPeminjaman.value ? 'Peminjaman' : 'Permintaan');

// --- Cancellation Modal State ---
const isCancelModalOpen = ref(false);

// --- Handover (Serah Terima) Modal & State ---
const isHandoverModalOpen = ref(false);
const handoverMethod = ref('Pilih');
const handoverDate = ref('');
const handoverTimeOnly = ref('');
const handoverTime = ref(''); // Combined string for timeline
const handoverLocation = ref('Ruang GA / IT Support'); 
const handoverNotes = ref('');
const errorMessage = ref('');

/** Parses date string format e.g. "22/05/2026 09:00" to Date object */
const parseDurationDate = (dateStr: string) => {
  const parts = dateStr.trim().split(' ');
  if (parts.length < 2) return null;
  const dateParts = parts[0].split(/[-/]/);
  const timeParts = parts[1].split(':');
  if (dateParts.length !== 3 || timeParts.length !== 2) return null;
  
  const day = parseInt(dateParts[0], 10);
  const month = parseInt(dateParts[1], 10) - 1; // 0-indexed month
  const year = parseInt(dateParts[2], 10);
  const hours = parseInt(timeParts[0], 10);
  const minutes = parseInt(timeParts[1], 10);
  
  return new Date(year, month, day, hours, minutes);
};

/** Checks whether the request is automatically scheduled (e.g. 1 day prior to start date) */
const isAutoScheduled = computed(() => {
  const r = request.value;
  if (!r || r.type !== 'peminjaman' || !r.durationStart) return false;
  if (r.raw_status !== 'confirm') return false;

  const startDate = parseDurationDate(r.durationStart);
  if (!startDate) return false;

  // 1 day before starting date
  const oneDayBefore = new Date(startDate.getTime() - 24 * 60 * 60 * 1000);
  const now = new Date();
  return now >= oneDayBefore;
});

const effectiveHandoverMethod = computed(() => {
  if (isAutoScheduled.value) return 'Ambil sendiri';
  if (request.value.handover_method) return request.value.handover_method;
  return handoverMethod.value && handoverMethod.value !== 'Pilih' ? handoverMethod.value : 'Ambil sendiri';
});

const effectiveHandoverTime = computed(() => {
  if (isAutoScheduled.value) return request.value.durationStart || '';
  if (request.value.handover_time) return request.value.handover_time;
  return handoverTime.value || request.value.durationStart || '';
});

const effectiveHandoverLocation = computed(() => {
  if (isAutoScheduled.value) return 'Ruang GA / IT Support';
  if (request.value.handover_location) return request.value.handover_location;
  return handoverLocation.value;
});

const formattedTimeForBanner = computed(() => {
  const dt = effectiveHandoverTime.value;
  if (!dt) return '';
  return dt.replace(' ', ' jam ');
});

const openHandoverModal = () => {
  handoverMethod.value = 'Pilih';
  handoverDate.value = '';
  handoverTimeOnly.value = '';
  errorMessage.value = '';
  isHandoverModalOpen.value = true;
};
const closeHandoverModal = () => {
  isHandoverModalOpen.value = false;
};

const onInputChange = () => {
  errorMessage.value = '';
};

/** Submit handover scheduling settings */
const handleSaveHandover = () => {
  // Validate required fields
  if (!handoverMethod.value || handoverMethod.value === 'Pilih') {
    errorMessage.value = 'Metode penyerahan wajib dipilih.';
    return;
  }
  if (!handoverDate.value) {
    errorMessage.value = 'Tanggal penyerahan wajib diisi.';
    return;
  }
  if (!handoverTimeOnly.value) {
    errorMessage.value = 'Jam penyerahan wajib diisi.';
    return;
  }

  router.post(route('smart.history.handover', props.requestId), {
    method: handoverMethod.value,
    scheduled_date: `${handoverDate.value} ${handoverTimeOnly.value}`,
    location: handoverLocation.value,
    note: handoverNotes.value,
  }, {
    onSuccess: () => {
      const dateParts = handoverDate.value.split('-');
      const formattedDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : handoverDate.value;
      handoverTime.value = `${formattedDate} ${handoverTimeOnly.value}`;

      toast.success('Serah terima berhasil diatur!');
      closeHandoverModal();
    },
    onError: (errs) => {
      errorMessage.value = Object.values(errs).join(', ');
    }
  });
};

// --- Return Modal & State ---
const isReturnModalOpen = ref(false);
const returnMethod = ref('Pilih');
const returnDate = ref('');
const returnTimeOnly = ref('');
const returnTime = ref(''); // Combined string for timeline
const returnLocation = ref('Ruang GA / IT Support'); 
const returnNotes = ref('');
const returnErrorMessage = ref('');

const openReturnModal = () => {
  returnMethod.value = 'Pilih';
  returnDate.value = '';
  returnTimeOnly.value = '';
  returnErrorMessage.value = '';
  isReturnModalOpen.value = true;
};

const closeReturnModal = () => {
  isReturnModalOpen.value = false;
};

const onReturnInputChange = () => {
  returnErrorMessage.value = '';
};

/** Submit return scheduling details */
const handleSaveReturn = () => {
  if (!returnMethod.value || returnMethod.value === 'Pilih') {
    returnErrorMessage.value = 'Metode pengembalian wajib dipilih.';
    return;
  }
  if (!returnDate.value) {
    returnErrorMessage.value = 'Tanggal pengembalian wajib diisi.';
    return;
  }
  if (!returnTimeOnly.value) {
    returnErrorMessage.value = 'Jam pengembalian wajib diisi.';
    return;
  }

  router.post(route('smart.history.return', props.requestId), {
    method: returnMethod.value,
    scheduled_date: `${returnDate.value} ${returnTimeOnly.value}`,
    location: returnLocation.value,
    note: returnNotes.value,
  }, {
    onSuccess: () => {
      const dateParts = returnDate.value.split('-');
      const formattedDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : returnDate.value;
      returnTime.value = `${formattedDate} ${returnTimeOnly.value}`;

      toast.success('Pengembalian aset berhasil diatur!');
      closeReturnModal();
    },
    onError: (errs) => {
      returnErrorMessage.value = Object.values(errs).join(', ');
    }
  });
};

// --- Item Received Confirmation State ---
const isConfirmReceivedModalOpen = ref(false);

const handleConfirmReceived = () => {
  isConfirmReceivedModalOpen.value = true;
};

const closeConfirmReceivedModal = () => {
  isConfirmReceivedModalOpen.value = false;
};

/** Mark assets as received by user */
const confirmReceivedAction = () => {
  router.post(route('smart.history.receive', props.requestId), {}, {
    onSuccess: () => {
      toast.success('Aset berhasil dikonfirmasi telah diterima!');
      closeConfirmReceivedModal();
    }
  });
};

// --- Asset Placement State & Modal ---
const assetPlacements = ref<Record<string, string>>({
  ...(props.placements || {})
});

watch(() => props.placements, (newPlacements) => {
  if (newPlacements) {
    assetPlacements.value = { ...assetPlacements.value, ...newPlacements };
  }
}, { deep: true });

const isAssetPlacementModalOpen = ref(false);
const selectedItemForPlacement = ref<RequestItem | null>(null);
const returnPlacementType = ref<'seragam' | 'beragam'>('seragam');
const singlePlacementLocation = ref('');
const beragamPlacementLocations = ref<Record<string, string>>({});
const searchQuery = ref('');
const itemsPerPage = ref<string | number>('Semua baris');
const currentPage = ref(1);
const sortAsc = ref(true);

const activeItemForPlacement = computed(() => {
  return selectedItemForPlacement.value || request.value.items[0];
});

/** Open placement modal for item asset tags */
const openAssetPlacementModal = (item: RequestItem) => {
  selectedItemForPlacement.value = item;
  searchQuery.value = '';
  currentPage.value = 1;
  
  if (item && item.assets && item.assets.length > 0) {
    item.assets.forEach(asset => {
      beragamPlacementLocations.value = { ...beragamPlacementLocations.value };
      beragamPlacementLocations.value[asset] = assetPlacements.value[asset] || '';
    });

    const firstLoc = assetPlacements.value[item.assets[0]] || '';
    const allSame = firstLoc && item.assets.every(asset => assetPlacements.value[asset] === firstLoc);
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
  
  let list = item.assets.filter(asset => 
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

/** Action to handle return initiation or concluding consumable requests */
const handleReturnAction = () => {
  if (!requestState.value) return;

  if (request.value.type === 'peminjaman') {
    openReturnModal();
  } else {
    requestState.value.status = 'Selesai';
    toast.success('Permintaan barang habis pakai selesai!');
  }
};

/** Save asset placement locations */
const confirmAssetPlacement = () => {
  const item = activeItemForPlacement.value;
  if (!item || !item.assets) return;

  const tempPlacements = { ...assetPlacements.value };

  if (returnPlacementType.value === 'seragam') {
    if (!singlePlacementLocation.value) {
      toast.warning('Tolong pilih lokasi penempatan aset.');
      return;
    }
    item.assets.forEach(asset => {
      tempPlacements[asset] = singlePlacementLocation.value;
    });
  } else {
    const unselected = item.assets.some(asset => !beragamPlacementLocations.value[asset]);
    if (unselected) {
      toast.warning('Tolong pilih lokasi penempatan untuk semua aset.');
      return;
    }
    item.assets.forEach(asset => {
      tempPlacements[asset] = beragamPlacementLocations.value[asset];
    });
  }

  router.post(route('smart.placement.update'), {
    placements: tempPlacements
  }, {
    onSuccess: () => {
      assetPlacements.value = tempPlacements;
      isAssetPlacementModalOpen.value = false;
      toast.success('Penempatan aset berhasil disimpan!');
      const itemName = item.brand ? `${item.brand} ${item.spec || ''}` : 'Aset';
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

// --- Timeline & Lifecycle Stepper Logic ---
interface TimelineStep {
  title: string;
  time?: string;
  status: 'done' | 'active' | 'pending' | 'rejected' | 'action-required';
  description?: string;
  user?: string;
}
const timelineSteps = computed((): TimelineStep[] => {
  const r = request.value;
  if (!r) return [];
  const steps: TimelineStep[] = [];

  // Step 1: Created
  steps.push({
    title: `${typeLabelTitle.value} dibuat`,
    time: r.created_at ? `${formatDate(r.created_at)} 08:30` : '',
    status: 'done'
  });

  // Step 2: Historical Logs
  if (r.logs && Array.isArray(r.logs)) {
    const sortedLogs = [...r.logs].sort((a, b) => a.id - b.id);
    sortedLogs.forEach(log => {
      if (log.status_to === 'wait') return;

      let title = '';
      let status: 'done' | 'rejected' | 'pending' = 'done';
      let description = log.note || '';

      if (log.status_to === 'approve') {
        const approverName = log.user || r.approval_by || r.approver_name || '-';
        title = 'Di-approve';
        description = `${typeLabelTitle.value} disetujui Manager: <span class="font-bold text-foreground">${approverName}</span>${r.approval?.note ? `<br>Catatan: ${r.approval.note}` : ''}`;
      } else if (log.status_to === 'partial') {
        title = 'Disetujui sebagian (Partial)';
        description = description || `${typeLabelTitle.value} disetujui sebagian oleh Admin.`;
      } else if (log.status_to === 'confirm') {
        if (log.status_from === 'partial') {
          title = 'Alokasi Barang Tambahan Dikonfirmasi';
        } else {
          if (log.note && log.note.includes('diatur oleh pengguna')) {
            title = 'Jadwal Serah Terima Diatur';
          } else {
            title = 'Dikonfirmasi';
          }
        }
        description = description || `${typeLabelTitle.value} dikonfirmasi oleh Admin.`;
      } else if (log.status_to === 'borrow') {
        title = 'Serah Terima Selesai & Dipinjam';
        description = description || 'Aset telah diserahkan dan dipinjam.';
      } else if (log.status_to === 'return') {
        title = 'Pengembalian Diajukan';
        description = description || 'Jadwal pengembalian telah diajukan.';
      } else if (log.status_to === 'success') {
        if (log.status_from === 'return') {
          title = 'Pengembalian Selesai';
          description = description || 'Aset dikembalikan & semua proses selesai.';
        } else {
          title = 'Serah Terima Selesai';
          description = description || 'Barang habis pakai telah diserahkan & proses selesai.';
        }
      } else if (log.status_to === 'reject') {
        const approverName = log.user || r.approval_by || r.approver_name || '-';
        title = 'Ditolak';
        status = 'rejected';
        description = `${typeLabelTitle.value} ditolak Manager: <span class="font-bold text-foreground">${approverName}</span>${r.approval?.note ? `<br>Alasan: ${r.approval.note}` : ''}`;
      } else if (log.status_to === 'cancel') {
        title = 'Dibatalkan';
        status = 'rejected';
        description = description || `${typeLabelTitle.value} dibatalkan.`;
      } else if (log.status_to === 'pending') {
        if (log.status_from === 'confirm') {
          title = 'Serah Terima Sebagian Diterima';
          status = 'done';
        } else {
          title = 'Pending';
          status = 'pending';
        }
        description = description || (log.status_from === 'confirm' ? 'Barang telah diterima oleh pengguna.' : `${typeLabelTitle.value} ditunda (pending) oleh Admin.`);
      }

      if (title) {
        steps.push({
          title,
          time: log.time,
          status,
          user: log.user || undefined,
          description
        });
      }
    });
  }

  // Step 3: Active / Next steps
  const isFinalStatus = ['success', 'reject', 'cancel'].includes(r.raw_status);
  if (!isFinalStatus) {
    if (r.raw_status === 'wait') {
      steps.push({
        title: 'Menunggu approval',
        status: 'active',
        description: `Menunggu approval dari Manager: <span class="font-bold text-foreground">${r.approver_name || '-'}</span>`
      });
    } else if (r.raw_status === 'approve') {
      const approverName = r.approval_by || r.approver_name || '-';
      steps.push({
        title: 'Menunggu konfirmasi Admin',
        status: 'active',
        description: `Menunggu alokasi aset dan konfirmasi Admin.`
      });
    } else if (r.raw_status === 'pending') {
      steps.push({
        title: 'Pending',
        status: 'pending',
        description: 'Pemesanan pending/ditunda oleh Admin karena stok barang habis.'
      });
    } else if (r.raw_status === 'partial') {
      steps.push({
        title: 'Serah Terima',
        status: 'action-required',
        description: 'Serah Terima perlu diatur!'
      });
    } else if (r.raw_status === 'confirm') {
      const isScheduled = !!r.handover_time || isAutoScheduled.value;
      steps.push({
        title: 'Serah Terima',
        status: 'action-required',
        description: isScheduled ? 'scheduled-details' : 'Serah Terima perlu diatur!'
      });
    } else if (r.raw_status === 'borrow') {
      steps.push({
        title: r.type === 'peminjaman' ? 'Aset sedang Anda pinjam' : 'Aset sedang Anda gunakan',
        status: 'action-required',
        description: 'show-return-action'
      });
    } else if (r.raw_status === 'return') {
      steps.push({
        title: 'Dalam Proses Pengembalian',
        status: 'active',
        description: 'Jadwal pengembalian telah diajukan. Menunggu konfirmasi Admin.'
      });
    }
  }

  return steps;
});

const activeStepIndex = computed(() => {
  const idx = timelineSteps.value.findIndex(
    s => s.status === 'active' || s.status === 'action-required' || s.status === 'rejected'
  );
  if (idx !== -1) return idx + 1;
  return timelineSteps.value.length + 1;
});
</script>

<template>
  <Head :title="'Detail ' + request.number" />

  <AppLayout :title="'Detail ' + typeLabelTitle">
    <!-- ── Breadcrumb & Tombol Kembali (In Line) ── -->
    <div class="flex items-center justify-between gap-4 mb-6">
      <Breadcrumb>
        <BreadcrumbList class="text-xs md:text-sm">
          <BreadcrumbItem>
            <Link :href="route('smart.history')" class="text-muted-foreground hover:text-foreground transition-colors">
              Riwayat 
            </Link>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <span class="text-foreground font-medium">{{ request.number }}</span>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <div class="flex items-center gap-2.5">
        <Button 
          v-if="request.status === 'Menunggu approval'" 
          variant="destructive" 
          size="lg"
          class="font-semibold text-xs flex items-center gap-1.5 h-8 px-3"
          @click="isCancelModalOpen = true"
        >
          <Trash2 class="w-3.5 h-3.5" />
          Batalkan {{ typeLabelTitle }}
        </Button>

        <Link :href="route('smart.history')">
          <Button variant="white" class="flex items-center gap-1.5 h-8 px-3 text-xs font-semibold">
            <ArrowLeft class="w-3.5 h-3.5" />
            Kembali ke Riwayat
          </Button>
        </Link>
      </div>
    </div>

    <!-- ── Alert Banner: Tindakan Diperlukan atau Pengingat Serah Terima ── -->
    <div 
      v-if="request.raw_status === 'confirm' || request.raw_status === 'partial'" 
      class="mb-6 p-4 border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-[0.875rem] flex flex-col sm:flex-row justify-between items-center gap-4 animate-in fade-in slide-in-from-top-1 duration-300"
    >
      <div class="flex items-center gap-2.5 text-indigo-700 dark:text-indigo-300">
        <AlertCircle class="w-5 h-5 shrink-0" />
        <span v-if="!request.handover_time && !isAutoScheduled" class="text-sm font-semibold">
          Tindakan diperlukan: serah terima belum diatur!
        </span>
        <span v-else class="text-sm font-semibold">
          Pengingat bahwa Anda harus mengambil sendiri pada {{ formattedTimeForBanner }}
        </span>
      </div>

      <Button 
        v-if="!request.handover_time && !isAutoScheduled"
        @click="openHandoverModal" 
        variant="primary"
        size="sm"
        class="font-semibold text-xs h-9 px-5"
      >
        Atur Serah Terima
      </Button>
      <Button 
        v-else
        @click="handleConfirmReceived" 
        variant="primary"
        size="sm"
        class="font-semibold text-xs h-9 px-5"
      >
        Aset Telah Diterima
      </Button>
    </div>

    <!-- ── Grid Layout Dua Kolom ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Kolom Kiri (Detail Permintaan & Daftar Barang) -->
      <div class="lg:col-span-2 space-y-6">
        
        <!-- Card Detail Info -->
        <div class="bg-card border border-border rounded-[0.875rem] p-5 space-y-1.5">
          <div class="text-base space-y-1">
            <h2 class="text-base font-bold text-foreground">
              <span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}
            </h2>

            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">PIC Approval:</span> 
              <span class="font-semibold ml-1">
                {{ request.approver_name || '-' }}
              </span>
            </p>
            
            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">Pemanfaatan:</span> 
              <span class="font-semibold ml-1">
                {{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}
              </span>
            </p>

            <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-sm text-foreground">
              <span class="text-muted-foreground">Durasi:</span>
              <span class="font-medium ml-1">
                <template v-if="request.durationEnd">
                  {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
                </template>
                <template v-else>
                  {{ request.durationStart }} s.d. - (Tanpa Tenggat Waktu)
                </template>
              </span>
            </p>

            <p class="text-xs text-muted-foreground pt-1">
              <span>{{ typeLabelTitle }} dibuat pada:</span>
              <span class="font-medium text-foreground/80 ml-1">{{ formatDate(request.created_at) }}</span>
            </p>
          </div>
        </div>

        <!-- Card Daftar Barang -->
        <div>
          <p class="text-xs text-muted-foreground font-medium mb-3">Daftar Barang:</p>
          
          <ScrollArea 
            class="border border-border rounded-[0.875rem] bg-card"
            :class="[
              (request.raw_status === 'confirm' || request.raw_status === 'partial')
                ? 'h-[calc(100vh-35rem)] sm:h-[calc(100vh-32rem)] lg:h-[calc(100vh-30.5rem)]'
                : 'h-[calc(100vh-30.5rem)] sm:h-[calc(100vh-27rem)] lg:h-[calc(100vh-24.5rem)]'
            ]"
          >
            <div class="p-2.5 sm:p-5">
              <div class="space-y-4">
                <AssetItemCard 
                  v-for="item in request.items" 
                  :key="item.id" 
                  :brand="item.brand !== '-' ? (item.name && item.name !== 'Tidak Spesifik' ? `${item.brand} ${item.name} ${item.spec}` : `${item.brand} ${item.spec}`) : item.subcategory"
                  :category="item.category"
                  :subcategory="item.subcategory"
                  :quantity="item.quantity"
                  :uom="item.uom || 'satuan'"
                  :assets="item.assets || []"
                  :imageUrl="item.imageUrl"
                  :placements="assetPlacements"
                  :status="item.status"
                  :is-consumable="item.is_consumable"
                >
                  <template 
                    #footer 
                    v-if="['confirm', 'partial', 'borrow'].includes(request.raw_status) && !item.is_consumable && item.assets && item.assets.length > 0"
                  >
                    <div class="flex gap-2.5">
                      <Button 
                        @click="openAssetPlacementModal(item)"
                        variant="white"
                        size="sm"
                        class="font-semibold text-xs"
                      >
                        Catat Penempatan Aset
                      </Button>
                    </div>
                  </template>
                </AssetItemCard>
              </div>
            </div>
          </ScrollArea>
        </div>

      </div>

      <!-- Kolom Kanan (Timeline / Tahapan) -->
      <div class="space-y-6">
        
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sm:p-6 relative">
          <!-- Header without border/count matching Daftar Barang style -->
          <p class="text-xs text-muted-foreground font-medium mb-4">Tahapan {{ typeLabelTitle }}:</p>

          <!-- shadcn-vue Vertical Stepper -->
          <Stepper
            orientation="vertical"
            :model-value="activeStepIndex"
            :linear="false"
            class="flex flex-col gap-6"
          >
            <StepperItem
              v-for="(step, idx) in timelineSteps"
              :key="idx"
              :step="idx + 1"
              :completed="step.status === 'done'"
              v-slot="{ state }"
              class="relative flex items-start gap-4 group"
            >
              <!-- Stepper Separator (Connecting line) -->
              <StepperSeparator
                v-if="idx !== timelineSteps.length - 1"
                class="absolute left-4 -translate-x-1/2 top-8 -bottom-6 w-0.5 bg-border"
                :class="[
                  (step.status === 'done' || state === 'completed')
                    ? '!bg-green-700'
                    : 'group-data-[state=completed]:!bg-green-700'
                ]"
              />

              <!-- Stepper Trigger & Indicator -->
              <StepperTrigger as-child class="cursor-default pointer-events-none">
                <StepperIndicator
                  class="z-10 w-8 h-8 rounded-full text-xs font-semibold shrink-0 border transition-all"
                  :class="[
                    step.status === 'rejected'
                      ? '!bg-destructive/10 !text-destructive !border-destructive/40'
                      : step.status === 'action-required'
                        ? '!bg-primary !text-primary-foreground !border-primary ring-2 ring-primary/25 ring-offset-2 ring-offset-background'
                        : (step.status === 'done' || state === 'completed')
                          ? '!bg-green-700 !text-white !border-green-700'
                          : state === 'active'
                            ? '!bg-amber-500/15 !text-amber-600 dark:!text-amber-400 !border-amber-500 ring-2 ring-amber-500/40 ring-offset-2 ring-offset-background'
                            : '!bg-muted !text-muted-foreground !border-border'
                  ]"
                >
                  <Check v-if="step.status === 'done' || state === 'completed'" class="w-4 h-4 text-white" />
                  <X v-else-if="step.status === 'rejected'" class="w-4 h-4" />
                  <AlertCircle v-else-if="step.status === 'action-required'" class="w-4 h-4" />
                  <Clock v-else-if="step.status === 'active'" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                  <span v-else>{{ idx + 1 }}</span>
                </StepperIndicator>
              </StepperTrigger>

              <!-- Stepper Content -->
              <div class="flex-1 min-w-0 pt-0.5">
                <div class="flex flex-col gap-0.5">
                  <StepperTitle
                    class="text-sm transition-colors"
                    :class="[
                      step.status === 'rejected'
                        ? 'text-destructive font-bold'
                        : step.status === 'action-required'
                          ? 'text-primary font-bold'
                          : state === 'active'
                            ? 'text-amber-700 dark:text-amber-400 font-bold'
                            : (step.status === 'done' || state === 'completed')
                              ? 'text-green-700 font-semibold'
                              : 'text-muted-foreground font-medium'
                    ]"
                  >
                    {{ step.title }}
                  </StepperTitle>

                  <!-- Time metadata -->
                  <div v-if="step.time" class="text-[11px] text-muted-foreground">
                    <span>{{ step.time }}</span>
                  </div>

                  <!-- Description -->
                  <StepperDescription
                    v-if="step.description && step.description !== 'scheduled-details' && step.description !== 'show-return-action'"
                    class="text-xs text-muted-foreground leading-relaxed"
                  >
                    <span v-html="step.description"></span>
                  </StepperDescription>
                </div>

                <!-- Scheduled Handover Details -->
                <div 
                  v-if="step.description === 'scheduled-details'" 
                  class="mt-2.5 p-3 rounded-lg bg-muted/40 border border-border/80 text-xs space-y-1.5"
                >
                  <div class="flex items-start justify-between gap-2">
                    <span class="text-muted-foreground">Metode</span>
                    <span class="font-medium text-foreground text-right">{{ effectiveHandoverMethod }}</span>
                  </div>
                  <div class="flex items-start justify-between gap-2">
                    <span class="text-muted-foreground">Tempat</span>
                    <span class="font-medium text-foreground text-right">{{ effectiveHandoverLocation }}</span>
                  </div>
                  <div class="flex items-start justify-between gap-2">
                    <span class="text-muted-foreground">Waktu</span>
                    <span class="font-medium text-foreground text-right">{{ effectiveHandoverTime }}</span>
                  </div>
                </div>

                <!-- Action Button -->
                <div v-if="step.status === 'action-required'" class="mt-3">
                  <Button 
                    v-if="step.description !== 'scheduled-details' && step.description !== 'show-return-action'"
                    @click="openHandoverModal" 
                    variant="primary"
                    size="sm"
                    class="font-semibold text-xs h-8 px-3.5"
                  >
                    Atur Serah Terima
                  </Button>

                  <Button 
                    v-else-if="step.description === 'scheduled-details'"
                    @click="handleConfirmReceived" 
                    variant="primary"
                    size="sm"
                    class="font-semibold text-xs h-8 px-4"
                  >
                    Aset Telah Diterima
                  </Button>

                  <Button 
                    v-else-if="step.description === 'show-return-action'"
                    @click="handleReturnAction" 
                    variant="primary"
                    size="sm"
                    class="font-semibold text-xs h-8 px-4"
                  >
                    {{ request.type === 'peminjaman' ? 'Atur Pengembalian' : 'Selesai' }}
                  </Button>
                </div>
              </div>
            </StepperItem>
          </Stepper>
        </div>
      </div>
    </div>

    <!-- ── Modal Atur Serah Terima (Dialog) ── -->
    <Dialog :open="isHandoverModalOpen" @update:open="val => isHandoverModalOpen = val">
      <DialogContent class="sm:max-w-[42rem] rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
          <div>
            <DialogTitle class="text-lg font-bold text-foreground">Serah Terima</DialogTitle>
            <DialogDescription class="sr-only">
              Formulir pengaturan jadwal dan metode serah terima barang.
            </DialogDescription>
          </div>
          <button @click="closeHandoverModal" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[70vh] space-y-4">
          <div class="p-3.5 rounded-[0.875rem] bg-muted/40 border border-border space-y-1 text-sm">
            <h4 class="font-bold text-foreground"><span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}</h4>
            <p class="text-foreground">
              <span class="text-muted-foreground">PIC Approval:</span> 
              <span class="font-semibold ml-1">{{ request.approver_name || '-' }}</span>
            </p>
            <p class="text-foreground">
              <span class="text-muted-foreground">Pemanfaatan:</span> 
              <span class="font-semibold ml-1">{{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}</span>
            </p>
            <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-foreground">
              <span class="text-muted-foreground">Durasi:</span>
              <span class="font-medium ml-1">{{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)</span>
            </p>
          </div>

          <div class="space-y-4 pt-1">
            <!-- Row: Metode Penyerahan -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-foreground block">
                Metode penyerahan <span class="text-destructive">*</span>
              </label>
              <Select v-model="handoverMethod" @update:modelValue="onInputChange">
                <SelectTrigger class="w-full rounded-[0.875rem] h-10 text-sm">
                  <SelectValue placeholder="Pilih metode" />
                </SelectTrigger>
                <SelectContent class="rounded-[0.875rem]">
                  <SelectItem value="Ambil sendiri">Ambil sendiri</SelectItem>
                  <SelectItem value="Diantar ke ruangan">Diantar ke ruangan</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Row: Jadwal Penyerahan -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-foreground block">
                Jadwal penyerahan <span class="text-destructive">*</span>
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input 
                  type="date" 
                  v-model="handoverDate" 
                  @change="onInputChange"
                  class="h-10 px-3.5 rounded-[0.875rem] border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground w-full"
                />
                <input 
                  type="time" 
                  v-model="handoverTimeOnly" 
                  @change="onInputChange"
                  class="h-10 px-3.5 rounded-[0.875rem] border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground w-full"
                />
              </div>
            </div>

            <div v-if="errorMessage" class="p-3 rounded-[0.875rem] bg-destructive/10 border border-destructive/20 text-xs font-semibold text-destructive">
              {{ errorMessage }}
            </div>
          </div>
        </div>

        <div class="py-4 px-4 sm:px-6 border-t border-border flex items-center justify-between gap-3">
          <span class="text-xs italic text-destructive font-medium">*Wajib diisi</span>
          <div class="flex items-center gap-3">
            <Button variant="white" size="lg" @click="closeHandoverModal">
              Batal
            </Button>
            <Button variant="primary" size="lg" @click="handleSaveHandover">
              Konfirmasi Serah Terima
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- ── Modal Konfirmasi Serah Terima Selesai (Dialog) ── -->
    <Dialog :open="isConfirmReceivedModalOpen" @update:open="val => isConfirmReceivedModalOpen = val">
      <DialogContent class="sm:max-w-md rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
          <div>
            <DialogTitle class="text-base font-bold text-foreground">Konfirmasi Serah Terima</DialogTitle>
            <DialogDescription class="sr-only">Konfirmasi penerimaan barang.</DialogDescription>
          </div>
          <button @click="closeConfirmReceivedModal" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div class="p-6 text-center space-y-3">
          <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-600 mx-auto flex items-center justify-center">
            <CheckCircle2 class="w-6 h-6" />
          </div>
          <p class="text-sm font-semibold text-foreground">
            Saya telah menerima semua barang dengan sesuai dan dalam kondisi yang baik.
          </p>
        </div>

        <div class="py-3 px-4 sm:px-6 border-t border-border flex items-center justify-end gap-3">
          <Button variant="white" size="sm" @click="closeConfirmReceivedModal">
            Tidak
          </Button>
          <Button variant="primary" size="sm" @click="confirmReceivedAction">
            Iya, Sudah Diterima
          </Button>
        </div>
      </DialogContent>
    </Dialog>

    <!-- ── Modal Atur Pengembalian (Dialog) ── -->
    <Dialog :open="isReturnModalOpen" @update:open="val => isReturnModalOpen = val">
      <DialogContent class="sm:max-w-[42rem] rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
          <div>
            <DialogTitle class="text-lg font-bold text-foreground">Pengembalian</DialogTitle>
            <DialogDescription class="sr-only">Formulir pengaturan jadwal pengembalian aset.</DialogDescription>
          </div>
          <button @click="closeReturnModal" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[70vh] space-y-4">
          <div class="p-3.5 rounded-[0.875rem] bg-muted/40 border border-border space-y-1 text-sm">
            <h4 class="font-bold text-foreground"><span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}</h4>
            <p class="text-foreground">
              <span class="text-muted-foreground">PIC Approval:</span> 
              <span class="font-semibold ml-1">{{ request.approver_name || '-' }}</span>
            </p>
            <p class="text-foreground">
              <span class="text-muted-foreground">Pemanfaatan:</span> 
              <span class="font-semibold ml-1">{{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}</span>
            </p>
            <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-foreground">
              <span class="text-muted-foreground">Durasi:</span>
              <span class="font-medium ml-1">{{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)</span>
            </p>
          </div>

          <div class="space-y-4 pt-1">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-foreground block">
                Metode pengembalian <span class="text-destructive">*</span>
              </label>
              <Select v-model="returnMethod" @update:modelValue="onReturnInputChange">
                <SelectTrigger class="w-full rounded-[0.875rem] h-10 text-sm">
                  <SelectValue placeholder="Pilih metode" />
                </SelectTrigger>
                <SelectContent class="rounded-[0.875rem]">
                  <SelectItem value="Kembalikan sendiri">Kembalikan sendiri</SelectItem>
                  <SelectItem value="Diantar ke GA / IT Support">Diantar ke GA / IT Support</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-foreground block">
                Jadwal pengembalian <span class="text-destructive">*</span>
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input 
                  type="date" 
                  v-model="returnDate" 
                  @change="onReturnInputChange"
                  class="h-10 px-3.5 rounded-[0.875rem] border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground w-full"
                />
                <input 
                  type="time" 
                  v-model="returnTimeOnly" 
                  @change="onReturnInputChange"
                  class="h-10 px-3.5 rounded-[0.875rem] border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground w-full"
                />
              </div>
            </div>

            <div v-if="returnErrorMessage" class="p-3 rounded-[0.875rem] bg-destructive/10 border border-destructive/20 text-xs font-semibold text-destructive">
              {{ returnErrorMessage }}
            </div>
          </div>
        </div>

        <div class="py-4 px-4 sm:px-6 border-t border-border flex items-center justify-between gap-3">
          <span class="text-xs italic text-destructive font-medium">*Wajib diisi</span>
          <div class="flex items-center gap-3">
            <Button variant="white" size="lg" @click="closeReturnModal">
              Batal
            </Button>
            <Button variant="primary" size="lg" @click="handleSaveReturn">
              Konfirmasi Pengembalian
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- ── Modal Penempatan Aset (Dialog) ── -->
    <Dialog :open="isAssetPlacementModalOpen && !!activeItemForPlacement" @update:open="val => isAssetPlacementModalOpen = val">
      <DialogContent class="sm:max-w-[46rem] rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
          <div>
            <DialogTitle class="text-lg font-bold text-foreground">Pilih Penempatan Aset</DialogTitle>
            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
              Pilih lokasi penempatan untuk aset yang diserahterimakan
            </DialogDescription>
          </div>
          <button @click="isAssetPlacementModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div v-if="activeItemForPlacement" class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[70vh] space-y-4">
          <!-- Item Card Detail -->
          <div class="p-3 rounded-[0.875rem] bg-muted/40 border border-border flex gap-3.5 items-center">
            <div class="w-14 h-14 rounded-[0.625rem] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center">
              <img 
                v-if="activeItemForPlacement.imageUrl" 
                :src="activeItemForPlacement.imageUrl.startsWith('http') || activeItemForPlacement.imageUrl.startsWith('/') ? activeItemForPlacement.imageUrl : '/media/' + activeItemForPlacement.imageUrl" 
                class="w-full h-full object-cover" 
              />
              <div v-else class="text-xs font-black text-muted-foreground/50 select-none">
                {{ activeItemForPlacement.subcategory.substring(0, 3).toUpperCase() }}
              </div>
            </div>
            <div class="min-w-0 flex-grow space-y-0.5">
              <h4 class="text-sm font-bold text-foreground truncate">
                {{ activeItemForPlacement.brand !== '-' ? `${activeItemForPlacement.brand} ${activeItemForPlacement.spec}` : activeItemForPlacement.subcategory }}
              </h4>
              <p class="text-xs text-muted-foreground">
                {{ activeItemForPlacement.category }} ({{ activeItemForPlacement.subcategory }})
              </p>
              <p class="text-xs text-foreground font-semibold">
                Jumlah diminta: {{ activeItemForPlacement.quantity }} {{ activeItemForPlacement.uom || 'satuan' }}
              </p>
            </div>
          </div>

          <!-- Placement Type Switch -->
          <div class="flex gap-2">
            <Button 
              type="button"
              size="sm"
              :variant="returnPlacementType === 'seragam' ? 'primary-border' : 'white'"
              @click="returnPlacementType = 'seragam'"
              class="font-semibold text-xs"
            >
              Seragam
            </Button>
            <Button 
              type="button"
              size="sm"
              :variant="returnPlacementType === 'beragam' ? 'primary-border' : 'white'"
              @click="returnPlacementType = 'beragam'"
              class="font-semibold text-xs"
            >
              Beragam
            </Button>
          </div>

          <!-- Seragam View -->
          <div v-if="returnPlacementType === 'seragam'" class="space-y-1.5 py-2">
            <label class="text-xs font-semibold text-foreground block">
              Lokasi penempatan aset:
            </label>
            <Select v-model="singlePlacementLocation">
              <SelectTrigger class="w-full rounded-[0.875rem] h-10 text-sm">
                <SelectValue placeholder="Pilih tempat" />
              </SelectTrigger>
              <SelectContent class="rounded-[0.875rem]">
                <SelectItem value="Mega Mendung">Mega Mendung</SelectItem>
                <SelectItem value="Tiga Negeri">Tiga Negeri</SelectItem>
                <SelectItem value="Gudang GA">Gudang GA</SelectItem>
                <SelectItem value="Ruang IT">Ruang IT</SelectItem>
                <SelectItem value="Ruang IFS">Ruang IFS</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Beragam View -->
          <div v-else class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Cari Kode Aset..." 
                class="h-9 w-full sm:max-w-xs px-3.5 rounded-[0.875rem] border border-input bg-background text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground"
              />
            </div>

            <!-- Table -->
            <div class="border border-border rounded-[0.875rem] overflow-hidden bg-card">
              <table class="min-w-full divide-y divide-border">
                <thead class="bg-muted/30">
                  <tr>
                    <th 
                      scope="col" 
                      @click="sortAsc = !sortAsc"
                      class="px-4 py-2.5 text-left text-xs font-bold text-foreground cursor-pointer hover:bg-muted/50 select-none w-1/2 transition-colors"
                    >
                      <div class="flex items-center gap-1.5">
                        <span>Kode Aset</span>
                        <ArrowUpDown class="w-3 h-3 opacity-60 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-bold text-foreground w-1/2">
                      Penempatan Aset
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <tr v-for="asset in paginatedAssets" :key="asset" class="hover:bg-muted/10 transition-colors">
                    <td class="px-4 py-2.5 whitespace-nowrap text-xs font-mono font-medium text-foreground">
                      {{ asset }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-xs text-foreground">
                      <Select v-model="beragamPlacementLocations[asset]">
                        <SelectTrigger class="w-full rounded-[0.875rem] h-8 text-xs">
                          <SelectValue placeholder="Pilih tempat" />
                        </SelectTrigger>
                        <SelectContent class="rounded-[0.875rem]">
                          <SelectItem value="Mega Mendung">Mega Mendung</SelectItem>
                          <SelectItem value="Tiga Negeri">Tiga Negeri</SelectItem>
                          <SelectItem value="Gudang GA">Gudang GA</SelectItem>
                          <SelectItem value="Ruang IT">Ruang IT</SelectItem>
                          <SelectItem value="Ruang IFS">Ruang IFS</SelectItem>
                        </SelectContent>
                      </Select>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="py-4 px-4 sm:px-6 border-t border-border flex items-center justify-end gap-3">
          <Button variant="white" size="lg" @click="isAssetPlacementModalOpen = false">
            Batal
          </Button>
          <Button variant="primary" size="lg" @click="confirmAssetPlacement">
            Konfirmasi Penempatan Aset
          </Button>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Modal Pembatalan Permintaan -->
    <RequestCancelModal
      v-model:open="isCancelModalOpen"
      :request="request"
    />
  </AppLayout>
</template>

<style scoped>
.before\:bg-border::before {
  background-color: var(--border);
}
.animate-in {
  animation-duration: 200ms;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
