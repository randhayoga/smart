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
  AlertCircle
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
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <div class="space-y-4">
            <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Detail:</h3>
            <div class="space-y-1">
                <h2 class="text-lg font-bold text-foreground">#Nomor_Permintaan/#Nomor_Peminjaman</h2>
                <p class="text-sm text-foreground">
                    <span class="text-muted-foreground font-normal">Pemanfaatan: </span>
                    Jenis_Pemanfaatan (Nomor_Project/Nama_Departement)
                </p>
                <p class="text-sm text-foreground">
                    <span class="text-muted-foreground font-normal">Durasi: </span>
                    DD-MM-YYYY HH:MM s.d. DD-MM-YYYY HH:MM (X hari, Y jam)
                </p>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm space-y-6">
          <h3 class="text-lg font-bold text-foreground">Daftar barang:</h3>
          
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
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <h3 class="text-lg font-bold text-foreground mb-8">Tahapan:</h3>
          
          <div class="space-y-4">
            <div v-for="(step, index) in timeline" :key="index">
              <!-- Step Content -->
              <div class="flex items-start gap-4">
                <div class="flex flex-col items-center">
                  <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full bg-card border-2"
                    :class="[
                        step.completed ? 'border-green-500' : 'border-indigo-600'
                    ]"
                  >
                    <CheckCircle2 v-if="step.completed" class="w-6 h-6 text-green-500" />
                    <AlertCircle v-else class="w-6 h-6 text-indigo-600" />
                  </div>
                </div>

                <div class="space-y-0.5 pt-0.5 flex-grow">
                  <p class="font-bold text-base leading-tight" :class="step.completed ? 'text-green-600' : 'text-indigo-600'">
                    {{ step.status }}
                  </p>
                  <p v-if="step.user" class="text-sm text-green-600/80 font-medium leading-tight">oleh {{ step.user }}</p>
                  <p v-if="step.time" class="text-sm text-muted-foreground leading-tight">{{ step.time }}</p>
                  <p v-if="step.info" class="text-sm text-indigo-600 font-medium leading-tight">{{ step.info }}</p>
                </div>
              </div>

              <!-- Connecting Arrow -->
              <div v-if="index < timeline.length - 1" class="flex justify-start pl-4 py-1">
                <div class="w-2.5 h-6 ml-0.5">
                  <svg width="12" height="24" viewBox="0 0 12 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 0V20M6 20L1 15M6 20L11 15" 
                      :stroke="index < 3 ? '#22C55E' : '#6366F1'" 
                      stroke-width="2" 
                      stroke-linecap="round" 
                      stroke-linejoin="round"
                    />
                  </svg>
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
