<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
  ChevronUp
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
  assets?: string[];
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
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan';
  created_at: string; // format YYYY-MM-DD
  items: RequestItem[];
}

const props = defineProps<{
  id: string | number;
  user?: any;
}>();

// ─────────────────────────────────────────────
// Dummy Database for detail requests
// ─────────────────────────────────────────────
const requestsDb: RequestHistory[] = [
  {
    id: 1,
    number: '#REQ-2026-0001',
    type: 'permintaan',
    pemanfaatan: 'corporate',
    pemanfaatanDetail: 'Finance',
    status: 'Menunggu approval',
    created_at: '2026-05-20',
    items: [
      { id: 101, subcategory: 'ATK', brand: 'Sinar Dunia', spec: 'Kertas A4 80gr', quantity: 5, stockQuantity: 15, category: 'Alat Tulis', assets: [] },
      { id: 102, subcategory: 'Peralatan Kantor', brand: 'Joyko', spec: 'Stapler Besar', quantity: 2, stockQuantity: 4, category: 'Alat Tulis', assets: [] },
      { id: 103, subcategory: 'ATK', brand: 'Pilot', spec: 'Pulpen Hitam Ballpoint', quantity: 12, stockQuantity: 45, category: 'Alat Tulis', assets: [] },
      { id: 104, subcategory: 'Peralatan Kantor', brand: 'Kenko', spec: 'Gunting Kantor', quantity: 3, stockQuantity: 8, category: 'Alat Tulis', assets: [] }
    ]
  },
  {
    id: 2,
    number: '#BOR-2026-0002',
    type: 'peminjaman',
    pemanfaatan: 'project',
    pemanfaatanDetail: 'PRJ-001 – Website Revamp',
    durationStart: '22-05-2026 09:00',
    durationEnd: '29-05-2026 17:00',
    durationDays: 7,
    durationHours: 8,
    status: 'Menunggu approval',
    created_at: '2026-05-19',
    items: [
      { id: 201, subcategory: 'Laptop', brand: 'Asus ROG', spec: 'Zephyrus G14 AMD R7', quantity: 1, stockQuantity: 2, category: 'Elektronik', assets: [] },
      { id: 202, subcategory: 'Mouse', brand: 'Logitech', spec: 'MX Master 3S Wireless', quantity: 1, stockQuantity: 5, category: 'Elektronik', assets: [] }
    ]
  },
  {
    id: 3,
    number: '#BOR-2026-0003',
    type: 'peminjaman',
    pemanfaatan: 'project',
    pemanfaatanDetail: 'PRJ-002 – Mobile App Development',
    durationStart: '15-05-2026 08:00',
    durationEnd: '15-06-2026 17:00',
    durationDays: 31,
    durationHours: 9,
    status: 'Disetujui',
    created_at: '2026-05-14',
    items: [
      { id: 301, subcategory: 'Monitor', brand: 'Dell', spec: 'UltraSharp 27" U2723QE', quantity: 2, stockQuantity: 3, category: 'Elektronik', assets: ['MON-DELL-2026-901', 'MON-DELL-2026-902'] }
    ]
  },
  {
    id: 4,
    number: '#REQ-2026-0004',
    type: 'permintaan',
    pemanfaatan: 'corporate',
    pemanfaatanDetail: 'IT Support',
    status: 'Disetujui',
    created_at: '2026-05-10',
    items: [
      { id: 401, subcategory: 'Kabel', brand: 'Belden', spec: 'Kabel UTP Cat6 10m', quantity: 4, stockQuantity: 12, category: 'Elektronik', assets: ['BEL-CAT6-2026-001', 'BEL-CAT6-2026-002', 'BEL-CAT6-2026-003', 'BEL-CAT6-2026-004'] },
      { id: 402, subcategory: 'Konektor', brand: 'Amp', spec: 'RJ45 Connector isi 50', quantity: 1, stockQuantity: 3, category: 'Elektronik', assets: ['AMP-RJ45-2026-092'] }
    ]
  },
  {
    id: 5,
    number: '#BOR-2026-0005',
    type: 'peminjaman',
    pemanfaatan: 'corporate',
    pemanfaatanDetail: 'HR & GA',
    durationStart: '01-05-2026 10:00',
    durationEnd: '08-05-2026 10:00',
    durationDays: 7,
    durationHours: 0,
    status: 'Selesai',
    created_at: '2026-04-30',
    items: [
      { id: 501, subcategory: 'Proyektor', brand: 'Epson', spec: 'EB-X500 XGA 3600 Lumens', quantity: 1, stockQuantity: 2, category: 'Elektronik', assets: ['PRJ-EPSON-2026-441'] }
    ]
  },
  {
    id: 6,
    number: '#REQ-2026-0006',
    type: 'permintaan',
    pemanfaatan: 'project',
    pemanfaatanDetail: 'PRJ-003 – ERP Integration',
    status: 'Dibatalkan',
    created_at: '2026-04-25',
    items: [
      { id: 601, subcategory: 'Peralatan Listrik', brand: 'Schneider', spec: 'Stopkontak 5 Lubang', quantity: 5, stockQuantity: 10, category: 'Elektronik', assets: [] }
    ]
  },
  {
    id: 7,
    number: '#BOR-2026-0007',
    type: 'peminjaman',
    pemanfaatan: 'project',
    pemanfaatanDetail: 'PRJ-004 – AI Research Model',
    durationStart: '20-05-2026 09:00',
    durationEnd: '27-05-2026 17:00',
    durationDays: 7,
    durationHours: 8,
    status: 'Dipinjam',
    created_at: '2026-05-18',
    items: [
      { id: 701, subcategory: 'GPU Workstation', brand: 'NVIDIA', spec: 'RTX 4090 24GB', quantity: 1, stockQuantity: 2, category: 'Elektronik', assets: ['GPU-NVIDIA-2026-001'] }
    ]
  },
  {
    id: 8,
    number: '#BOR-2026-0008',
    type: 'peminjaman',
    pemanfaatan: 'corporate',
    pemanfaatanDetail: 'Research & Development',
    durationStart: '10-05-2026 08:00',
    durationEnd: '', // No due date
    durationDays: 0,
    durationHours: 0,
    status: 'Dipinjam',
    created_at: '2026-05-09',
    items: [
      { id: 801, subcategory: 'Papan Tulis', brand: 'Sakura', spec: 'Whiteboard Portable 120x90', quantity: 1, stockQuantity: 3, category: 'Alat Tulis', assets: ['WBD-SAKURA-2026-101'] }
    ]
  }
];

