<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  Breadcrumb,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import Combobox from '@/Components/Combobox.vue';
import { CheckCircle2, Calendar, Clock, ChevronDown, Check } from 'lucide-vue-next';

// ─────────────────────────────────────────────
// Props (diisi dari Inertia)
// ─────────────────────────────────────────────
interface CartItem {
  id: number;
  barang_id?: number | null;
  brand: string;
  name?: string;
  spec: string;
  category: string;
  subcategory: string;
  stock: number;
  quantity: number;
  imageUrl?: string;
  uom?: string;
}

interface Option {
  value: string;
  label: string;
}

interface Props {
  user?: any;
  selectedItems?: CartItem[];
  departments?: Option[];
  projects?: Option[];
  defaultStartDate?: string;
  defaultStartTime?: string;
  defaultEndDate?: string;
  defaultEndTime?: string;
}

const props = withDefaults(defineProps<Props>(), {
  selectedItems: () => [],
  departments: () => [],
  projects: () => [],
  defaultStartDate: '',
  defaultStartTime: '',
  defaultEndDate: '',
  defaultEndTime: '',
});

const pemanfaatanOptions = [
  { value: 'corporate', label: 'Corporate' },
  { value: 'project',   label: 'Project' },
];

const departemenOptions = computed(() => props.departments);
const projectOptions = computed(() => {
  return props.projects.map(p => ({
    id: p.value,
    name: p.label,
  }));
});

const selectedPemanfaatanLabel = computed(() => {
  const found = pemanfaatanOptions.find(opt => opt.value === pemanfaatan.value);
  return found ? found.label : 'Pilih Pemanfaatan';
});

const selectedDepartemenLabel = computed(() => {
  if (!departemen.value) return 'Pilih Departemen';
  const found = props.departments.find(opt => opt.value == departemen.value);
  return found ? found.label : 'Pilih Departemen';
});

// ─────────────────────────────────────────────
// State Form
// ─────────────────────────────────────────────
const pemanfaatan = ref('corporate');
const departemen  = ref('');
const project     = ref('');
const alasan      = ref('');

/** Project / Departemen kondisional */
const isCorporateRequired = computed(() => pemanfaatan.value === 'corporate');
const isProjectRequired   = computed(() => pemanfaatan.value === 'project');

// Reset selection when changing type
watch(pemanfaatan, (newVal) => {
  if (newVal === 'corporate') {
    project.value = '';
  } else if (newVal === 'project') {
    departemen.value = '';
  }
});

/** Validasi: semua field wajib terisi */
const isFormValid = computed(() => {
  if (!pemanfaatan.value) return false;
  if (isCorporateRequired.value && !departemen.value) return false;
  if (isProjectRequired.value && !project.value) return false;
  if (!alasan.value.trim()) return false;
  return true;
});

// ─────────────────────────────────────────────
// State sukses (modal / overlay setelah submit)
// ─────────────────────────────────────────────
const isSubmitted  = ref(false);
const isSubmitting = ref(false);

const isBorrow = computed(() => !!props.defaultStartDate);

const pageTitle = computed(() => isBorrow.value ? 'Konfirmasi Peminjaman' : 'Konfirmasi Permintaan');

const totalQuantity = computed(() => {
  return props.selectedItems.reduce((acc, item) => acc + (Number(item.quantity) || 0), 0);
});

const formatDisplayDate = (dateStr: string) => {
  if (!dateStr) return '-';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return dateStr;
};

// ─────────────────────────────────────────────
// Aksi: Konfirmasi dan Minta Approval
// ─────────────────────────────────────────────
const handleConfirm = () => {
  if (!isFormValid.value || isSubmitting.value) return;

  isSubmitting.value = true;

  const routeName = isBorrow.value 
    ? 'smart.borrow-cart.confirmation.store' 
    : 'smart.asset-cart.confirmation.store';

  const payload: any = {
    items: props.selectedItems,
    pemanfaatan: pemanfaatan.value,
    departemen: departemen.value ? departemen.value : null,
    project: project.value ? project.value : null,
    alasan: alasan.value,
  };

  if (isBorrow.value) {
    payload.start_date = `${props.defaultStartDate} ${props.defaultStartTime || '08:00'}`;
    payload.end_date = props.defaultEndDate && props.defaultEndTime 
      ? `${props.defaultEndDate} ${props.defaultEndTime}` 
      : (props.defaultEndDate ? `${props.defaultEndDate} 17:00` : null);
  }

  router.post(route(routeName), payload, {
    onSuccess: () => {
      isSubmitting.value = false;
      isSubmitted.value  = true;
    },
    onError: () => {
      isSubmitting.value = false;
    }
  });
};

