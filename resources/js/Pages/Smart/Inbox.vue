<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  Search, 
  ChevronDown, 
  ArrowUpDown, 
  ArrowUp,
  ArrowDown,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Printer,
  FileDown,
  Eye,
  FileText
} from 'lucide-vue-next';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

const dummyInbox = [
  { 
    id: 1, 
    number: '052026-0001', 
    amount: 12, 
    requester: 'John Doe', 
    createdAt: '05-05-2026 09:30', 
    startTime: '05-05-2026 10:00', 
    endTime: '-',
    type: 'Habis Pakai'
  },
  { 
    id: 2, 
    number: '052026-0002', 
    amount: 1, 
    requester: 'Jane Smith', 
    createdAt: '05-05-2026 09:45', 
    startTime: '05-05-2026 11:00', 
    endTime: '06-05-2026 11:00',
    type: 'Pinjam'
  },
  { 
    id: 3, 
    number: '052026-0003', 
    amount: 5, 
    requester: 'Budi Santoso', 
    createdAt: '05-05-2026 10:15', 
    startTime: '05-05-2026 13:00', 
    endTime: '-',
    type: 'Habis Pakai'
  },
  { 
    id: 4, 
    number: '052026-0004', 
    amount: 2, 
    requester: 'Siti Aminah', 
    createdAt: '05-05-2026 11:00', 
    startTime: '05-05-2026 14:00', 
    endTime: '05-05-2026 17:00',
    type: 'Pinjam'
  },
];

const sortKey = ref('number');
const sortOrder = ref('desc');
const searchQuery = ref('');
const typeFilter = ref('');
const timeFilter = ref('');
const selectedItems = ref<number[]>([]);

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortOrder.value = 'asc';
  }
};

const displayData = computed(() => {
  let data = [...dummyInbox];
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    data = data.filter(item => 
      item.number.toLowerCase().includes(query) || 
      item.requester.toLowerCase().includes(query)
    );
  }

  if (typeFilter.value) {
    data = data.filter(item => item.type === typeFilter.value);
  }

  // Time filter logic would go here
  
  if (sortKey.value) {
    data.sort((a: any, b: any) => {
      let aVal = a[sortKey.value];
      let bVal = b[sortKey.value];
      
      if (typeof aVal === 'string') aVal = aVal.toLowerCase();
      if (typeof bVal === 'string') bVal = bVal.toLowerCase();
      
      if (aVal < bVal) return sortOrder.value === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortOrder.value === 'asc' ? 1 : -1;
      return 0;
    });
  }
  
  return data;
});

const toggleSelectAll = (e: any) => {
  if (e.target.checked) {
    selectedItems.value = displayData.value.map(item => item.id);
  } else {
    selectedItems.value = [];
  }
};

const toggleSelectItem = (id: number) => {
  const index = selectedItems.value.indexOf(id);
  if (index > -1) {
    selectedItems.value.splice(index, 1);
  } else {
    selectedItems.value.push(id);
  }
};

const isAllSelected = computed(() => {
  return displayData.value.length > 0 && selectedItems.value.length === displayData.value.length;
});
</script>

