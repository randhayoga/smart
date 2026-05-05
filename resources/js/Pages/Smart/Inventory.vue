<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  Search, 
  ChevronDown, 
  ArrowUpDown, 
  ArrowUp,
  ArrowDown,
  Plus, 
  Trash2,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Printer,
  FileDown,
  Eye,
  Package
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

// Dummy Data
const categories = ['Elektronik', 'Furnitur', 'ATK', 'Kendaraan'];
const subcategories = ['Laptop', 'Meja', 'Kertas', 'Mobil'];
const brands = ['Asus', 'IKEA', 'Sinar Dunia', 'Toyota'];

const dummyInventory = [
  { id: 1, code: 'ELE-LAP-ASUS', category: 'Elektronik', subcategory: 'Laptop', brand: 'Asus', specification: 'ROG Zephyrus G14, 16GB RAM, 512GB SSD', lastUpdate: '05-05-2026 10:00', amount: 5 },
  { id: 2, code: 'FUR-MEJ-IKEA', category: 'Furnitur', subcategory: 'Meja', brand: 'IKEA', specification: 'Linnmon Table, White, 120x60cm', lastUpdate: '05-05-2026 11:30', amount: 12 },
  { id: 3, code: 'ATK-KER-SIDU', category: 'ATK', subcategory: 'Kertas', brand: 'Sinar Dunia', specification: 'A4 80gsm, 500 sheets', lastUpdate: '04-05-2026 09:15', amount: 50 },
  { id: 4, code: 'VEH-MOB-TOYO', category: 'Kendaraan', subcategory: 'Mobil', brand: 'Toyota', specification: 'Avanza 2023, Silver Metallic', lastUpdate: '03-05-2026 15:45', amount: 2 },
  { id: 5, code: 'ELE-LAP-LENO', category: 'Elektronik', subcategory: 'Laptop', brand: 'Lenovo', specification: 'ThinkPad X1 Carbon, 32GB RAM', lastUpdate: '05-05-2026 14:20', amount: 3 },
];

const sortKey = ref('code');
const sortOrder = ref('asc');
const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const rowsPerPage = ref('Semua baris');
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
  let data = [...dummyInventory];
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    data = data.filter(item => 
      item.code.toLowerCase().includes(query) || 
      item.specification.toLowerCase().includes(query)
    );
  }

  if (categoryFilter.value) {
    data = data.filter(item => item.category === categoryFilter.value);
  }

  if (subcategoryFilter.value) {
    data = data.filter(item => item.subcategory === subcategoryFilter.value);
  }

  if (brandFilter.value) {
    data = data.filter(item => item.brand === brandFilter.value);
  }
  
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

