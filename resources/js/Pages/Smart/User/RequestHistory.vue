<script setup lang="ts">
/**
 * Request History Page
 * Displays a searchable, filterable list of previous requests and loans.
 * Provides quick filters by status, type (loan vs request), and time range.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import TableSearch from '@/Components/TableSearch.vue';
import RequestHistoryCard from '@/Components/RequestHistoryCard.vue';
import RequestCancelModal from '@/Pages/Smart/User/Modals/RequestCancelModal.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { 
  ChevronDown, 
  AlertTriangle, 
  X,
  Calendar
} from 'lucide-vue-next';
import { 
  type RequestStatus, 
  type RawRequestStatus, 
  getRequestStatusLabel,
  getLocalizedRequestStatusLabel
} from '@/lib/requestStatus';

// --- Data Types & Props ---
interface RequestItem {
  id: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  uom?: string;
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
  approval_by?: string | null;
  approval_at?: string | null;
  confirmation_by?: string | null;
  confirmation_at?: string | null;
  handover_method?: string | null;
  handover_time?: string | null;
  handover_location?: string | null;
  handover_note?: string | null;
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

// --- Filters & Search State ---
const { t } = useI18n();

const searchQuery = ref('');
const filterType = ref('all');            // 'all' | 'requests' | 'loans'
const filterStatus = ref('all');          // 'all' | 'Menunggu approval' | etc.
const filterTimeRange = ref('all');      // 'all' | 'today' | '7days' | '30days'

const typeOptions = computed(() => [
  { label: t('requests.allTypes'), value: 'all' },
  { label: t('requests.onlyRequests'), value: 'requests' },
  { label: t('requests.onlyLoans'), value: 'loans' },
]);

const filterTypeLabel = computed(() => {
  return typeOptions.value.find(o => o.value === filterType.value)?.label || t('requests.allTypes');
});

// Status Options
const statusOptions = [
  'Menunggu approval',
  'Di-approve',
  'Serah Terima',
  'Dipinjam',
  'Selesai',
  'Ditolak',
  'Dibatalkan',
  'Pending'
];

const filterStatusLabel = computed(() => {
  if (filterStatus.value === 'all') return t('requests.allStatuses');
  return getLocalizedRequestStatusLabel(filterStatus.value);
});

// Time Range Options
const timeRangeOptions = computed(() => [
  { label: t('requests.allRanges'), value: 'all' },
  { label: t('requests.today'), value: 'today' },
  { label: t('requests.last7Days'), value: '7days' },
  { label: t('requests.last30Days'), value: '30days' },
]);

const filterTimeRangeLabel = computed(() => {
  return timeRangeOptions.value.find(o => o.value === filterTimeRange.value)?.label || t('requests.allRanges');
});

/** Reset all search and filter states */
const clearFilter = () => {
  searchQuery.value = '';
  filterType.value = 'all';
  filterStatus.value = 'all';
  filterTimeRange.value = 'all';
};

// --- Filtered Data ---
const filteredRequests = computed(() => {
  return requests.value.filter(req => {
    // 1. Search Query Match
    const matchesSearch = 
      !searchQuery.value.trim() ||
      req.number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.items.some(item => 
        item.brand.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.subcategory.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.spec.toLowerCase().includes(searchQuery.value.toLowerCase())
      );

    // 2. Type Match
    let matchesType = true;
    if (filterType.value === 'requests') {
      matchesType = req.type === 'permintaan';
    } else if (filterType.value === 'loans') {
      matchesType = req.type === 'peminjaman';
    }

    // 3. Status Match
    const matchesStatus = filterStatus.value === 'all' || getRequestStatusLabel(req.status) === filterStatus.value;

    // 4. Time Range Match
    let matchesTime = true;
    if (filterTimeRange.value !== 'all' && req.created_at && req.created_at !== '-') {
      const datePart = req.created_at.split(' ')[0];
      const parts = datePart.split('-').map(Number);
      const reqDate = parts[0] > 1000
        ? new Date(parts[0], parts[1] - 1, parts[2])
        : new Date(parts[2], parts[1] - 1, parts[0]);
      
      const today = new Date();
      const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());

      const diffTime = todayDate.getTime() - reqDate.getTime();
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (filterTimeRange.value === 'today') {
        matchesTime = diffDays === 0;
      } else if (filterTimeRange.value === '7days') {
        matchesTime = diffDays >= 0 && diffDays <= 7;
      } else if (filterTimeRange.value === '30days') {
        matchesTime = diffDays >= 0 && diffDays <= 30;
      }
    }

    return matchesSearch && matchesType && matchesStatus && matchesTime;
  });
});

