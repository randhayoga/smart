<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
} from '@/Components/ui/breadcrumb';
import { Search, Calendar, AlertTriangle } from 'lucide-vue-next';
import ManagerRequestCard from '@/Components/ManagerRequestCard.vue';

interface RequestItem {
  id: number;
  subcategory: string;
  brand: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
}

interface RequestHistory {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman';
  requester: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: string;
  created_at: string;
  items: RequestItem[];
}

interface Props {
  user?: any;
  requests?: RequestHistory[];
}

const props = withDefaults(defineProps<Props>(), {
  requests: () => []
});

const requests = ref<RequestHistory[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// ─────────────────────────────────────────────
// State Filters & Search
// ─────────────────────────────────────────────
const searchQuery = ref('');
const filterSort = ref('terbaru');        // 'terbaru' | 'terlama'
const filterUtilization = ref('semua');   // 'semua' | 'corporate' | 'project'
const filterProject = ref('semua');       // 'semua' | unique projects
const filterTimeRange = ref('semua');     // 'semua' | 'hari-ini' | '7-hari' | '30-hari'

// Project Options (Unique project details from data)
const projectOptions = computed(() => {
  const projs = new Set<string>();
  requests.value.forEach(req => {
    if (req.pemanfaatan === 'project' && req.pemanfaatanDetail) {
      projs.add(req.pemanfaatanDetail);
    }
  });
  return Array.from(projs);
});

// ─────────────────────────────────────────────
// Filtered & Sorted Data
// ─────────────────────────────────────────────
const filteredRequests = computed(() => {
  let list = requests.value.filter(req => {
    // 1. Search Query Match
    const matchesSearch = 
      req.number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.requester.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.items.some(item => 
        item.brand.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.subcategory.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.spec.toLowerCase().includes(searchQuery.value.toLowerCase())
      );

    // 2. Pemanfaatan Match
    const matchesUtilization = filterUtilization.value === 'semua' || req.pemanfaatan === filterUtilization.value;

    // 3. Project Match
    const matchesProject = filterProject.value === 'semua' || 
      (req.pemanfaatan === 'project' && req.pemanfaatanDetail === filterProject.value);

    // 4. Time Range Match
    let matchesTime = true;
    if (filterTimeRange.value !== 'semua') {
      const reqDate = new Date(req.created_at);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (filterTimeRange.value === 'hari-ini') {
        const reqDateStr = req.created_at;
        const todayStr = today.toISOString().split('T')[0];
        matchesTime = reqDateStr === todayStr;
      } else if (filterTimeRange.value === '7-hari') {
        const diffTime = Math.abs(today.getTime() - reqDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        matchesTime = diffDays <= 7;
      } else if (filterTimeRange.value === '30-hari') {
        const diffTime = Math.abs(today.getTime() - reqDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        matchesTime = diffDays <= 30;
      }
    }

    return matchesSearch && matchesUtilization && matchesProject && matchesTime;
  });

  // Sorting
  if (filterSort.value === 'terbaru') {
    return [...list].sort((a, b) => b.id - a.id);
  } else if (filterSort.value === 'terlama') {
    return [...list].sort((a, b) => a.id - b.id);
  }
  return list;
});
</script>

<template>
  <Head title="Sudah Diproses" />

  <AppLayout title="Sudah Diproses">
    <!-- ── Breadcrumb ── -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3 text-xs md:text-sm">
        <BreadcrumbItem>
          <span class="text-muted-foreground">Sudah Diproses</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- ── Judul Halaman ── -->
    <div class="mb-4">
      <h1 class="text-2xl font-bold text-foreground">Sudah Diproses</h1>
    </div>

    <!-- ── Input Pencarian ── -->
    <div class="relative w-full max-w-xl mb-6">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Cari nomor permintaan atau nama yang meminta barang..."
        class="w-full h-11 pl-4 pr-12 text-sm border border-input rounded-full bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-sm"
      />
      <div class="absolute right-1 top-1 h-9 w-9 bg-primary/10 rounded-full flex items-center justify-center text-primary">
        <Search class="w-4 h-4" />
      </div>
    </div>

    <!-- ── Filter Controls ── -->
    <div class="space-y-2 mb-6">
      <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">Filter</span>
      
      <div class="flex flex-wrap gap-3 items-center">
        <!-- Urutkan -->
        <div class="w-full sm:w-[180px]">
          <Select v-model="filterSort">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Urutkan" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="terbaru">Terbaru</SelectItem>
              <SelectItem value="terlama">Terlama</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Corporate & Project -->
        <div class="w-full sm:w-[200px]">
          <Select v-model="filterUtilization">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Corporate & Project" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Corporate &amp; Project</SelectItem>
              <SelectItem value="corporate">Hanya Corporate</SelectItem>
              <SelectItem value="project">Hanya Project</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Pilih Project -->
        <div class="w-full sm:w-[180px]">
          <Select v-model="filterProject" :disabled="filterUtilization === 'corporate'">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Pilih Project" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Pilih Project</SelectItem>
              <SelectItem 
                v-for="proj in projectOptions" 
                :key="proj" 
                :value="proj"
              >
                {{ proj }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Rentang Waktu -->
        <div class="w-full sm:w-[220px]">
          <Select v-model="filterTimeRange">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm flex items-center gap-2">
              <Calendar class="w-4 h-4 text-muted-foreground shrink-0" />
              <SelectValue placeholder="Semua Rentang Waktu" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Semua Rentang Waktu</SelectItem>
              <SelectItem value="hari-ini">Hari Ini</SelectItem>
              <SelectItem value="7-hari">7 Hari Terakhir</SelectItem>
              <SelectItem value="30-hari">30 Hari Terakhir</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <!-- ── Hasil Pencarian Title ── -->
    <div class="mb-4">
      <span class="text-sm font-semibold text-foreground">Hasil Pencarian dan Filter:</span>
    </div>

    <!-- ── Daftar Request Cards ── -->
    <div class="space-y-4">
      <!-- Empty State -->
      <div 
        v-if="filteredRequests.length === 0" 
        class="bg-card border border-border rounded-[14px] p-12 text-center"
      >
        <AlertTriangle class="w-10 h-10 text-muted-foreground/60 mx-auto mb-3" />
        <p class="text-sm text-muted-foreground font-medium">Tidak ada permintaan yang sudah diproses.</p>
      </div>

      <!-- Request Card -->
      <ManagerRequestCard
        v-for="req in filteredRequests"
        :key="req.id"
        :request="req"
        :detailRoute="route('smart.approve.show', req.id)"
      />
    </div>
  </AppLayout>
</template>
