<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';
import { Eye, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

interface Props {
  user: {
    name: string;
    email: string;
  };
  stats: {
    dalamPinjaman: number;
    belumDiproses: number;
    sudahDiproses: number;
    dalamPengembalian: number;
    sudahPantauan: number;
  };
  riwayatTerbaru: {
    penyerahan: Array<{
      id: number;
      number: string;
      requester: string;
      department: string;
      category_info: string;
      time: string;
    }>;
    pengembalian: Array<{
      id: number;
      number: string;
      requester: string;
      department: string;
      category_info: string;
      time: string;
    }>;
  };
  aktivitasTerbaru: Array<{
    id: number;
    user: string;
    request_number: string;
    action: string;
    time: string;
  }>;
  totalStok: Array<{
    name: string;
    code: string;
    count: number;
  }>;
  kualitasStok: Record<string, {
    name: string;
    code: string;
    baik: number;
    kurang_baik: number;
    buruk: number;
  }>;
}

const props = defineProps<Props>();

// Greeting computed
const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) return 'Selamat Pagi';
  if (hour >= 11 && hour < 15) return 'Selamat Siang';
  if (hour >= 15 && hour < 19) return 'Selamat Sore';
  return 'Selamat Malam';
});

const currentMonthYearLower = computed(() => {
  const date = new Date();
  const months = [
    'januari', 'februari', 'maret', 'april', 'mei', 'juni',
    'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
  ];
  return `${months[date.getMonth()]} ${date.getFullYear()}`;
});

// Navigate helper
const navigate = (path: string) => {
  router.visit(path);
};

// "Riwayat Terbaru" states and methods
const activeHistoryTab = ref<'penyerahan' | 'pengembalian'>('penyerahan');
const historyPage = ref(1);
const historyRowsPerPage = 5;

const historyData = computed(() => {
  return activeHistoryTab.value === 'penyerahan' 
    ? props.riwayatTerbaru.penyerahan 
    : props.riwayatTerbaru.pengembalian;
});

const totalHistoryPages = computed(() => {
  return Math.ceil(historyData.value.length / historyRowsPerPage) || 1;
});

const paginatedHistoryData = computed(() => {
  const start = (historyPage.value - 1) * historyRowsPerPage;
  return historyData.value.slice(start, start + historyRowsPerPage);
});

const setHistoryTab = (tab: 'penyerahan' | 'pengembalian') => {
  activeHistoryTab.value = tab;
  historyPage.value = 1;
};

const toggleHistoryTab = () => {
  setHistoryTab(activeHistoryTab.value === 'penyerahan' ? 'pengembalian' : 'penyerahan');
};

// "Kualitas Stok" cycle
const qualityCategoryKeys = computed(() => Object.keys(props.kualitasStok));
const activeQualityIndex = ref(0);

const activeQualityCategory = computed(() => {
  const key = qualityCategoryKeys.value[activeQualityIndex.value];
  return props.kualitasStok[key] || { name: '-', code: '', baik: 0, kurang_baik: 0, buruk: 0 };
});

const selectQualityIndex = (index: number) => {
  if (index >= 0 && index < qualityCategoryKeys.value.length) {
    activeQualityIndex.value = index;
  }
};

const prevQualityCategory = () => {
  if (activeQualityIndex.value > 0) {
    activeQualityIndex.value--;
  } else {
    activeQualityIndex.value = qualityCategoryKeys.value.length - 1;
  }
};

const nextQualityCategory = () => {
  if (activeQualityIndex.value < qualityCategoryKeys.value.length - 1) {
    activeQualityIndex.value++;
  } else {
    activeQualityIndex.value = 0;
  }
};

// Bar chart calculations
const maxStockCount = computed(() => {
  return Math.max(...props.totalStok.map(s => s.count), 1);
});

const maxQualityCount = computed(() => {
  const cat = activeQualityCategory.value;
  return Math.max(cat.baik, cat.kurang_baik, cat.buruk, 1);
});
</script>

