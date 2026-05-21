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
  borrowedId: string | number;
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
  }
]);

const timeline = [
  { status: 'Permintaan dibuat', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Di-approve', user: 'John Doe', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Dikonfirmasi', user: 'Radifa', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Serah Terima', time: 'DD-MM-YYYY HH:MM', completed: true },
  { 
    status: 'Aset sedang dipinjam', 
    info: 'Tenggat pada DD-MM-YYYY HH:MM', 
    active: true,
    isFinal: true
  },
];

const handleCatatPenempatan = (item: any) => {
  alert('Fitur Catat Penempatan Aset untuk ' + item.brand);
};
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/borrowed">Lacak Peminjaman</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">#Request_ID</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-4">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan</h1>
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
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">
                  Jenis_Pemanfaatan (Nomor_Project/Nama_Departement)
                </span>
              </p>

              <p>
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">
                  DD-MM-YYYY HH:MM s.d. DD-MM-YYYY HH:MM (X hari, Y jam)
                </span>
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
          >
            <template #footer>
              <button 
                @click="handleCatatPenempatan(item)"
                class="px-5 py-2.5 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Catat Penempatan Aset
              </button>
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
                
                <!-- Status Active / Current (Pulsing Blue Clock) -->
                <div 
                  v-else-if="step.active" 
                  class="w-7 h-7 rounded-full border-2 border-blue-500 flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-30 animate-ping"></span>
                  <Clock class="w-4 h-4 text-blue-500" />
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
                      'text-blue-600': step.active && !step.completed,
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
                  <p v-if="step.info" class="text-xs text-indigo-600 font-medium mt-0.5">
                    {{ step.info }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
