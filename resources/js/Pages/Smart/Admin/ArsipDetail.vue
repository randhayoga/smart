<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2,
  Check,
  Clock
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  requestId: string | number;
}

const props = defineProps<Props>();

// Mock data for archive items
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
  },
  {
    id: 3,
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
  { status: 'Permintaan dibuat', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Di-approve', user: 'John Doe', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Dikonfirmasi', user: 'Radifa', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Serah Terima', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Aset selesai dipinjam', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Pengembalian aset', info: 'dikonfirmasi oleh Radifa', time: 'DD-MM-YYYY HH:MM', completed: true },
  { status: 'Selesai', time: 'DD-MM-YYYY HH:MM', completed: true },
];

</script>

<template>
  <AppLayout title="Detail Arsip">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/arsip">Arsip</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ requestId || '#Request_ID' }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan #{{ requestId || 'Request_ID' }}</h1>
      <p class="text-sm text-muted-foreground">Permintaan dibuat pada DD-MM-YYYY</p>
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
                <div class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card">
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 class="text-sm font-bold text-green-600">
                    {{ step.status }}
                  </h4>
                  <p v-if="step.user" class="text-xs font-semibold text-green-600 mt-0.5">
                    oleh {{ step.user }}
                  </p>
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  <p v-if="step.info" class="text-xs text-green-600/80 font-medium mt-0.5">
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
