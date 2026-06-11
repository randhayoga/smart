<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { addNotification } from '@/stores/notificationStore';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Button } from '@/Components/ui/button';
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
  ArrowLeft,
  CheckCircle2, 
  Clock, 
  XCircle, 
  AlertCircle,
  Package,
  Layers,
  Calendar,
  Layers2,
  Check,
  X,
  ChevronDown,
  ChevronUp,
  Search,
  ArrowUpDown
} from 'lucide-vue-next';

// ─────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────
interface RequestItem {
  id: number;
  subcategory: string;
  brand: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  is_consumable?: boolean;
  assets?: string[];
  stock?: number | null;
  status?: string;
}

interface RequestHistory {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman';
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan' | 'Pending' | 'Partial';
  raw_status: 'wait' | 'approve' | 'confirm' | 'handover' | 'borrow' | 'return' | 'success' | 'reject' | 'cancel' | 'pending' | 'partial';
  created_at: string; // format YYYY-MM-DD
  items: RequestItem[];
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

import { watch } from 'vue';
import { router } from '@inertiajs/vue3';

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

// Formatting Date Helper
const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`; // DD/MM/YYYY
};

// ─────────────────────────────────────────────
// Handover (Serah Terima) Modal & State
// ─────────────────────────────────────────────
const isHandoverModalOpen = ref(false);
const handoverMethod = ref('Pilih');
const handoverDate = ref('');
const handoverTimeOnly = ref('');
const handoverTime = ref(''); // Combined string for timeline
const handoverLocation = ref('Ruang GA / IT Support'); 
const handoverNotes = ref('');
const errorMessage = ref('');

const showToast = ref(false);
const alertToastMessage = ref('');

// Parser durationDate e.g. "22/05/2026 09:00" -> Date object
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

// Check if request is automatically scheduled
const isAutoScheduled = computed(() => {
  const r = request.value;
  if (!r || r.type !== 'peminjaman' || !r.durationStart) return false;
  if (r.raw_status !== 'confirm') return false;

  const startDate = parseDurationDate(r.durationStart);
  if (!startDate) return false;

  // 1 day before starting date
  const oneDayBefore = new Date(startDate.getTime() - 24 * 60 * 60 * 1000);
  const now = new Date(); // May 20, 2026 in system mockup
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

// Formatting datetime display helper
const formatDateTimeDisplay = (dtStr: string) => {
  return dtStr; // already formatted
};

const handleSaveHandover = () => {
  // 1. Validasi Wajib Diisi
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

  // 2. Validasi jadwal penyerahan jika tipe peminjaman aset (tidak boleh melebihi start date)
  if (request.value.type === 'peminjaman' && request.value.durationStart) {
    const limitDate = parseDurationDate(request.value.durationStart);
    const selectedDateTimeStr = `${handoverDate.value}T${handoverTimeOnly.value}`;
    const selectedDateTime = new Date(selectedDateTimeStr);

    if (limitDate && selectedDateTime > limitDate) {
      errorMessage.value = `Jadwal serah terima tidak boleh melebihi tanggal mulai peminjaman (${request.value.durationStart})`;
      return;
    }
  }

  router.post(route('smart.history.handover', props.requestId), {
    method: handoverMethod.value,
    scheduled_date: `${handoverDate.value} ${handoverTimeOnly.value}`,
    location: handoverLocation.value,
    note: handoverNotes.value,
  }, {
    onSuccess: () => {
      // Format tanggal ke DD-MM-YYYY untuk display di timeline
      const dateParts = handoverDate.value.split('-');
      const formattedDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : handoverDate.value;
      handoverTime.value = `${formattedDate} ${handoverTimeOnly.value}`;

      alertToastMessage.value = 'Serah terima berhasil diatur!';
      showToast.value = true;
      setTimeout(() => {
        showToast.value = false;
      }, 4000);
      closeHandoverModal();
    },
    onError: (errs) => {
      errorMessage.value = Object.values(errs).join(', ');
    }
  });
};

// Return Modal State
const isReturnModalOpen = ref(false);
const returnMethod = ref('Pilih');
const returnDate = ref('');
const returnTimeOnly = ref('');
const returnTime = ref(''); // Combined string for timeline
const returnLocation = ref('Ruang GA / IT Support'); 
const returnNotes = ref('');
const returnErrorMessage = ref('');

const effectiveReturnMethod = computed(() => {
  return returnMethod.value && returnMethod.value !== 'Pilih' ? returnMethod.value : 'Kembalikan sendiri';
});

const effectiveReturnTime = computed(() => {
  return returnTime.value || '';
});

const effectiveReturnLocation = computed(() => {
  return returnLocation.value;
});

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
      // Format tanggal ke DD-MM-YYYY untuk display di timeline
      const dateParts = returnDate.value.split('-');
      const formattedDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : returnDate.value;
      returnTime.value = `${formattedDate} ${returnTimeOnly.value}`;

      alertToastMessage.value = 'Pengembalian aset berhasil diatur!';
      showToast.value = true;
      setTimeout(() => {
        showToast.value = false;
      }, 4000);
      closeReturnModal();
    },
    onError: (errs) => {
      returnErrorMessage.value = Object.values(errs).join(', ');
    }
  });
};

const isConfirmReceivedModalOpen = ref(false);

const handleConfirmReceived = () => {
  isConfirmReceivedModalOpen.value = true;
};

const closeConfirmReceivedModal = () => {
  isConfirmReceivedModalOpen.value = false;
};

const confirmReceivedAction = () => {
  router.post(route('smart.history.receive', props.requestId), {}, {
    onSuccess: () => {
      alertToastMessage.value = 'Aset berhasil dikonfirmasi telah diterima!';
      showToast.value = true;
      setTimeout(() => {
        showToast.value = false;
      }, 4000);
      closeConfirmReceivedModal();
    }
  });
};

// Collapsible Aset state
const expandedAssets = ref<Record<number, boolean>>({});
const toggleAssets = (itemId: number) => {
  expandedAssets.value[itemId] = !expandedAssets.value[itemId];
};

// Asset placement state
const assetPlacements = ref<Record<string, string>>({
  ...(props.placements || {})
});

watch(() => props.placements, (newPlacements) => {
  if (newPlacements) {
    assetPlacements.value = { ...assetPlacements.value, ...newPlacements };
  }
}, { deep: true });

// Placement Modal State
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

const openAssetPlacementModal = (item: RequestItem) => {
  selectedItemForPlacement.value = item;
  searchQuery.value = '';
  currentPage.value = 1;
  
  if (item && item.assets && item.assets.length > 0) {
    // Populate beragam placements
    item.assets.forEach(asset => {
      beragamPlacementLocations.value = { ...beragamPlacementLocations.value };
      beragamPlacementLocations.value[asset] = assetPlacements.value[asset] || '';
    });

    // Check if all assets have the same location and it is not empty
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

const totalPages = computed(() => {
  if (itemsPerPage.value === 'Semua baris') return 1;
  const limit = Number(itemsPerPage.value);
  return Math.ceil(filteredAssets.value.length / limit);
});

const handleReturnAction = () => {
  if (!requestState.value) return;

  if (request.value.type === 'peminjaman') {
    openReturnModal();
  } else {
    requestState.value.status = 'Selesai';
    alertToastMessage.value = 'Permintaan barang habis pakai selesai!';
    showToast.value = true;
    setTimeout(() => {
      showToast.value = false;
    }, 4000);
  }
};

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

// ─────────────────────────────────────────────
// Timeline / Steps Logic
// ─────────────────────────────────────────────
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
  const baseDate = formatDate(r.created_at);

  // Step 1: Permintaan dibuat
  steps.push({
    title: 'Permintaan dibuat',
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
        title = 'Di-approve';
        description = description || 'Permintaan disetujui oleh Manager.';
      } else if (log.status_to === 'partial') {
        title = 'Disetujui sebagian (Partial)';
        description = description || 'Permintaan disetujui sebagian oleh Admin.';
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
        description = description || 'Permintaan dikonfirmasi oleh Admin.';
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
        title = 'Ditolak';
        status = 'rejected';
        description = description || 'Permintaan ditolak.';
      } else if (log.status_to === 'cancel') {
        title = 'Dibatalkan';
        status = 'rejected';
        description = description || 'Permintaan dibatalkan.';
      } else if (log.status_to === 'pending') {
        if (log.status_from === 'confirm') {
          title = 'Serah Terima Sebagian Diterima';
          status = 'done'; // Mark as done since it is a completed receipt step
        } else {
          title = 'Pending';
          status = 'pending';
        }
        description = description || (log.status_from === 'confirm' ? 'Barang telah diterima oleh pengguna.' : 'Permintaan ditunda (pending) oleh Admin.');
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
        description: 'Menunggu approval dari Manager.'
      });
    } else if (r.raw_status === 'approve') {
      steps.push({
        title: 'Menunggu konfirmasi Admin',
        status: 'active',
        description: 'Permintaan disetujui Manager. Menunggu alokasi aset dan konfirmasi Admin.'
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
</script>

<template>
  <Head :title="'Detail ' + request.number" />

  <AppLayout title="Detail Permintaan">
    <!-- ── Breadcrumb ── -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3 text-xs md:text-sm">
        <BreadcrumbItem>
          <Link :href="route('smart.history')" class="text-muted-foreground hover:text-foreground transition-colors">
            Riwayat Permintaan
          </Link>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-foreground font-medium">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- ── Header Halaman dengan Tombol Kembali ── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight flex items-center gap-2">
          Detail Permintaan <span class="text-primary">{{ request.number }}</span>
        </h1>
        <p class="text-sm text-muted-foreground mt-1">
          Permintaan dibuat pada {{ formatDate(request.created_at) }}
        </p>
      </div>

      <Link :href="route('smart.history')">
        <Button variant="outline" class="rounded-full flex items-center gap-1.5 h-10 px-5 shadow-sm text-sm">
          <ArrowLeft class="w-4 h-4" />
          Kembali ke Riwayat
        </Button>
      </Link>
    </div>

    <!-- ── Alert Banner: Tindakan Diperlukan atau Pengingat Serah Terima ── -->
    <div 
      v-if="request.raw_status === 'confirm' || request.raw_status === 'partial'" 
      class="mb-6 p-4 border border-[#6366F1] bg-[#6366F1]/5 rounded-[12px] flex flex-col sm:flex-row justify-between items-center gap-4 animate-in fade-in slide-in-from-top-1 duration-300"
    >
      <span v-if="!request.handover_time && !isAutoScheduled" class="text-sm font-semibold text-[#6366F1]">
        Tindakan diperlukan: serah terima belum diatur!
      </span>
      <span v-else class="text-sm font-semibold text-[#6366F1]">
        Pengingat bahwa Anda harus mengambil sendiri pada {{ formattedTimeForBanner }}
      </span>

      <Button 
        v-if="!request.handover_time && !isAutoScheduled"
        @click="openHandoverModal" 
        class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-5 h-9 rounded-lg text-xs md:text-sm shadow-sm transition-colors"
      >
        Atur Serah Terima
      </Button>
      <Button 
        v-else
        @click="handleConfirmReceived" 
        class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-6 h-9 rounded-full text-xs md:text-sm shadow-sm transition-colors"
      >
        Aset Telah Diterima
      </Button>
    </div>



    <!-- ── Grid Layout Dua Kolom ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Kolom Kiri (Detail Permintaan & Daftar Barang) -->
      <div class="lg:col-span-2 space-y-6">
        
        <!-- Card Detail Info -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-sm font-medium text-muted-foreground mb-3">Detail:</h3>
          <div class="space-y-2">
            <h2 class="text-lg md:text-xl font-extrabold text-foreground">
              {{ request.number }}
            </h2>
            
            <div class="space-y-1.5 text-sm text-foreground">
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">
                  {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
                </span>
              </p>

              <p v-if="request.type === 'peminjaman' && request.durationStart">
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">
                  {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
                </span>
              </p>
            </div>
          </div>
        </div>

        <!-- Card Daftar Barang -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-4">Daftar barang:</h3>
          
          <div class="space-y-4">
            <AssetItemCard 
              v-for="item in request.items" 
              :key="item.id"
              :brand="item.spec ? `${item.brand} ${item.spec}` : item.brand"
              :category="item.category"
              :subcategory="item.subcategory"
              :quantity="item.quantity"
              :assets="item.assets || []"
              :imageUrl="item.imageUrl"
              :placements="assetPlacements"
              :stock="item.stock"
              :status="item.status"
              :is-consumable="item.is_consumable"
            >
              <template #footer>
                <div
                  v-if="['wait', 'approve', 'confirm', 'partial'].includes(request.raw_status) && !item.is_consumable && item.assets && item.assets.length > 0"
                  class="flex gap-2.5"
                >
                  <button 
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

      </div>

      <!-- Kolom Kanan (Timeline / Tahapan Permintaan) -->
      <div class="space-y-6">
        
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm relative overflow-hidden">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-6">Tahapan:</h3>

          <!-- Vertical Timestep Stepper -->
          <div class="relative pl-8 space-y-8 before:absolute before:left-[15px] before:top-[10px] before:bottom-[10px] before:w-[2px] before:bg-border">
            
            <div 
              v-for="(step, idx) in timelineSteps" 
              :key="idx" 
              class="relative"
            >
              <!-- Icon/Indicator -->
              <div class="absolute -left-[32px] top-0 w-8 h-8 rounded-full bg-card flex items-center justify-center z-10">
                <!-- Status Done (Green Check Circle) -->
                <div 
                  v-if="step.status === 'done'" 
                  class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card"
                >
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
                
                <!-- Status Action Required (Exclamation Circle) -->
                <div 
                  v-else-if="step.status === 'action-required'" 
                  class="w-7 h-7 rounded-full border-2 border-[#6366F1] flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-[#6366F1]/20 opacity-40 animate-ping"></span>
                  <span class="text-sm font-extrabold text-[#6366F1]">!</span>
                </div>

                <!-- Status Active / Current (Pulsing Blue Clock) -->
                <div 
                  v-else-if="step.status === 'active'" 
                  class="w-7 h-7 rounded-full border-2 border-blue-500 flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-30 animate-ping"></span>
                  <Clock class="w-4 h-4 text-blue-500" />
                </div>

                <!-- Status Rejected (Red X Circle) -->
                <div 
                  v-else-if="step.status === 'rejected'" 
                  class="w-7 h-7 rounded-full border-2 border-red-500 flex items-center justify-center bg-card"
                >
                  <X class="w-4 h-4 text-red-500 stroke-[3.5]" />
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
                      'text-green-600': step.status === 'done',
                      'text-[#6366F1]': step.status === 'action-required',
                      'text-blue-600': step.status === 'active',
                      'text-red-600': step.status === 'rejected',
                      'text-muted-foreground': step.status === 'pending'
                    }"
                  >
                    {{ step.title }}
                  </h4>
                  <p v-if="step.user" class="text-xs font-semibold text-green-600 mt-0.5">
                    oleh {{ step.user }}
                  </p>
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                </div>
                
                <p v-if="step.description && step.description !== 'scheduled-details' && step.description !== 'show-return-action'" class="text-xs text-muted-foreground leading-relaxed pt-0.5">
                  {{ step.description }}
                </p>

                <!-- Scheduled Details Custom Rendering -->
                <div v-if="step.description === 'scheduled-details'" class="text-xs space-y-1 text-muted-foreground pt-0.5">
                  <p><span class="font-semibold text-foreground/80">Metode:</span> {{ effectiveHandoverMethod }}</p>
                  <p><span class="font-semibold text-foreground/80">Tempat:</span> {{ effectiveHandoverLocation }}</p>
                  <p><span class="font-semibold text-foreground/80">Waktu:</span> {{ effectiveHandoverTime }}</p>
                </div>

                <!-- Tombol Atur Serah Terima di Step Timeline -->
                <div v-if="step.status === 'action-required' && step.description !== 'scheduled-details' && step.description !== 'show-return-action'" class="pt-2">
                  <Button 
                    @click="openHandoverModal" 
                    class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-4 h-8 rounded-lg text-xs shadow-sm transition-colors"
                  >
                    Atur Serah Terima
                  </Button>
                </div>

                <!-- Tombol Aset Telah Diterima di Step Timeline -->
                <div v-if="step.status === 'action-required' && step.description === 'scheduled-details'" class="pt-2">
                  <Button 
                    @click="handleConfirmReceived" 
                    class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-5 h-8 rounded-full text-xs shadow-sm transition-colors"
                  >
                    Aset Telah Diterima
                  </Button>
                </div>

                <!-- Tombol Atur Pengembalian / Selesai di Step Timeline -->
                <div v-if="step.status === 'action-required' && step.description === 'show-return-action'" class="pt-2">
                  <Button 
                    @click="handleReturnAction" 
                    class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-5 h-8 rounded-full text-xs shadow-sm transition-colors"
                  >
                    {{ request.type === 'peminjaman' ? 'Atur Pengembalian' : 'Selesai' }}
                  </Button>
                </div>
              </div>

            </div>

          </div>

        </div>

      </div>

    </div>

    <!-- ============================================================
         Modal Atur Serah Terima (Teleport & Backdrop)
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
          v-if="isHandoverModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-card text-foreground rounded-[20px] shadow-2xl w-full max-w-[850px] flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-card shrink-0">
              <h3 class="text-lg font-bold text-foreground">Serah Terima</h3>
              <button @click="closeHandoverModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Metadata Area (Indented / Styled) -->
            <div class="px-6 pb-4 space-y-1 text-sm text-foreground shrink-0">
              <h4 class="font-extrabold text-base">{{ request.number }}</h4>
              <p class="text-muted-foreground text-xs md:text-sm">
                Pemanfaatan: {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
              </p>
              <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-muted-foreground text-xs md:text-sm">
                Durasi: {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
              </p>
            </div>

            <div class="mx-6 border-b border-border"></div>

            <!-- Form Content -->
            <div class="p-6 space-y-6">
              
              <!-- Row: Metode Penyerahan -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <label class="w-full sm:w-44 text-sm font-semibold text-foreground shrink-0">
                  Metode penyerahan<span class="text-red-500">*</span>:
                </label>
                <div class="flex-grow max-w-xs relative">
                  <!-- Custom Select -->
                  <Select v-model="handoverMethod" @update:modelValue="onInputChange">
                    <SelectTrigger class="w-full rounded-full border-input bg-background h-10 px-4">
                      <SelectValue placeholder="Pilih" />
                    </SelectTrigger>
                    <SelectContent 
                      class="bg-card border border-border rounded-xl shadow-lg z-[10000]"
                      style="width: var(--reka-select-trigger-width);"
                    >
                      <SelectItem value="Ambil sendiri">Ambil sendiri</SelectItem>
                      <SelectItem value="Diantar ke ruangan">Diantar ke ruangan</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- Row: Jadwal Penyerahan -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <label class="w-full sm:w-44 text-sm font-semibold text-foreground shrink-0">
                  Jadwal penyerahan<span class="text-red-500">*</span>:
                </label>
                <div class="flex flex-wrap items-center gap-3">
                  <!-- Date Input -->
                  <div class="relative">
                    <input 
                      type="date" 
                      v-model="handoverDate" 
                      @change="onInputChange"
                      class="h-10 px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-muted-foreground w-48"
                      placeholder="Pilih tanggal"
                    />
                  </div>

                  <!-- Time Input -->
                  <div class="relative">
                    <input 
                      type="time" 
                      v-model="handoverTimeOnly" 
                      @change="onInputChange"
                      class="h-10 px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-muted-foreground w-36"
                      placeholder="Pilih jam"
                    />
                  </div>
                </div>
              </div>

              <!-- Validation Error Message -->
              <div v-if="errorMessage" class="pl-0 sm:pl-48">
                <p class="text-xs font-bold text-red-500 bg-red-500/5 border border-red-500/20 px-3 py-2 rounded-lg animate-in fade-in duration-200">
                  {{ errorMessage }}
                </p>
              </div>

            </div>

            <!-- Footer Action Section -->
            <div class="flex items-center justify-between p-6 border-t border-border bg-card shrink-0">
              <span class="text-xs italic text-red-500">*Wajib diisi</span>
              
              <div class="flex items-center gap-3">
                <Button 
                  variant="outline"
                  @click="closeHandoverModal"
                  class="rounded-full h-10 px-6 font-bold text-sm border-input hover:bg-muted transition-colors"
                >
                  Batal
                </Button>
                <Button 
                  @click="handleSaveHandover"
                  class="rounded-full h-10 px-6 font-bold text-sm bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm transition-colors"
                >
                  Konfirmasi Serah Terima
                </Button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============================================================
         Modal Konfirmasi Serah Terima Selesai (Teleport & Backdrop)
         ============================================================ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isConfirmReceivedModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
          <div 
            class="bg-card w-full max-w-lg rounded-[18px] border border-border shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
              <h3 class="text-sm md:text-base font-extrabold text-foreground">
                Konfirmasi Serah Terima Selesai
              </h3>
              <button 
                @click="closeConfirmReceivedModal"
                class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-full hover:bg-muted"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Body Content -->
            <div class="p-6">
              <p class="text-xs md:text-sm font-semibold text-[#6366F1] leading-relaxed">
                Saya telah menerima semua barang dengan sesuai dan dalam kondisi yang baik.
              </p>
            </div>

            <!-- Footer Actions -->
            <div class="flex justify-end items-center gap-3 px-6 py-4 bg-muted/30 border-t border-border">
              <Button 
                variant="outline" 
                @click="closeConfirmReceivedModal"
                class="rounded-full border-input hover:bg-muted text-foreground font-bold px-5 h-9 text-xs md:text-sm transition-colors"
              >
                Tidak
              </Button>
              <Button 
                @click="confirmReceivedAction"
                class="bg-[#6366F1] hover:bg-[#5558EB] text-white font-bold px-6 h-9 rounded-full text-xs md:text-sm shadow-sm transition-colors"
              >
                Iya
              </Button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>



    <!-- ============================================================
         Modal Atur Pengembalian (Teleport & Backdrop)
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
          v-if="isReturnModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-card text-foreground rounded-[20px] shadow-2xl w-full max-w-[850px] flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-card shrink-0">
              <h3 class="text-lg font-bold text-foreground">Pengembalian</h3>
              <button @click="closeReturnModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Metadata Area (Indented / Styled) -->
            <div class="px-6 pb-4 space-y-1 text-sm text-foreground shrink-0">
              <h4 class="font-extrabold text-base">{{ request.number }}</h4>
              <p class="text-muted-foreground text-xs md:text-sm">
                Pemanfaatan: {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
              </p>
              <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-muted-foreground text-xs md:text-sm">
                Durasi: {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
              </p>
            </div>

            <div class="mx-6 border-b border-border"></div>

            <!-- Form Content -->
            <div class="p-6 space-y-6">
              
              <!-- Row: Metode Pengembalian -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <label class="w-full sm:w-44 text-sm font-semibold text-foreground shrink-0">
                  Metode pengembalian<span class="text-red-500">*</span>:
                </label>
                <div class="flex-grow max-w-xs relative">
                  <!-- Custom Select -->
                  <Select v-model="returnMethod" @update:modelValue="onReturnInputChange">
                    <SelectTrigger class="w-full rounded-full border-input bg-background h-10 px-4">
                      <SelectValue placeholder="Pilih" />
                    </SelectTrigger>
                    <SelectContent 
                      class="bg-card border border-border rounded-xl shadow-lg z-[10000]"
                      style="width: var(--reka-select-trigger-width);"
                    >
                      <SelectItem value="Kembalikan sendiri">Kembalikan sendiri</SelectItem>
                      <SelectItem value="Diantar ke GA / IT Support">Diantar ke GA / IT Support</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- Row: Jadwal Pengembalian -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <label class="w-full sm:w-44 text-sm font-semibold text-foreground shrink-0">
                  Jadwal pengembalian<span class="text-red-500">*</span>:
                </label>
                <div class="flex flex-wrap items-center gap-3">
                  <!-- Date Input -->
                  <div class="relative">
                    <input 
                      type="date" 
                      v-model="returnDate" 
                      @change="onReturnInputChange"
                      class="h-10 px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-muted-foreground w-48"
                      placeholder="Pilih tanggal"
                    />
                  </div>

                  <!-- Time Input -->
                  <div class="relative">
                    <input 
                      type="time" 
                      v-model="returnTimeOnly" 
                      @change="onReturnInputChange"
                      class="h-10 px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-muted-foreground w-36"
                      placeholder="Pilih jam"
                    />
                  </div>
                </div>
              </div>

              <!-- Validation Error Message -->
              <div v-if="returnErrorMessage" class="pl-0 sm:pl-48">
                <p class="text-xs font-bold text-red-500 bg-red-500/5 border border-red-500/20 px-3 py-2 rounded-lg animate-in fade-in duration-200">
                  {{ returnErrorMessage }}
                </p>
              </div>

            </div>

            <!-- Footer Action Section -->
            <div class="flex items-center justify-between p-6 border-t border-border bg-card shrink-0">
              <span class="text-xs italic text-red-500">*Wajib diisi</span>
              
              <div class="flex items-center gap-3">
                <Button 
                  variant="outline"
                  @click="closeReturnModal"
                  class="rounded-full h-10 px-6 font-bold text-sm border-input hover:bg-muted transition-colors"
                >
                  Batal
                </Button>
                <Button 
                  @click="handleSaveReturn"
                  class="rounded-full h-10 px-6 font-bold text-sm bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm transition-colors"
                >
                  Konfirmasi Pengembalian
                </Button>
              </div>
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

    <!-- ============================================================
         Toast Notification (Sukses Menyimpan)
         ============================================================ -->
    <Transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="showToast" 
        class="fixed top-5 right-5 z-[10000] max-w-sm w-full bg-card border border-border shadow-lg rounded-xl pointer-events-auto overflow-hidden flex items-center p-4 gap-3 border-l-4 border-l-green-500"
      >
        <div class="flex-shrink-0">
          <CheckCircle2 class="h-6 w-6 text-green-500" />
        </div>
        <div class="flex-1 text-sm font-semibold text-foreground">
          {{ alertToastMessage }}
        </div>
        <button @click="showToast = false" class="text-muted-foreground hover:text-foreground">
          <X class="w-4 h-4" />
        </button>
      </div>
    </Transition>
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