<template>
  <AppLayout title="Inbox">
    <template #header>
      <h1 class="text-xl font-medium text-foreground">
        Inbox
      </h1>
    </template>
    
    <div class="space-y-6">
      <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-bold text-foreground">Daftar Permintaan Baru</h2>
      </div>

      <!-- Main Card -->
      <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-5">
          <!-- Filters -->
          <div class="space-y-4">
            <div class="flex flex-col sm:flex-row items-end gap-4">
              <div class="space-y-1.5 flex-1 max-w-md">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <div class="relative">
                  <input 
                    type="text" 
                    v-model="searchQuery"
                    placeholder="Cari nomor permintaan atau nama peminta..." 
                    class="w-full pl-9 pr-3 py-2 text-sm border border-input rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  />
                  <Search class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                </div>
              </div>

              <div class="relative flex-1 max-w-[200px]">
                <select 
                  v-model="typeFilter"
                  class="appearance-none w-full px-3 py-2 border border-input rounded-lg text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-colors"
                >
                  <option value="">Semua jenis</option>
                  <option value="Habis Pakai">Habis Pakai</option>
                  <option value="Pinjam">Pinjam</option>
                </select>
                <ChevronDown class="w-4 h-4 text-muted-foreground absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              </div>

              <div class="relative flex-1 max-w-[200px]">
                <select 
                  v-model="timeFilter"
                  class="appearance-none w-full px-3 py-2 border border-input rounded-lg text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-colors"
                >
                  <option value="">Semua kurun waktu</option>
                  <option value="today">Hari Ini</option>
                  <option value="week">Minggu Ini</option>
                  <option value="month">Bulan Ini</option>
                </select>
                <ChevronDown class="w-4 h-4 text-muted-foreground absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              </div>
            </div>

            <!-- Export Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
              <div class="space-y-2">
                <label class="text-xs text-muted-foreground font-medium block">Aksi Terpilih</label>
                <div class="flex flex-wrap gap-2">
                  <button class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                    <Printer class="w-4 h-4" />
                    Print
                  </button>
                  <button class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                    <FileDown class="w-4 h-4" />
                    Export: Excel
                  </button>
                  <button class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                    <FileDown class="w-4 h-4" />
                    Export: PDF
                  </button>
                  <button class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                    <FileDown class="w-4 h-4" />
                    Export: CSV
                  </button>
                </div>
              </div>

              <div class="flex items-center gap-3 text-sm text-muted-foreground pt-6">
                <span>Baris per halaman</span>
                <div class="relative">
                  <select class="appearance-none pl-3 pr-8 py-2 border border-input rounded-lg text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-colors">
                    <option>Semua baris</option>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                  </select>
                  <ChevronDown class="w-4 h-4 text-muted-foreground absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="px-[10px] pb-[10px]">
          <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left">
                <thead class="text-xs text-foreground bg-muted/50 border-b border-border">
                  <tr>
                    <th class="px-4 py-4 w-10">
                      <input 
                        type="checkbox" 
                        :checked="isAllSelected"
                        @change="toggleSelectAll"
                        class="rounded border-input text-primary focus:ring-primary/20 transition-colors"
                      />
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold">
                      <div @click="toggleSort('number')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'number' }">
                        Nomor
                        <ArrowUp v-if="sortKey === 'number' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'number' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold text-center">
                      <div @click="toggleSort('amount')" class="flex items-center justify-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'amount' }">
                        Jumlah
                        <ArrowUp v-if="sortKey === 'amount' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'amount' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold">
                      <div @click="toggleSort('requester')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'requester' }">
                        Nama Peminta
                        <ArrowUp v-if="sortKey === 'requester' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'requester' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold">
                      <div @click="toggleSort('createdAt')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'createdAt' }">
                        Waktu Dibuat
                        <ArrowUp v-if="sortKey === 'createdAt' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'createdAt' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold">
                      <div @click="toggleSort('startTime')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'startTime' }">
                        Waktu Mulai
                        <ArrowUp v-if="sortKey === 'startTime' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'startTime' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold">
                      <div @click="toggleSort('endTime')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none" :class="{ 'text-primary': sortKey === 'endTime' }">
                        Waktu Selesai
                        <ArrowUp v-if="sortKey === 'endTime' && sortOrder === 'asc'" class="w-3.5 h-3.5" />
                        <ArrowDown v-else-if="sortKey === 'endTime' && sortOrder === 'desc'" class="w-3.5 h-3.5" />
                        <ArrowUpDown v-else class="w-3.5 h-3.5 text-muted-foreground" />
                      </div>
                    </th>
                    <th scope="col" class="px-4 py-4 font-semibold text-right">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="(item, index) in displayData" 
                    :key="item.id"
                    class="border-b border-border hover:bg-muted/30 transition-colors"
                    :class="{ 'border-none': index === displayData.length - 1 }"
                  >
                    <td class="px-4 py-4">
                      <input 
                        type="checkbox" 
                        :checked="selectedItems.includes(item.id)"
                        @change="toggleSelectItem(item.id)"
                        class="rounded border-input text-primary focus:ring-primary/20 transition-colors"
                      />
                    </td>
                    <td class="px-4 py-4 font-medium text-foreground">
                      {{ item.number }}
                    </td>
                    <td class="px-4 py-4 text-center text-muted-foreground">
                      {{ item.amount }}
                    </td>
                    <td class="px-4 py-4 text-foreground">
                      {{ item.requester }}
                    </td>
                    <td class="px-4 py-4 text-muted-foreground">
                      {{ item.createdAt }}
                    </td>
                    <td class="px-4 py-4 text-muted-foreground">
                      {{ item.startTime }}
                    </td>
                    <td class="px-4 py-4 text-muted-foreground">
                      {{ item.endTime }}
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-end gap-2">
                        <button class="p-2 bg-cyan-400 hover:bg-cyan-500 text-white rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-400/50">
                          <Eye class="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="displayData.length === 0">
                    <td colspan="8" class="px-4 py-12 text-center text-muted-foreground italic">
                      Tidak ada permintaan baru yang ditemukan.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer Pagination -->
        <div class="p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
          <div>
            {{ selectedItems.length }} dari {{ displayData.length }} baris dipilih
          </div>
          
          <div class="flex items-center gap-1">
            <button class="flex items-center gap-1 px-2 py-1 hover:text-foreground transition-colors disabled:opacity-50" :disabled="true">
              <ChevronLeft class="w-4 h-4" />
              <span>Sebelumnya</span>
            </button>
            
            <div class="flex items-center gap-1 px-2">
              <button class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-accent text-foreground transition-colors">1</button>
              <button class="w-8 h-8 flex items-center justify-center rounded-full border border-border bg-background font-medium text-foreground transition-colors shadow-sm">2</button>
              <button class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-accent text-foreground transition-colors">3</button>
              <span class="px-1"><MoreHorizontal class="w-4 h-4 text-muted-foreground" /></span>
            </div>

            <button class="flex items-center gap-1 px-2 py-1 hover:text-foreground transition-colors">
              <span>Selanjutnya</span>
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Any custom styles can go here if needed */
</style>