// Reaktif Request State
const requestState = ref<RequestHistory | null>(null);

// Inisialisasi data
const reqId = Number(props.id);
const found = requestsDb.find(r => r.id === reqId);
requestState.value = found ? JSON.parse(JSON.stringify(found)) : JSON.parse(JSON.stringify(requestsDb[0]));

const request = computed((): RequestHistory => {
  return requestState.value || requestsDb[0];
});

// Formatting Date Helper
const formatDate = (dateStr: string) => {
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}-${parts[1]}-${parts[0]}`; // DD-MM-YYYY
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

// Parser durationDate e.g. "22-05-2026 09:00" -> Date object
const parseDurationDate = (dateStr: string) => {
  const parts = dateStr.trim().split(' ');
  if (parts.length < 2) return null;
  const dateParts = parts[0].split('-');
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
  if (r.status !== 'Disetujui') return false;

  const startDate = parseDurationDate(r.durationStart);
  if (!startDate) return false;

  // 1 day before starting date
  const oneDayBefore = new Date(startDate.getTime() - 24 * 60 * 60 * 1000);
  const now = new Date(); // May 20, 2026 in system mockup
  return now >= oneDayBefore;
});

const effectiveHandoverMethod = computed(() => {
  if (isAutoScheduled.value) return 'Ambil sendiri';
  return handoverMethod.value && handoverMethod.value !== 'Pilih' ? handoverMethod.value : 'Ambil sendiri';
});

const effectiveHandoverTime = computed(() => {
  if (isAutoScheduled.value) return request.value.durationStart || '';
  return handoverTime.value || request.value.durationStart || '';
});

const effectiveHandoverLocation = computed(() => {
  if (isAutoScheduled.value) return 'Ruang GA / IT Support';
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

  // Simpan data & ubah status
  if (requestState.value) {
    requestState.value.status = 'Serah Terima';
    
    // Format tanggal ke DD-MM-YYYY untuk display di timeline
    const dateParts = handoverDate.value.split('-');
    const formattedDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : handoverDate.value;
    handoverTime.value = `${formattedDate} ${handoverTimeOnly.value}`;

    alertToastMessage.value = 'Serah terima berhasil diatur!';
    showToast.value = true;
    setTimeout(() => {
      showToast.value = false;
    }, 4000);
  }
  closeHandoverModal();
};

const isConfirmReceivedModalOpen = ref(false);

const handleConfirmReceived = () => {
  isConfirmReceivedModalOpen.value = true;
};

const closeConfirmReceivedModal = () => {
  isConfirmReceivedModalOpen.value = false;
};

const confirmReceivedAction = () => {
  if (requestState.value) {
    requestState.value.status = 'Dipinjam';
    alertToastMessage.value = 'Aset berhasil dikonfirmasi telah diterima!';
    showToast.value = true;
    setTimeout(() => {
      showToast.value = false;
    }, 4000);
  }
  closeConfirmReceivedModal();
};

// Collapsible Aset state
const expandedAssets = ref<Record<number, boolean>>({});
const toggleAssets = (itemId: number) => {
  expandedAssets.value[itemId] = !expandedAssets.value[itemId];
};

// Asset placement state
const assetPlacements = ref<Record<string, string>>({
  'GPU-NVIDIA-2026-001': 'Mega Mendung',
  'WBD-SAKURA-2026-101': 'Tiga Negeri',
  'MON-DELL-2026-901': 'Mega Mendung',
  'MON-DELL-2026-902': 'Tiga Negeri',
});

// Placement Modal State
const isPlacementModalOpen = ref(false);
const currentItemForPlacement = ref<RequestItem | null>(null);
const placementForm = ref<Record<string, string>>({});

const openPlacementModal = (item: RequestItem) => {
  currentItemForPlacement.value = item;
  placementForm.value = {};
  if (item.assets) {
    item.assets.forEach(asset => {
      placementForm.value[asset] = assetPlacements.value[asset] || '';
    });
  }
  isPlacementModalOpen.value = true;
};

const closePlacementModal = () => {
  isPlacementModalOpen.value = false;
  currentItemForPlacement.value = null;
};

const handleSavePlacements = () => {
  if (currentItemForPlacement.value && currentItemForPlacement.value.assets) {
    currentItemForPlacement.value.assets.forEach(asset => {
      assetPlacements.value[asset] = placementForm.value[asset] || '';
    });
  }
  closePlacementModal();
  alertToastMessage.value = 'Penempatan aset berhasil dicatat!';
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 4000);
};

const handleReturnAction = () => {
  if (requestState.value) {
    requestState.value.status = 'Selesai';
    alertToastMessage.value = request.value.type === 'peminjaman' 
      ? 'Pengembalian aset berhasil diatur!' 
      : 'Permintaan barang habis pakai selesai!';
    showToast.value = true;
    setTimeout(() => {
      showToast.value = false;
    }, 4000);
  }
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
    time: `${baseDate} 08:30`,
    status: 'done'
  });

  if (r.status === 'Menunggu approval') {
    steps.push({
      title: 'Dalam proses approval',
      status: 'active',
      description: 'Menunggu peninjauan dari Admin/Departemen terkait.'
    });
  } else if (r.status === 'Disetujui' && !isAutoScheduled.value) {
    steps.push({
      title: 'Di-approve',
      user: 'Jean Doe',
      time: `${baseDate} 14:15`,
      status: 'done'
    });
    steps.push({
      title: 'Dikonfirmasi',
      user: 'Radifa',
      time: `${baseDate} 15:30`,
      status: 'done'
    });
    steps.push({
      title: 'Serah Terima',
      status: 'action-required',
      description: 'Serah Terima perlu diatur!'
    });
  } else if (r.status === 'Serah Terima' || (r.status === 'Disetujui' && isAutoScheduled.value)) {
    steps.push({
      title: 'Di-approve',
      user: 'Jean Doe',
      time: `${baseDate} 14:15`,
      status: 'done'
    });
    steps.push({
      title: 'Dikonfirmasi',
      user: 'Radifa',
      time: `${baseDate} 15:30`,
      status: 'done'
    });
    steps.push({
      title: 'Serah Terima',
      status: 'action-required',
      description: 'scheduled-details'
    });
    steps.push({
      title: r.type === 'peminjaman' ? 'Sedang Dipinjam' : 'Selesai Digunakan',
      status: 'pending',
      description: r.type === 'peminjaman' ? 'Aset sedang Anda gunakan. Harap dikembalikan sebelum jatuh tempo.' : 'Barang habis pakai sudah diserahkan.'
    });
  } else if (r.status === 'Dipinjam') {
    steps.push({
      title: 'Di-approve',
      user: 'Jean Doe',
      time: `${baseDate} 14:15`,
      status: 'done'
    });
    steps.push({
      title: 'Dikonfirmasi',
      user: 'Radifa',
      time: `${baseDate} 15:30`,
      status: 'done'
    });
    steps.push({
      title: 'Serah Terima Selesai',
      time: effectiveHandoverTime.value,
      status: 'done',
      description: `Serah terima diselesaikan secara ${effectiveHandoverMethod.value.toLowerCase()} di ${effectiveHandoverLocation.value}.`
    });
    steps.push({
      title: r.type === 'peminjaman' ? 'Aset sedang Anda pinjam' : 'Aset sedang Anda gunakan',
      status: 'action-required',
      description: 'show-return-action'
    });
  } else if (r.status === 'Selesai') {
    steps.push({
      title: 'Di-approve',
      user: 'Jean Doe',
      time: `${baseDate} 14:15`,
      status: 'done'
    });
    steps.push({
      title: 'Dikonfirmasi',
      user: 'Radifa',
      time: `${baseDate} 15:30`,
      status: 'done'
    });
    steps.push({
      title: 'Serah Terima Selesai',
      time: effectiveHandoverTime.value || `${baseDate} 16:00`,
      status: 'done'
    });
    steps.push({
      title: 'Barang Dikembalikan & Selesai',
      time: `${baseDate} 17:00`,
      status: 'done',
      description: 'Semua proses peminjaman telah diselesaikan dengan sukses.'
    });
  } else if (r.status === 'Dibatalkan') {
    steps.push({
      title: 'Dibatalkan oleh Pengguna',
      time: `${baseDate} 09:45`,
      status: 'rejected',
      description: 'Anda telah membatalkan permintaan ini.'
    });
  } else if (r.status === 'Ditolak') {
    steps.push({
      title: 'Ditolak oleh Admin',
      time: `${baseDate} 11:20`,
      status: 'rejected',
      description: 'Permintaan ditolak karena stok di gudang tidak mencukupi atau spesifikasi tidak sesuai.'
    });
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
      v-if="request.status === 'Disetujui' || request.status === 'Serah Terima'" 
      class="mb-6 p-4 border border-[#6366F1] bg-[#6366F1]/5 rounded-[12px] flex flex-col sm:flex-row justify-between items-center gap-4 animate-in fade-in slide-in-from-top-1 duration-300"
    >
      <span v-if="request.status === 'Disetujui' && !isAutoScheduled" class="text-sm font-semibold text-[#6366F1]">
        Tindakan diperlukan: serah terima belum diatur!
      </span>
      <span v-else class="text-sm font-semibold text-[#6366F1]">
        Pengingat bahwa Anda harus mengambil sendiri pada {{ formattedTimeForBanner }}
      </span>

      <Button 
        v-if="request.status === 'Disetujui' && !isAutoScheduled"
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

    <!-- ── Warning Banner for Dipinjam Status (Tolong catat penempatan Aset) ── -->
    <div 
      v-if="request.status === 'Dipinjam' && request.type === 'peminjaman'" 
      class="mb-6 p-4 border border-[#6366F1] bg-[#6366F1]/5 rounded-[12px] flex justify-start items-center animate-in fade-in slide-in-from-top-1 duration-300"
    >
      <span class="text-sm font-semibold text-[#6366F1]">
        Tolong catat penempatan Aset
      </span>
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
            <div 
              v-for="item in request.items" 
              :key="item.id"
              class="flex gap-4 p-4 border border-border/70 hover:border-primary/20 hover:bg-muted/5 transition-all rounded-[14px] items-start"
            >
              <!-- Thumbnail Barang -->
              <div class="w-16 h-16 rounded-[12px] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center mt-0.5">
                <img 
                  v-if="item.imageUrl" 
                  :src="item.imageUrl" 
                  class="w-full h-full object-cover" 
                />
                <div v-else class="text-sm font-black text-muted-foreground/50 select-none">
                  {{ item.subcategory.substring(0, 3).toUpperCase() }}
                </div>
              </div>

              <!-- Detail Detail Deskripsi Barang -->
              <div class="min-w-0 flex-grow space-y-1">
                <h4 class="text-sm md:text-base font-bold text-foreground truncate">
                  {{ item.brand }} {{ item.spec }}
                </h4>
                
                <p class="text-xs text-muted-foreground">
                  Kategori: {{ item.category }} ({{ item.subcategory }})
                </p>
                
                <p class="text-xs text-foreground font-semibold">
                  Jumlah dipinjam: {{ item.quantity }} satuan
                </p>

                <!-- Asset list toggle button (hanya jika ada aset teralokasi) -->
                <div v-if="item.assets && item.assets.length > 0" class="pt-1.5">
                  <button 
                    @click="toggleAssets(item.id)"
                    class="text-xs font-bold text-[#6366F1] hover:text-[#5558EB] flex items-center gap-1 transition-colors focus:outline-none"
                  >
                    <span>{{ expandedAssets[item.id] ? 'Sembunyikan Alokasi Aset' : 'Lihat Alokasi Aset' }}</span>
                    <ChevronUp v-if="expandedAssets[item.id]" class="w-3.5 h-3.5" />
                    <ChevronDown v-else class="w-3.5 h-3.5" />
                  </button>

                  <!-- Expanded Asset List -->
                  <div 
                    v-if="expandedAssets[item.id]" 
                    class="mt-2 pl-3 py-1 space-y-1 animate-in fade-in slide-in-from-top-1 duration-200"
                  >
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Aset:</p>
                    <ul class="space-y-1">
                      <li 
                        v-for="asset in item.assets" 
                        :key="asset" 
                        class="text-xs text-foreground font-semibold flex items-center gap-1.5"
                      >
                        <span class="w-1 h-1 rounded-full bg-foreground shrink-0"></span>
                        <span>{{ asset }} <span class="text-muted-foreground font-normal">({{ assetPlacements[asset] || 'Tempat Penempatan' }})</span></span>
                      </li>
                    </ul>
                  </div>

                  <!-- Catat Penempatan Aset Button -->
                  <div 
                    v-if="request.status === 'Dipinjam' && request.type === 'peminjaman' && item.assets && item.assets.length > 0" 
                    class="pt-3 flex justify-end"
                  >
                    <Button 
                      @click="openPlacementModal(item)"
                      class="bg-[#00BCD4] hover:bg-[#00ACC1] text-white font-bold px-4 h-9 rounded-lg text-xs shadow-sm transition-colors"
                    >
                      Catat Penempatan Aset
                    </Button>
                  </div>
                </div>
              </div>
            </div>
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
         Modal Catat Penempatan Aset (Teleport & Backdrop)
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
          v-if="isPlacementModalOpen && currentItemForPlacement" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-card text-foreground rounded-[20px] shadow-2xl w-full max-w-[550px] flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-card shrink-0">
              <h3 class="text-lg font-bold text-foreground">Catat Penempatan Aset</h3>
              <button @click="closePlacementModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Metadata Area -->
            <div class="px-6 pb-4 space-y-1 text-sm text-foreground shrink-0">
              <h4 class="font-extrabold text-base">{{ currentItemForPlacement.brand }} {{ currentItemForPlacement.spec }}</h4>
              <p class="text-muted-foreground text-xs md:text-sm">
                Kategori: {{ currentItemForPlacement.category }} ({{ currentItemForPlacement.subcategory }})
              </p>
            </div>

            <div class="mx-6 border-b border-border"></div>

            <!-- Form Content -->
            <div class="p-6 space-y-4 max-h-[350px] overflow-y-auto">
              <div v-for="asset in currentItemForPlacement.assets" :key="asset" class="flex flex-col gap-2">
                <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">{{ asset }}</label>
                <input 
                  type="text" 
                  v-model="placementForm[asset]" 
                  class="h-10 px-4 rounded-full border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-foreground w-full"
                  placeholder="Masukkan lokasi penempatan (misal: Mega Mendung)"
                />
              </div>
            </div>

            <!-- Footer Action Section -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-border bg-card shrink-0">
              <Button 
                variant="outline"
                @click="closePlacementModal"
                class="rounded-full h-10 px-6 font-bold text-sm border-input hover:bg-muted transition-colors"
              >
                Batal
              </Button>
              <Button 
                @click="handleSavePlacements"
                class="rounded-full h-10 px-6 font-bold text-sm bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm transition-colors"
              >
                Simpan Lokasi
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