/** Kembali ke keranjang */
const handleBack = () => {
  if (isBorrow.value) {
    router.visit(route('smart.borrow-cart'));
  } else {
    router.visit(route('smart.asset-cart'));
  }
};

/** Setelah sukses → pergi ke dashboard atau riwayat permintaan */
const handleGoToHistory = () => {
  router.visit(route('smart.user.dashboard'));
};
</script>

<template>
  <Head :title="pageTitle" />

  <AppLayout :title="pageTitle">
    <!-- Breadcrumb -->
    <Breadcrumb class="mb-2">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink :href="isBorrow ? route('smart.borrow-cart') : route('smart.asset-cart')">
            {{ isBorrow ? 'Keranjang Peminjaman' : 'Keranjang Habis Pakai' }}
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground font-medium">Konfirmasi</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Title -->
    <div class="mb-2 flex flex-row items-center justify-between sm:flex-col sm:items-start">
      <div class="min-w-0">
        <h1 class="text-lg font-bold text-gray-900 mt-2 leading-none">{{ pageTitle }}</h1>
        <p class="text-sm text-muted-foreground mt-2 hidden sm:block">Periksa kembali daftar barang dan lengkapi detail sebelum mengajukan.</p>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row sm:gap-6 mt-3 pb-20 lg:pb-0">
      <!-- ============================================================ -->
      <!-- Left Column: Dates (if borrow) + Selected Items List         -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0 space-y-4">
        <!-- === Borrow Date Block (If borrowing) === -->
        <div v-if="isBorrow" class="bg-card border border-border rounded-[0.875rem] p-4 sm:p-5">
          <h2 class="text-base font-bold text-foreground flex items-center gap-2 mb-3">
            <Calendar class="w-4 h-4 text-primary" />
            Jadwal Peminjaman
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Start Schedule -->
            <div class="p-3 rounded-[0.875rem] bg-muted/40 border border-border flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                <Clock class="w-5 h-5 text-primary" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs text-muted-foreground font-medium">Mulai Peminjaman</p>
                <p class="text-sm font-bold text-foreground truncate">
                  {{ formatDisplayDate(props.defaultStartDate) }} <span class="font-normal text-muted-foreground">•</span> {{ props.defaultStartTime || '08:00' }}
                </p>
              </div>
            </div>

            <!-- End Schedule -->
            <div class="p-3 rounded-[0.875rem] bg-muted/40 border border-border flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center shrink-0">
                <Clock class="w-5 h-5 text-muted-foreground" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs text-muted-foreground font-medium">Selesai Peminjaman</p>
                <p class="text-sm font-bold text-foreground truncate">
                  <template v-if="props.defaultEndDate">
                    {{ formatDisplayDate(props.defaultEndDate) }} <span class="font-normal text-muted-foreground">•</span> {{ props.defaultEndTime || '17:00' }}
                  </template>
                  <template v-else>
                    <span class="text-muted-foreground font-normal italic">Tidak ditentukan</span>
                  </template>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Items ScrollArea Container -->
        <ScrollArea 
          class="border border-border rounded-[0.875rem] bg-card"
          :class="isBorrow ? 'h-[calc(100vh-23.5rem)] sm:h-[calc(100vh-24rem)] lg:h-[calc(100vh-23.5rem)]' : 'h-[calc(100vh-14.5rem)] sm:h-[calc(100vh-15rem)] lg:h-[calc(100vh-15rem)]'"
        >
          <div class="p-3 sm:p-5">
            <div class="space-y-3">
              <!-- Message if empty -->
              <div v-if="props.selectedItems.length === 0" class="text-center py-10">
                <p class="text-muted-foreground text-sm">Tidak ada barang yang dipilih.</p>
              </div>

              <!-- Item Card in Confirmation -->
              <div
                v-for="item in props.selectedItems"
                :key="item.id"
                class="bg-card border border-border rounded-[0.875rem] p-3 sm:p-4 flex items-center justify-between gap-3 sm:gap-4 shadow-card hover:shadow-card-hover transition-all duration-300"
              >
                <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                  <!-- Image -->
                  <div class="w-16 h-16 sm:w-20 sm:h-20 shrink-0 bg-muted rounded-[0.875rem] overflow-hidden flex items-center justify-center border border-border relative">
                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
                    <img 
                      v-if="item.imageUrl" 
                      :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/media/' + item.imageUrl" 
                      :alt="item.name || item.subcategory" 
                      class="w-full h-full object-cover relative z-10" 
                    />
                    <img 
                      v-else 
                      src="https://placehold.co/400x400?text=Barang" 
                      :alt="item.name || item.subcategory" 
                      class="w-full h-full object-cover opacity-50" 
                    />
                  </div>

                  <!-- Info -->
                  <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <template v-if="!item.barang_id">
                      <h3 class="text-sm sm:text-base font-bold text-foreground leading-snug truncate">{{ item.subcategory }}</h3>
                      <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">{{ item.category }}</p>
                      <p class="text-[10px] sm:text-xs text-muted-foreground italic hidden sm:block">*foto hanya ilustrasi</p>
                    </template>
                    <template v-else>
                      <span v-if="item.brand && item.brand !== '-'" class="text-xs sm:text-sm font-bold text-foreground leading-snug truncate">
                        {{ item.brand }}
                      </span>
                      <h3 class="text-sm sm:text-base font-bold text-foreground leading-snug truncate">
                        {{ item.name }}{{ item.spec && item.spec !== '-' ? ' ' + item.spec : '' }}
                      </h3>
                      <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">
                        {{ item.category }} ({{ item.subcategory }})
                      </p>
                    </template>
                  </div>
                </div>

                <!-- Quantity badge -->
                <div class="shrink-0 text-right">
                  <span class="text-[11px] sm:text-xs text-muted-foreground block mb-0.5">Jumlah:</span>
                  <div class="text-xs sm:text-sm font-bold text-foreground bg-muted/60 px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-[0.625rem] border border-border whitespace-nowrap">
                    {{ item.quantity }} {{ item.uom || 'satuan' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Right Column: Detail Form & Summary (sticky)                -->
      <!-- ============================================================ -->
      <div class="w-full lg:w-96 xl:w-[28rem] 2xl:w-[30rem] flex-shrink-0">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Detail {{ isBorrow ? 'Peminjaman' : 'Permintaan' }}</h2>

          <div class="space-y-4">
            <!-- Pemanfaatan -->
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-foreground">
                Pemanfaatan<span class="text-destructive">*</span>
              </label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    class="w-full h-10 px-3 justify-between rounded-[0.875rem] font-normal text-sm border-input hover:bg-muted"
                  >
                    <span class="truncate" :class="!pemanfaatan ? 'text-muted-foreground' : 'text-foreground'">
                      {{ selectedPemanfaatanLabel }}
                    </span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[0.875rem] p-1 z-[10000]" align="start">
                  <DropdownMenuItem
                    v-for="opt in pemanfaatanOptions"
                    :key="opt.value"
                    class="rounded-[0.5rem] cursor-pointer flex items-center justify-between"
                    @select="pemanfaatan = opt.value"
                  >
                    <span>{{ opt.label }}</span>
                    <Check v-if="pemanfaatan === opt.value" class="h-4 w-4 text-primary" />
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Departemen (if corporate) -->
            <div v-if="isCorporateRequired" class="space-y-1.5">
              <label class="text-sm font-medium text-foreground">
                Departemen<span class="text-destructive">*</span>
              </label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    class="w-full h-10 px-3 justify-between rounded-[0.875rem] font-normal text-sm border-input hover:bg-muted"
                  >
                    <span class="truncate" :class="!departemen ? 'text-muted-foreground' : 'text-foreground'">
                      {{ selectedDepartemenLabel }}
                    </span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) max-h-60 overflow-y-auto rounded-[0.875rem] p-1 z-[10000]" align="start">
                  <DropdownMenuItem
                    v-for="opt in departemenOptions"
                    :key="opt.value"
                    class="rounded-[0.5rem] cursor-pointer flex items-center justify-between"
                    @select="departemen = opt.value"
                  >
                    <span class="truncate">{{ opt.label }}</span>
                    <Check v-if="departemen == opt.value" class="h-4 w-4 text-primary shrink-0 ml-2" />
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Project (if project) -->
            <div v-if="isProjectRequired" class="space-y-1.5">
              <label class="text-sm font-medium text-foreground">
                Project<span class="text-destructive">*</span>
              </label>
              <Combobox
                v-model="project"
                :options="projectOptions"
                placeholder="Pilih Project"
                default-label="Pilih Project"
                search-placeholder="Cari nama project..."
                empty-text="Project tidak ditemukan."
                width-class="w-full h-10 px-3 rounded-[0.875rem] text-sm"
              />
            </div>

            <!-- Alasan -->
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-foreground">
                Alasan {{ isBorrow ? 'peminjaman' : 'permintaan' }}<span class="text-destructive">*</span>
              </label>
              <textarea
                v-model="alasan"
                rows="4"
                :placeholder="`Ketik alasan ${isBorrow ? 'peminjaman' : 'permintaan'} di sini...`"
                class="w-full p-3 text-sm border border-input rounded-[0.875rem] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none placeholder:text-muted-foreground"
              ></textarea>
            </div>

            <p class="text-xs text-destructive italic">*Wajib diisi</p>

            <hr class="border-border" />

            <!-- Summary counts -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Total jenis:</span>
                <span class="font-semibold text-foreground">{{ props.selectedItems.length }} jenis</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Total kuantitas:</span>
                <span class="font-semibold text-foreground">{{ totalQuantity }}</span>
              </div>
            </div>

            <!-- Submit Button Desktop -->
            <Button
              variant="primary"
              size="lg"
              class="w-full"
              :disabled="!isFormValid || isSubmitting"
              @click="handleConfirm"
            >
              <span v-if="isSubmitting">Memproses...</span>
              <span v-else>Konfirmasi dan Minta Approval</span>
            </Button>
          </div>
        </div>
      </div>

      <!-- Mobile Sticky Bottom Footer -->
      <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-card border-t border-border px-4 py-3 shadow-lg flex items-center justify-between pb-safe">
        <div class="flex flex-col">
          <span class="text-xs text-muted-foreground font-medium">Total:</span>
          <span class="text-sm font-bold text-foreground">
            {{ props.selectedItems.length }} jenis ({{ totalQuantity }} item)
          </span>
        </div>
        <Button
          variant="primary"
          size="default"
          class="px-6 rounded-xl"
          :disabled="!isFormValid || isSubmitting"
          @click="handleConfirm"
        >
          {{ isSubmitting ? 'Memproses...' : 'Konfirmasi' }}
        </Button>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- Modal Sukses                                                 -->
    <!-- ============================================================ -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isSubmitted"
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
          <div
            class="bg-card text-foreground rounded-[0.875rem] border border-border shadow-2xl w-full max-w-md p-6 sm:p-8 flex flex-col items-center text-center gap-4"
            @click.stop
          >
            <!-- Ikon sukses -->
            <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-950/50 flex items-center justify-center">
              <CheckCircle2 class="w-9 h-9 text-green-600 dark:text-green-400" />
            </div>

            <div class="space-y-1">
              <h3 class="text-lg sm:text-xl font-bold text-foreground">{{ isBorrow ? 'Peminjaman' : 'Permintaan' }} Terkirim!</h3>
              <p class="text-sm text-muted-foreground">
                {{ isBorrow ? 'Peminjaman' : 'Permintaan' }} Anda telah berhasil dikirimkan dan sedang menunggu approval.
                Anda akan mendapat notifikasi ketika permintaan diproses.
              </p>
            </div>

            <!-- Tombol aksi -->
            <div class="w-full pt-2">
              <Button
                variant="primary"
                class="w-full rounded-[0.875rem] h-10 text-sm font-semibold"
                @click="handleGoToHistory"
              >
                Ke Dashboard
              </Button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
