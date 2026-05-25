<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  AlertCircle,
  Check
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  requestId: string | number;
}

const props = defineProps<Props>();

// Mock data for new request items
const items = ref([
  {
    id: 1,
    brand: 'Merek Spek',
    category: 'Kategori',
    subcategory: 'Subkategori',
    quantity: 'XX',
    assets: [
      'XXXX-ABC-DE-ORG-PTRE-XX',
      'XXXX-ABC-DE-ORG-PTRE-XX',
      'XXXX-ABC-DE-ORG-PTRE-XX',
      'XXXX-ABC-DE-ORG-PTRE-XX',
      'XXXX-ABC-DE-ORG-PTRE-XX',
    ]
  },
  {
    id: 2,
    brand: 'Merek Spek',
    category: 'Kategori',
    subcategory: 'Subkategori',
    quantity: 'XX',
    assets: [
       'XXXX-ABC-DE-ORG-PTRE-XX',
    ]
  }
]);

const timeline = [
  { status: 'Permintaan dibuat', user: 'John Doe', time: 'DD/MM/YYYY HH:MM', completed: true },
  { status: 'Di-approve', user: 'Jane Doe', time: 'DD/MM/YYYY HH:MM', completed: true },
  { 
    status: 'Perlu konfirmasi Anda!', 
    active: true,
    isAction: true 
  },
];

const handleApprove = () => alert('Request Approved!');
const handleReject = () => alert('Request Rejected!');
const handleAturSerahTerima = () => alert('Atur Serah Terima diklik!');
const handlePilihAlokasi = (item: any) => alert(`Pilih Alokasi Aset untuk ${item.brand}`);
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inbox">Inbox</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ requestId || '#Request_ID' }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan</h1>
    </div>

    <!-- Info Banner -->
    <div class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-white flex items-center justify-between gap-3 text-indigo-600">
      <p class="text-sm font-semibold">
        Tolong pastikan bahwa alokasi aset sudah sesuai dan konfirmasi permintaan/peminjaman ini
      </p>
      <button 
        @click="handleAturSerahTerima"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
      >
        Atur Serah Terima
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
                <span class="font-semibold"> John Doe</span>
              </p>
              <p>
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold"> Jane Doe</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold"> DD/MM/YYYY HH:MM</span>
              </p>
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">
                  Jenis_Pemanfaatan (Nomor_Project/Nama_Departement)
                </span>
              </p>
              <p>
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">
                  DD/MM/YYYY HH:MM s.d. DD/MM/YYYY HH:MM (X hari, Y jam)
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
                @click="handlePilihAlokasi(item)"
                class="px-5 py-2.5 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Pilih Alokasi Aset
              </button>
            </template>
          </AssetItemCard>
        </div>
      </div>

      <!-- Right Column (Timeline & Actions) -->
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
                
                <!-- Status Action Required / Active -->
                <div 
                  v-else
                  class="w-7 h-7 rounded-full border-2 border-[#6366F1] flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-[#6366F1]/20 opacity-40 animate-ping"></span>
                  <span class="text-sm font-extrabold text-[#6366F1]">!</span>
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 
                    class="text-sm font-bold"
                    :class="{
                      'text-green-600': step.completed,
                      'text-[#6366F1]': !step.completed
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
                  
                  <!-- Action Buttons inside timeline step -->
                  <div v-if="step.isAction" class="pt-3 flex gap-2">
                    <button 
                      @click="handleApprove"
                      class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Konfirmasi
                    </button>
                    <button 
                      @click="handleReject"
                      class="px-4 py-1.5 bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Tolak
                    </button>
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