const isAllSelected = computed(() => {
  return displayData.value.length > 0 && selectedItems.value.length === displayData.value.length;
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

// Export & Print Logic
const handlePrint = () => {
  window.print();
};

const handleExportCSV = () => {
  if (displayData.value.length === 0) return;
  
  const headers = ['Kode', 'Kategori', 'Subkategori', 'Merek', 'Spesifikasi', 'Pembaruan Terakhir', 'Jumlah'];
  const rows = displayData.value.map(item => [
    `"${item.code}"`,
    `"${item.category}"`,
    `"${item.subcategory}"`,
    `"${item.brand}"`,
    `"${item.specification}"`,
    `"${item.lastUpdate}"`,
    `"${item.amount}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map(e => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `inventory_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
};

const handleExportPDF = () => {
  window.print();
};
</script>

<template>
  <AppLayout title="Manajemen Stok">
    <template #header>
      <div class="flex items-center gap-3 no-print">
        <h1 class="text-xl font-medium text-foreground">
          Manajemen Stok
        </h1>
      </div>
    </template>
    
    <div class="space-y-4">
      <!-- Main Card -->
      <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-5 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Stok</h2>
          
          <!-- Filters Row 1 -->
          <div class="mt-4 flex flex-col space-y-4">
            <div class="flex flex-wrap items-end gap-3">
              <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <div class="relative">
                  <Search class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                  <input 
                    type="text" 
                    v-model="searchQuery"
                    placeholder="Cari kode Barang..." 
                    class="w-full pl-9 pr-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  />
                </div>
              </div>

              <!-- Category Dropdown -->
              <div class="w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ categoryFilter || 'Semua kategori' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem @select="categoryFilter = ''">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in categories" :key="cat" @select="categoryFilter = cat">
                      {{ cat }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <!-- Subcategory Dropdown -->
              <div class="w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ subcategoryFilter || 'Semua subkategori' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem @select="subcategoryFilter = ''">Semua subkategori</DropdownMenuItem>
                    <DropdownMenuItem v-for="sub in subcategories" :key="sub" @select="subcategoryFilter = sub">
                      {{ sub }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <div class="flex-grow"></div>

              <div class="flex items-center gap-3 text-sm text-muted-foreground">
                <span>Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal">
                      {{ rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px]">
                    <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <!-- Filters Row 2 & Actions -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ brandFilter || 'Semua merek' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem @select="brandFilter = ''">Semua merek</DropdownMenuItem>
                    <DropdownMenuItem v-for="brand in brands" :key="brand" @select="brandFilter = brand">
                      {{ brand }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <div class="flex-grow"></div>
              
              <button class="flex items-center gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm">
                <Plus class="w-4 h-4" />
                <span>Barang Baru</span>
              </button>
            </div>

            <!-- Bulk Actions -->
            <div class="space-y-2">
              <label class="text-xs text-muted-foreground font-medium block">Aksi Terpilih</label>
              <div class="flex flex-wrap gap-2">
                <button 
                  :disabled="selectedItems.length === 0"
                  class="flex items-center gap-2 px-4 py-2 bg-muted text-muted-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50"
                >
                  <Trash2 class="w-4 h-4" />
                  Hapus Barang
                </button>
                <button 
                  @click="handlePrint"
                  class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  <Printer class="w-4 h-4" />
                  Print
                </button>
                <button 
                  @click="handleExportExcel"
                  class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  <FileDown class="w-4 h-4" />
                  Export: Excel
                </button>
                <button 
                  @click="handleExportPDF"
                  class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  <FileDown class="w-4 h-4" />
                  Export: PDF
                </button>
                <button 
                  @click="handleExportCSV"
                  class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  <FileDown class="w-4 h-4" />
                  Export: CSV
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="px-[10px] pb-[10px]">
          <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden"> 
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left table-fixed">
                <thead class="text-xs text-foreground bg-muted/50 border-b border-border">
                  <tr>
                    <th scope="col" class="px-4 py-4 w-[50px] text-center no-print">
                      <input 
                        type="checkbox" 
                        @change="toggleSelectAll"
                        :checked="isAllSelected"
                        class="rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer"
                      />
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[180px]">
                      <div @click="toggleSort('code')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Kode
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[150px]">
                      <div @click="toggleSort('category')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Kategori
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[150px]">
                      <div @click="toggleSort('subcategory')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Subkategori
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[120px]">
                      <div @click="toggleSort('brand')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Merek
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[250px]">
                      <div @click="toggleSort('specification')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Spesifikasi
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[180px]">
                      <div @click="toggleSort('lastUpdate')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Pembaruan Terakhir
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold w-[100px]">
                      <div @click="toggleSort('amount')" class="flex items-center gap-1.5 cursor-pointer hover:text-primary transition-colors select-none">
                        Jumlah
                        <ArrowUpDown class="w-3.5 h-3.5 text-muted-foreground no-print" />
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-right w-[120px] no-print">
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
                    <td class="px-4 py-4 text-center no-print">
                      <input 
                        type="checkbox" 
                        :checked="selectedItems.includes(item.id)"
                        @change="toggleSelectItem(item.id)"
                        class="rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer"
                      />
                    </td>
                    <td class="px-6 py-4 text-muted-foreground font-mono text-xs truncate">
                      {{ item.code }}
                    </td>
                    <td class="px-6 py-4 text-foreground truncate">
                      {{ item.category }}
                    </td>
                    <td class="px-6 py-4 text-foreground truncate">
                      {{ item.subcategory }}
                    </td>
                    <td class="px-6 py-4 text-foreground truncate">
                      {{ item.brand }}
                    </td>
                    <td class="px-6 py-4 text-muted-foreground truncate" :title="item.specification">
                      {{ item.specification }}
                    </td>
                    <td class="px-6 py-4 text-muted-foreground truncate">
                      {{ item.lastUpdate }}
                    </td>
                    <td class="px-6 py-4 font-medium text-foreground">
                      {{ item.amount }}
                    </td>
                    <td class="px-6 py-4 no-print">
                      <div class="flex items-center justify-end gap-2">
                        <button class="p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50">
                          <Eye class="w-3.5 h-3.5" />
                        </button>
                        <button class="p-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50">
                          <Trash2 class="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer Pagination -->
        <div class="p-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground no-print">
          <div>
            {{ selectedItems.length }} dari {{ displayData.length }} baris dipilih
          </div>
          
          <div class="flex items-center gap-1">
            <button class="flex items-center gap-1 px-2 py-1 hover:text-foreground transition-colors disabled:opacity-50">
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
@media print {
  /* Hide browser headers/footers (Title/URL) */
  @page {
    size: landscape;
    margin: 0;
  }

  body {
    padding: 1.5cm !important;
    background: white !important;
  }

  /* Hide unnecessary elements */
  :deep(aside), 
  :deep(header),
  :deep(nav),
  .no-print,
  .p-5,
  .p-4,
  th.no-print, td.no-print,
  th:first-child, td:first-child, /* Checkbox */
  th:last-child, td:last-child, /* Actions */
  .lucide,
  svg
  {
    display: none !important;
  }

  /* Reset layout and borders */
  .bg-card {
    border: none !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    border-radius: 0 !important;
  }

  .bg-card div {
    border-radius: 0 !important;
  }

  /* Table styling for print */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 9px !important;
    table-layout: fixed !important;
  }

  th, td {
    border: 1px solid #000 !important;
    padding: 4px 2px !important;
    word-break: break-all !important;
    border-radius: 0 !important;
  }

  th {
    background-color: #f1f5f9 !important;
    -webkit-print-color-adjust: exact;
    color: black !important;
  }

  .truncate {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: clip !important;
  }

  /* Remove wrappers padding */
  .px-\[10px\], .pb-\[10px\] {
    padding: 0 !important;
    margin: 0 !important;
  }
}
</style>