<template>
  <AppLayout title="Dashboard">
    <!-- Breadcrumb -->
    <Breadcrumb class="pb-1">
      <BreadcrumbList class="pb-1.5">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/dashboard" class="text-xs">Dashboard</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-3.5 pb-4">
      <!-- Title Header Section -->
      <div class="mb-2">
        <Heading as="h1" class="text-xl sm:text-2xl pb-1">
          {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'Admin' }}</span>!
        </Heading>
        <p class="text-xs text-muted-foreground mt-0.5">
          Selamat datang kembali di aplikasi SMART (Stock Management and Request Tracking)
        </p>
      </div>

      <!-- Main Dashboard Flex Layout (Auto Layout) -->
      <div class="flex flex-col xl:flex-row gap-4 w-full xl:items-stretch items-start">
        
        <!-- Left Side Column (takes 3/4 width on desktop) -->
        <div class="flex-1 xl:flex-[3] flex flex-col gap-4 min-w-0 w-full">
          
          <!-- Top 3 Stat Cards Row (Horizontal Auto Layout) -->
          <div class="flex flex-col md:flex-row gap-4 w-full">
            
            <!-- Dalam Pinjaman -->
            <div class="flex-1 bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md min-w-0">
              <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
                Dalam Pinjaman
              </div>
              <div class="p-3 flex items-center justify-between flex-1">
                <span class="text-2xl font-bold text-foreground">{{ stats.dalamPinjaman }}</span>
                <button 
                  @click="navigate('/smart/borrowed')"
                  class="w-7 h-7 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Lihat Detail Peminjaman Aktif"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Belum Diproses -->
            <div class="flex-1 bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md min-w-0">
              <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
                Belum Diproses
              </div>
              <div class="p-3 flex items-center justify-between flex-1">
                <span class="text-2xl font-bold text-foreground">{{ stats.belumDiproses }}</span>
                <button 
                  @click="navigate('/smart/inbox')"
                  class="w-7 h-7 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Lihat Detail Permintaan Baru"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Sudah Diproses -->
            <div class="flex-1 bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md min-w-0">
              <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
                Sudah Diproses
              </div>
              <div class="p-3 flex items-center justify-between flex-1">
                <span class="text-2xl font-bold text-foreground">{{ stats.sudahDiproses }}</span>
                <button 
                  @click="navigate('/smart/arsip')"
                  class="w-7 h-7 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Lihat Arsip Permintaan"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>
            </div>

          </div>

          <!-- Riwayat Terbaru Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col xl:h-[354px]">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide flex justify-between items-center">
              <span>Riwayat Terbaru</span>
            </div>
            
            <div class="p-4 space-y-3 flex flex-col xl:flex-1 justify-between">
              <!-- Sub-header with eye icon -->
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-foreground capitalize">
                  {{ activeHistoryTab }}
                </h3>
                <button 
                  @click="navigate(activeHistoryTab === 'penyerahan' ? '/smart/handover' : '/smart/returns')"
                  class="w-8 h-8 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  :title="'Lihat Detail ' + (activeHistoryTab === 'penyerahan' ? 'Serah Terima' : 'Pengembalian')"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>

              <!-- Table -->
              <div class="overflow-x-auto border border-border rounded-xl xl:h-[185px]">
                <table class="w-full text-xs text-left border-collapse">
                  <thead>
                    <tr class="bg-muted/50 border-b border-border text-foreground font-semibold">
                      <th class="p-2 w-12 text-center">No</th>
                      <th class="p-2">Nama</th>
                      <th class="p-2">Departemen</th>
                      <th class="p-2">Kategori(subkategori)</th>
                      <th class="p-2">
                        {{ activeHistoryTab === 'penyerahan' ? 'Waktu Penyerahan' : 'Waktu Pengembalian' }}
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-border">
                    <tr 
                      v-for="(row, idx) in paginatedHistoryData" 
                      :key="row.id"
                      class="hover:bg-muted/20 transition-colors"
                    >
                      <td class="p-2 text-center text-muted-foreground font-medium">
                        {{ (historyPage - 1) * historyRowsPerPage + idx + 1 }}
                      </td>
                      <td class="p-2 font-semibold text-foreground">
                        {{ row.requester }}
                      </td>
                      <td class="p-2 text-muted-foreground">
                        {{ row.department }}
                      </td>
                      <td class="p-2 text-foreground font-medium">
                        {{ row.category_info }}
                      </td>
                      <td class="p-2 text-muted-foreground font-normal text-[10px]">
                        {{ row.time }}
                      </td>
                    </tr>
                    <tr v-if="paginatedHistoryData.length === 0">
                      <td colspan="5" class="p-6 text-center text-muted-foreground italic">
                        Tidak ada riwayat {{ activeHistoryTab === 'penyerahan' ? 'penyerahan' : 'pengembalian' }}.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Footer with Tab Toggles & Pagination -->
              <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1.5 border-t border-border mt-auto">
                
                <!-- Tab Selector: < Penyerahan Pengembalian > -->
                <div class="flex items-center space-x-2 bg-muted/40 border border-border p-0.5 rounded-xl">
                  <button 
                    @click="toggleHistoryTab"
                    class="p-1 text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                  >
                    <ChevronLeft class="w-3.5 h-3.5" />
                  </button>
                  
                  <button 
                    @click="setHistoryTab('penyerahan')"
                    class="px-3 py-1 rounded-lg text-[10px] font-bold transition-all cursor-pointer"
                    :class="activeHistoryTab === 'penyerahan' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                  >
                    Penyerahan
                  </button>
                  <button 
                    @click="setHistoryTab('pengembalian')"
                    class="px-3 py-1 rounded-lg text-[10px] font-bold transition-all cursor-pointer"
                    :class="activeHistoryTab === 'pengembalian' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                  >
                    Pengembalian
                  </button>

                  <button 
                    @click="toggleHistoryTab"
                    class="p-1 text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                  >
                    <ChevronRight class="w-3.5 h-3.5" />
                  </button>
                </div>

                <!-- Pagination controls -->
                <div class="flex items-center space-x-1" v-if="totalHistoryPages > 1">
                  <button 
                    @click="historyPage > 1 ? historyPage-- : null"
                    :disabled="historyPage === 1"
                    class="p-1.5 text-muted-foreground hover:text-foreground disabled:opacity-30 cursor-pointer"
                  >
                    <ChevronLeft class="w-3.5 h-3.5" />
                  </button>
                  
                  <button 
                    v-for="p in totalHistoryPages" 
                    :key="p"
                    @click="historyPage = p"
                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-semibold cursor-pointer transition-colors"
                    :class="historyPage === p ? 'border border-border bg-card text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                  >
                    {{ p }}
                  </button>

                  <button 
                    @click="historyPage < totalHistoryPages ? historyPage++ : null"
                    :disabled="historyPage === totalHistoryPages"
                    class="p-1.5 text-muted-foreground hover:text-foreground disabled:opacity-30 cursor-pointer"
                  >
                    <ChevronRight class="w-3.5 h-3.5" />
                  </button>
                </div>

              </div>
            </div>
          </div>

          <!-- Aktivitas Terbaru Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col xl:h-[300px]">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
              Aktivitas Terbaru
            </div>
            <div class="p-4 flex flex-col xl:flex-1 justify-between">
              <div class="overflow-y-auto border border-border rounded-xl xl:h-[220px]">
                <table class="w-full text-xs text-left border-collapse">
                  <tbody class="divide-y divide-border">
                    <tr 
                      v-for="act in aktivitasTerbaru" 
                      :key="act.id" 
                      class="hover:bg-muted/20 transition-colors"
                    >
                      <td class="p-2 font-semibold text-foreground w-1/4 truncate">{{ act.user }}</td>
                      <td class="p-2 text-muted-foreground w-1/4 font-mono text-[10px] truncate">{{ act.request_number }}</td>
                      <td class="p-2 text-foreground w-1/4 truncate font-medium">{{ act.action }}</td>
                      <td class="p-2 text-muted-foreground w-1/4 font-normal text-[10px] whitespace-nowrap">{{ act.time }}</td>
                    </tr>
                    <tr v-if="aktivitasTerbaru.length === 0">
                      <td colspan="4" class="p-4 text-center text-muted-foreground italic">
                        Belum ada aktivitas tercatat.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Side Column (takes 1/4 width on desktop) -->
        <div class="w-full xl:w-[25%] flex flex-col gap-4 shrink-0">
          
          <!-- Dalam Pengembalian Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
              Dalam Pengembalian
            </div>
            <div class="p-3 flex items-center justify-between flex-1">
              <span class="text-2xl font-bold text-foreground">{{ stats.dalamPengembalian }}</span>
              <button 
                @click="navigate('/smart/returns')"
                class="w-7 h-7 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                title="Lihat Daftar Pengembalian"
              >
                <Eye class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Sudah Pantauan Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-md">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
              Sudah Pantauan
            </div>
            <div class="p-3 flex items-center justify-between flex-1">
              <span class="text-2xl font-bold text-foreground">{{ stats.sudahPantauan }}</span>
              <button 
                @click="navigate('/smart/inventory')"
                class="w-7 h-7 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                title="Lihat Stok & Aset"
              >
                <Eye class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Total Stok Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
              Total Stok
            </div>
            <div class="p-4 space-y-3">
              <p class="text-[10px] text-muted-foreground font-semibold">
                Stok update pada tanggal {{ currentMonthYearLower }}
              </p>

              <!-- Custom Bar Chart Grid (shorter height) -->
              <div class="flex items-end justify-around h-32 pt-2 pb-1.5 border-b border-border">
                <div 
                  v-for="item in totalStok" 
                  :key="item.code"
                  class="flex flex-col items-center group w-12"
                >
                  <!-- Bar Wrapper -->
                  <div class="relative w-5 bg-muted rounded-t-lg h-20 flex items-end">
                    <div 
                      class="w-full bg-[#3b82f6] rounded-t-lg transition-all duration-500"
                      :style="{ height: `${Math.max(5, (item.count / maxStockCount) * 100)}%` }"
                    ></div>
                  </div>
                  
                  <!-- Bar Label -->
                  <span class="text-[10px] font-bold text-foreground mt-1.5 truncate max-w-full" :title="item.name">
                    {{ item.count }}
                  </span>
                  <span class="text-[9px] text-muted-foreground font-bold tracking-tight uppercase truncate max-w-full">
                    {{ item.code === 'ELK' ? 'ELK' : item.name.split(' ')[0] }}
                  </span>
                </div>
              </div>

              <!-- Eye Buttons Row below the chart -->
              <div class="flex items-center justify-around pt-0.5">
                <div 
                  v-for="item in totalStok" 
                  :key="'btn-' + item.code"
                  class="flex justify-center w-12"
                >
                  <button 
                    @click="navigate('/smart/inventory')"
                    class="w-6 h-6 bg-[#53c3c2] hover:bg-[#3fb0af] text-white rounded-full flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                    :title="'Buka Manajemen Stok ' + item.name"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Kualitas Stok Card -->
          <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col xl:flex-1">
            <div class="bg-[#5e50f9] px-3 py-1.5 text-white font-bold text-xs tracking-wide">
              Kualitas Stok
            </div>
            <div class="p-4 space-y-3 flex flex-col xl:flex-1 justify-between">
              <p class="text-[10px] text-muted-foreground font-semibold">
                Stok update pada tanggal {{ currentMonthYearLower }}
              </p>

              <!-- Custom Quality Bar Chart -->
              <div class="flex items-end justify-around h-32 xl:h-44 pt-2 pb-1.5 border-b border-border">
                
                <!-- Baik -->
                <div class="flex flex-col items-center w-14">
                  <div class="relative w-5 bg-muted rounded-t-lg h-20 xl:h-32 flex items-end">
                    <div 
                      class="w-full bg-[#3b82f6] rounded-t-lg transition-all duration-500"
                      :style="{ height: `${Math.max(5, (activeQualityCategory.baik / maxQualityCount) * 100)}%` }"
                    ></div>
                  </div>
                  <span class="text-[10px] font-bold text-foreground mt-1.5">
                    {{ activeQualityCategory.baik }}
                  </span>
                  <span class="text-[9px] text-muted-foreground font-bold tracking-tight uppercase">
                    Baik
                  </span>
                </div>

                <!-- Kurang baik -->
                <div class="flex flex-col items-center w-14">
                  <div class="relative w-5 bg-muted rounded-t-lg h-20 xl:h-32 flex items-end">
                    <div 
                      class="w-full bg-[#3b82f6] rounded-t-lg transition-all duration-500"
                      :style="{ height: `${Math.max(5, (activeQualityCategory.kurang_baik / maxQualityCount) * 100)}%` }"
                    ></div>
                  </div>
                  <span class="text-[10px] font-bold text-foreground mt-1.5">
                    {{ activeQualityCategory.kurang_baik }}
                  </span>
                  <span class="text-[9px] text-muted-foreground font-bold tracking-tight uppercase whitespace-nowrap">
                    Kurang baik
                  </span>
                </div>

                <!-- Buruk -->
                <div class="flex flex-col items-center w-14">
                  <div class="relative w-5 bg-muted rounded-t-lg h-20 xl:h-32 flex items-end">
                    <div 
                      class="w-full bg-[#3b82f6] rounded-t-lg transition-all duration-500"
                      :style="{ height: `${Math.max(5, (activeQualityCategory.buruk / maxQualityCount) * 100)}%` }"
                    ></div>
                  </div>
                  <span class="text-[10px] font-bold text-foreground mt-1.5">
                    {{ activeQualityCategory.buruk }}
                  </span>
                  <span class="text-[9px] text-muted-foreground font-bold tracking-tight uppercase">
                    Buruk
                  </span>
                </div>

              </div>

              <!-- Cycle category navigation controls -->
              <div class="flex flex-col items-center space-y-1.5 pt-0.5 mt-auto">
                <!-- Selected category name -->
                <span class="text-xs font-bold text-foreground text-center truncate max-w-full">
                  {{ activeQualityCategory.name }}
                </span>

                <!-- Pagination selector: < 1 2 3 > -->
                <div class="flex items-center space-x-1">
                  <button 
                    @click="prevQualityCategory"
                    class="p-1 text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                  >
                    <ChevronLeft class="w-3.5 h-3.5" />
                  </button>
                  
                  <button 
                    v-for="(key, idx) in qualityCategoryKeys" 
                    :key="key"
                    @click="selectQualityIndex(idx)"
                    class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-semibold cursor-pointer transition-colors"
                    :class="activeQualityIndex === idx ? 'border border-border bg-card text-foreground font-bold shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                  >
                    {{ idx + 1 }}
                  </button>

                  <button 
                    @click="nextQualityCategory"
                    class="p-1 text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                  >
                    <ChevronRight class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

            </div>
          </div>

        </div>

      </div>
    </div>
  </AppLayout>
</template>
