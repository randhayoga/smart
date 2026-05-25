<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronUp,
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  AlertCircle,
  Check
} from 'lucide-vue-next';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  returnId: string | number;
}

const props = defineProps<Props>();

// Mock internal state for items
const items = ref([
  {
    id: 1,
    brand: 'Merek Spek',
    category: 'Kategori',
    subcategory: 'Subkategori',
    quantity: 'XX',
    assets: [
      'XXXX-ABC-DE-ORG-PTRE-XX (Tempat Penempatan)',
      'XXXX-ABC-DE-ORG-PTRE-XX (Mega Mendung)',
      'XXXX-ABC-DE-ORG-PTRE-XX (Mega Mendung)',
      'XXXX-ABC-DE-ORG-PTRE-XX (Tiga Negeri)',
      'XXXX-ABC-DE-ORG-PTRE-XX (Tiga Negeri)',
    ]
  },
  {
    id: 2,
    brand: 'Merek Spek',
    category: 'Kategori',
    subcategory: 'Subkategori',
    quantity: 'XX',
    assets: [
       'XXXX-ABC-DE-ORG-PTRE-XX (Tempat Penempatan)',
    ]
  },
  {
    id: 3,
    brand: 'Merek Spek',
    category: 'Kategori',
    subcategory: 'Subkategori',
    quantity: 'XX',
    assets: [
       'XXXX-ABC-DE-ORG-PTRE-XX (Tempat Penempatan)',
    ]
  }
]);

const timeline = [
  { status: 'Permintaan dibuat', time: 'DD/MM/YYYY HH:MM', completed: true },
  { status: 'Di-approve', user: 'John Doe', time: 'DD/MM/YYYY HH:MM', completed: true },
  { status: 'Dikonfirmasi', user: 'Radifa', time: 'DD/MM/YYYY HH:MM', completed: true },
  { status: 'Serah Terima', time: 'DD/MM/YYYY HH:MM', completed: true },
  { status: 'Aset selesai dipinjam', time: 'DD/MM/YYYY HH:MM', completed: true },
  { 
    status: 'Pengembalian aset', 
    method: 'Dikembalikan sendiri',
    location: 'Ruang IFS',
    time: 'DD/MM/YYYY HH:MM', 
    active: true,
    isFinal: true
  },
];

const handleConfirmReturn = () => {
  alert('Konfirmasi Pengembalian Berhasil!');
};
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/returns">Pengembalian</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">#Request_ID</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan #Request_ID</h1>
      <p class="text-sm text-muted-foreground">Permintaan dibuat pada DD/MM/YYYY</p>
    </div>

    <!-- Info Banner -->
    <div class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center justify-between gap-3 text-indigo-700">
      <p class="text-sm font-semibold">
        Tolong kembalikan semua aset pada <span class="font-bold">DD/MM/YYYY jam HH:MM di Ruang IFS</span>
      </p>
      <button 
        @click="handleConfirmReturn"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
      >
        Konfirmasi Pengembalian
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-sm font-medium text-muted-foreground mb-3">Detail:</h3>
          <div class="space-y-2">
            <h2 class="text-lg md:text-xl font-extrabold text-foreground mb-3">
              #Nomor_Permintaan/#Nomor_Peminjaman
            </h2>
            
            <div class="space-y-1.5 text-sm text-foreground">
              <p>
                <span class="text-muted-foreground">Dibuat oleh:</span> 
                <span class="font-semibold">John Doe</span>
              </p>
              <p>
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold">Jane Doe</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold">DD/MM/YYYY HH:MM</span>
              </p>
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">Jenis_Pemanfaatan (Nomor_Project/Nama_Departement)</span>
              </p>
              <p>
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">DD/MM/YYYY HH:MM s.d. DD/MM/YYYY HH:MM (X hari, Y jam)</span>
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
            :brand="item.brand"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
          />
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
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  
                  <div v-if="step.active" class="space-y-0.5 mt-2 text-xs text-indigo-600">
                    <p class="font-semibold">Metode: {{ step.method }}</p>
                    <p class="font-semibold">Tempat: {{ step.location }}</p>
                    <p class="font-semibold">Waktu: {{ step.time }}</p>
                    
                    <div class="pt-3">
                      <button 
                        @click="handleConfirmReturn"
                        class="bg-[#6366F1] hover:bg-[#5558EB] text-white text-xs font-bold px-4 py-2 rounded-lg transition-all shadow-sm"
                      >
                        Konfirmasi Pengembalian
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