// --- Cancellation Modal State ---
const isCancelModalOpen = ref(false);
const activeRequestToCancel = ref<RequestHistory | null>(null);

/** Open the cancellation modal for a given request */
const openCancelModal = (req: RequestHistory) => {
  activeRequestToCancel.value = req;
  isCancelModalOpen.value = true;
};
</script>

<template>
  <AppLayout :title="$t('nav.history')">
    <div class="space-y-6">
      <div>
        <h1 class="text-lg font-bold text-gray-900 leading-none mb-5">{{ $t('requests.historyTitle') }}</h1>
        
        <!-- Filter & Search Section (Single Responsive Row) -->
        <div class="space-y-1.5 mb-5">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ $t('requests.searchAndFilter') }}</label>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <!-- Search Input -->
            <div class="flex-1 min-w-[240px] max-w-sm">
              <TableSearch 
                v-model="searchQuery" 
                :placeholder="$t('requests.historySearchPlaceholder')" 
                bg-class="bg-white"
              />
            </div>

            <!-- Filter Tipe -->
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterType === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ filterTypeLabel }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                <DropdownMenuItem 
                  v-for="opt in typeOptions" 
                  :key="opt.value" 
                  @select="filterType = opt.value"
                >
                  {{ opt.label }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <!-- Filter Status -->
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterStatus === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ filterStatusLabel }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                <DropdownMenuItem @select="filterStatus = 'all'">{{ $t('requests.allStatuses') }}</DropdownMenuItem>
                <DropdownMenuItem 
                  v-for="status in statusOptions" 
                  :key="status" 
                  @select="filterStatus = status"
                >
                  {{ getLocalizedRequestStatusLabel(status) }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <!-- Filter Rentang Waktu -->
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterTimeRange === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                  <div class="flex items-center gap-1.5 truncate">
                    <Calendar class="w-3.5 h-3.5 opacity-50 shrink-0" />
                    <span class="truncate">{{ filterTimeRangeLabel }}</span>
                  </div>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                <DropdownMenuItem 
                  v-for="opt in timeRangeOptions" 
                  :key="opt.value" 
                  @select="filterTimeRange = opt.value"
                >
                  {{ opt.label }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <!-- Clear Filter Button -->
            <Button variant="destructive" @click="clearFilter" class="hover:opacity-70 rounded-[0.875rem] px-0 sm:px-5 font-semibold text-white w-9 h-9 sm:w-auto sm:h-auto flex items-center justify-center shrink-0">
              <X class="w-4 h-4 sm:hidden" />
              <span class="hidden sm:inline">{{ $t('requests.clearFilter') }}</span>
            </Button>
          </div>
        </div>

        <p class="text-xs text-muted-foreground font-medium mb-3">{{ $t('requests.searchResults') }}</p>

        <!-- Grid / ScrollArea -->
        <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-21rem)] sm:h-[calc(100vh-18rem)]">
          <div class="p-2.5 sm:p-5">
            <!-- Empty State -->
            <div 
              v-if="filteredRequests.length === 0" 
              class="p-12 text-center"
            >
              <AlertTriangle class="w-10 h-10 text-muted-foreground/60 mx-auto mb-3" />
              <p class="text-sm text-muted-foreground font-medium">{{ $t('requests.emptyHistoryFilter') }}</p>
            </div>

            <!-- Request Cards -->
            <div v-else class="space-y-4">
              <RequestHistoryCard
                v-for="req in filteredRequests"
                :key="req.id"
                :request="req"
                @cancel="openCancelModal"
              />
            </div>
          </div>
        </ScrollArea>
      </div>
    </div>

    <!-- Modal Pembatalan Permintaan Component -->
    <RequestCancelModal
      v-model:open="isCancelModalOpen"
      :request="activeRequestToCancel"
      @close="activeRequestToCancel = null"
    />
  </AppLayout>
</template>

