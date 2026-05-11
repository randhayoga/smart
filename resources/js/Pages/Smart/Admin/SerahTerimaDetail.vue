<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronUp,
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  handover: any;
}

const props = defineProps<Props>();

// Mock internal state for items
const items = ref([
  {
    id: 1,
    brand: 'Asus ROG',
    spec: 'Zephyrus G14',
    category: 'Elektronik',
    subcategory: 'Laptop',
    quantity: 5,
    assets: [
      '2024-LAP-01-ORG-PTRE-01',
      '2024-LAP-01-ORG-PTRE-02',
      '2024-LAP-01-ORG-PTRE-03',
      '2024-LAP-01-ORG-PTRE-04',
      '2024-LAP-01-ORG-PTRE-05',
    ],
    showAssets: false
  },
  {
    id: 2,
    brand: 'Logitech',
    spec: 'MX Master 3S',
    category: 'Elektronik',
    subcategory: 'Mouse',
    quantity: 2,
    assets: [
      '2024-MOU-01-ORG-PTRE-01',
      '2024-MOU-01-ORG-PTRE-02',
    ],
    showAssets: false
  }
]);

const timeline = [
  { status: 'Permintaan dibuat', time: '10-05-2026 09:00', completed: true },
  { status: 'Di-approve', user: 'John Doe', time: '10-05-2026 14:30', completed: true },
  { status: 'Dikonfirmasi', user: 'Radifa', time: '11-05-2026 10:00', completed: true },
  { 
    status: 'Serah Terima', 
    method: 'Ambil sendiri', 
    location: 'Ruang IFS', 
    time: '12-05-2026 10:00', 
    active: true 
  },
];
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/handover">Serah Terima</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ handover?.number || '#Request_ID' }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-3xl font-bold text-foreground">Detail Permintaan</h1>
    </div>

    <!-- Alert / Info Banner -->
    <div class="mb-6 p-4 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center gap-3 text-indigo-700">
      <Info class="w-5 h-5 shrink-0" />
      <p class="text-sm font-medium">
        Pengingat bahwa aset akan diambil pada <span class="font-bold">{{ handover?.time || 'DD-MM-YYYY jam HH:MM' }}</span>
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card rounded-2xl border border-border p-6 shadow-sm">
          <div class="space-y-4">
            <h2 class="text-xl font-bold text-foreground">{{ handover?.number || '#Nomor_Permintaan' }}</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-12 text-sm">
              <div class="space-y-1">
                <p class="text-muted-foreground">Dibuat oleh</p>
                <p class="font-semibold text-foreground">{{ handover?.requester || 'John Doe' }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">PIC Approval</p>
                <p class="font-semibold text-foreground">Jane Doe</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">Waktu dibuat</p>
                <p class="font-semibold text-foreground">10-05-2026 09:00</p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">Pemanfaatan</p>
                <p class="font-semibold text-foreground">Internal Project (PRJ-2024-001/Finance)</p>
              </div>
              <div class="md:col-span-2 space-y-1">
                <p class="text-muted-foreground">Durasi</p>
                <p class="font-semibold text-foreground">12-05-2026 10:00 s.d. 19-05-2026 10:00 (7 hari, 0 jam)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card rounded-2xl border border-border p-6 shadow-sm space-y-6">
          <h3 class="text-lg font-bold text-foreground">Daftar barang:</h3>
          
          <div v-for="item in items" :key="item.id" class="p-5 rounded-xl border border-border bg-muted/5 space-y-4">
            <div class="flex items-start gap-4">
              <div class="w-24 h-24 rounded-lg bg-muted flex items-center justify-center overflow-hidden shrink-0 border border-border">
                <img src="https://placehold.co/100x100?text=Item" class="w-full h-full object-cover" />
              </div>
              
              <div class="flex-grow space-y-1">
                <h4 class="text-lg font-bold text-foreground">{{ item.brand }} {{ item.spec }}</h4>
                <p class="text-sm text-muted-foreground">{{ item.category }} ({{ item.subcategory }})</p>
                <p class="text-sm font-medium text-foreground">Jumlah dipinjam: {{ item.quantity }} satuan</p>
                
                <div class="pt-2">
                  <button 
                    @click="item.showAssets = !item.showAssets"
                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition-colors"
                  >
                    {{ item.showAssets ? 'Sembunyikan Alokasi Aset' : 'Lihat Alokasi Aset' }}
                    <ChevronUp v-if="item.showAssets" class="w-4 h-4" />
                    <ChevronDown v-else class="w-4 h-4" />
                  </button>
                  
                  <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                  >
                    <div v-if="item.showAssets" class="mt-3 p-3 bg-muted/30 rounded-lg space-y-1 border border-border">
                      <p class="text-xs font-bold text-muted-foreground mb-2">Aset:</p>
                      <div v-for="asset in item.assets" :key="asset" class="text-xs font-mono text-foreground flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                        {{ asset }}
                      </div>
                    </div>
                  </Transition>
                </div>
              </div>

              <div class="self-end">
                <button class="px-5 py-2 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-semibold rounded-[14px] transition-all shadow-sm">
                  Pilih Alokasi Aset
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card rounded-2xl border border-border p-6 shadow-sm">
          <h3 class="text-lg font-bold text-foreground mb-6">Tahapan:</h3>
          
          <div class="relative space-y-8 pl-4">
            <!-- Vertical Line -->
            <div class="absolute left-7 top-2 bottom-8 w-0.5 bg-border"></div>

            <div v-for="(step, index) in timeline" :key="index" class="relative flex items-start gap-4">
              <!-- Icon Container -->
              <div class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-card">
                <CheckCircle2 v-if="step.completed" class="w-8 h-8 text-green-500" />
                <div v-else-if="step.active" class="w-8 h-8 rounded-full border-2 border-indigo-600 flex items-center justify-center bg-indigo-50">
                   <div class="w-2.5 h-2.5 rounded-full bg-indigo-600 animate-pulse"></div>
                </div>
                <div v-else class="w-8 h-8 rounded-full border-2 border-border bg-muted flex items-center justify-center">
                  <Clock class="w-4 h-4 text-muted-foreground" />
                </div>
              </div>

              <div class="space-y-1">
                <p class="font-bold text-sm" :class="step.completed ? 'text-green-600' : step.active ? 'text-indigo-600' : 'text-muted-foreground'">
                  {{ step.status }}
                </p>
                <div v-if="step.user" class="text-xs text-foreground font-medium">Oleh {{ step.user }}</div>
                <div v-if="step.time" class="text-xs text-muted-foreground">{{ step.time }}</div>
                
                <div v-if="step.active" class="mt-2 space-y-1">
                  <p class="text-xs font-semibold text-indigo-700">Metode: {{ step.method }}</p>
                  <p class="text-xs font-semibold text-indigo-700">Tempat: {{ step.location }}</p>
                  <p class="text-xs font-semibold text-indigo-700">Waktu: {{ step.time }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-8">
            <button class="w-full py-2.5 bg-[#D9534F] hover:bg-[#C9302C] text-white font-bold rounded-[14px] transition-all shadow-sm active:scale-[0.98]">
              Batalkan
            </button>
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
