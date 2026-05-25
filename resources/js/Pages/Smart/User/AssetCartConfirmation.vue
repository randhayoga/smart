<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Breadcrumb,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { CheckCircle2 } from 'lucide-vue-next';

// ─────────────────────────────────────────────
// ─────────────────────────────────────────────
// Props (diisi dari Inertia)
// ─────────────────────────────────────────────
interface CartItem {
  id: number;
  brand: string;
  spec: string;
  category: string;
  subcategory: string;
  stock: number;
  quantity: number;
  imageUrl?: string;
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
const projectOptions = computed(() => props.projects);

// ─────────────────────────────────────────────
// State Form
// ─────────────────────────────────────────────
import { watch } from 'vue';

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

// ─────────────────────────────────────────────
// Aksi: Konfirmasi dan Minta Approval
// ─────────────────────────────────────────────
const handleConfirm = () => {
  if (!isFormValid.value) return;

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
  <AppLayout title="Konfirmasi Permintaan">

    <!-- ── Breadcrumb ─────────────────────────────── -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/asset-cart">Keranjang Habis Pakai</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">Konfirmasi</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- ── Judul Halaman ──────────────────────────── -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-foreground">Konfirmasi Permintaan</h1>
    </div>

    <!-- ── Layout Dua Kolom ───────────────────────── -->
    <div class="flex flex-col lg:flex-row gap-6">

      <!-- ============================================================
           Kolom Kiri: Daftar Barang yang Dipilih
           ============================================================ -->
      <div class="flex-1 min-w-0">
        <div class="bg-card border border-border rounded-[14px] p-5">
          <h2 class="text-sm font-bold text-foreground mb-4">Daftar barang:</h2>

          <!-- Daftar item ─ scroll jika panjang -->
          <div class="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
            <div
              v-for="item in props.selectedItems"
              :key="item.id"
              class="flex items-center gap-4 p-4 rounded-[14px] border border-border bg-muted/5"
            >
              <!-- Gambar -->
              <div class="w-16 h-16 flex-shrink-0 bg-muted rounded-[14px] overflow-hidden border border-border">
                <img
                  v-if="item.imageUrl"
                  :src="item.imageUrl"
                  :alt="`${item.brand} ${item.spec}`"
                  class="w-full h-full object-cover"
                />
                <img
                  v-else
                  src="https://placehold.co/200x200?text=Barang"
                  :alt="`${item.brand} ${item.spec}`"
                  class="w-full h-full object-cover opacity-50"
                />
              </div>

              <!-- Info Barang -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-foreground truncate">
                  {{ item.brand }} {{ item.spec }}
                </p>
                <p class="text-xs text-muted-foreground truncate">
                  {{ item.category }} ({{ item.subcategory }})
                </p>
                <p class="text-xs text-foreground mt-0.5">
                  Jumlah diminta:
                  <span class="font-semibold">{{ item.quantity }} satuan</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================
           Kolom Kanan: Form Detail Permintaan (sticky)
           ============================================================ -->
      <div class="w-full lg:w-80 xl:w-96 flex-shrink-0">
        <div class="bg-card border border-border rounded-[14px] p-5 sticky top-24">
          <h2 class="text-sm font-bold text-foreground mb-5">Detail permintaan:</h2>

          <div class="space-y-4">

            <!-- ── Pemanfaatan ── -->
            <div class="flex items-center gap-3">
              <label class="text-sm font-medium text-foreground w-32 flex-shrink-0">
                Pemanfaatan<span class="text-destructive">*</span>:
              </label>
              <div class="flex-1">
                <Select v-model="pemanfaatan">
                  <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
                    <SelectValue placeholder="Pilih Pemanfaatan" />
                  </SelectTrigger>
                  <SelectContent class="rounded-[14px]">
                    <SelectItem
                      v-for="opt in pemanfaatanOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- ── Departemen (hanya jika pemanfaatan = corporate) ── -->
            <div v-if="isCorporateRequired" class="flex items-center gap-3">
              <label class="text-sm font-medium text-foreground w-32 flex-shrink-0">
                Departemen<span class="text-destructive">*</span>:
              </label>
              <div class="flex-1">
                <Select v-model="departemen">
                  <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
                    <SelectValue placeholder="Pilih Departemen" />
                  </SelectTrigger>
                  <SelectContent class="rounded-[14px]">
                    <SelectItem
                      v-for="opt in departemenOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- ── Project (hanya jika pemanfaatan = project) ── -->
            <div v-if="isProjectRequired" class="flex items-center gap-3">
              <label class="text-sm font-medium text-foreground w-32 flex-shrink-0">
                Project<span class="text-destructive">*</span>:
              </label>
              <div class="flex-1">
                <Select v-model="project">
                  <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
                    <SelectValue placeholder="Pilih Project" />
                  </SelectTrigger>
                  <SelectContent class="rounded-[14px]">
                    <SelectItem
                      v-for="opt in projectOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- ── Alasan Permintaan ── -->
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-foreground">
                Alasan permintaan<span class="text-destructive">*</span>:
              </label>
              <textarea
                v-model="alasan"
                rows="5"
                placeholder="Ketik alasan permintaan di sini..."
                class="w-full p-3 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
              ></textarea>
            </div>

            <!-- Keterangan wajib isi -->
            <p class="text-xs text-destructive">*Wajib diisi</p>

            <!-- ── Tombol Konfirmasi ── -->
            <Button
              class="w-full bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-10 font-semibold text-sm transition-opacity"
              :disabled="!isFormValid || isSubmitting"
              :class="{ 'opacity-50 cursor-not-allowed': !isFormValid || isSubmitting }"
              @click="handleConfirm"
            >
              <span v-if="isSubmitting">Memproses...</span>
              <span v-else>Konfirmasi dan Minta Approval</span>
            </Button>

          </div>
        </div>
      </div>

    </div>

    <!-- ============================================================
         Modal Sukses — muncul setelah submit berhasil
         ============================================================ -->
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
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-md p-8 flex flex-col items-center text-center gap-4"
            @click.stop
          >
            <!-- Ikon sukses -->
            <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
              <CheckCircle2 class="w-9 h-9 text-green-600" />
            </div>

            <h3 class="text-xl font-bold text-foreground">Permintaan Terkirim!</h3>
            <p class="text-sm text-muted-foreground">
              Permintaan Anda telah berhasil dikirimkan dan sedang menunggu approval.
              Anda akan mendapat notifikasi ketika permintaan diproses.
            </p>

            <!-- Tombol aksi -->
            <div class="flex gap-3 w-full pt-2">
              <Button
                variant="outline"
                class="flex-1 rounded-full h-10 text-sm font-semibold border-input"
                @click="handleBack"
              >
                Kembali ke Keranjang
              </Button>
              <Button
                class="flex-1 bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-10 text-sm font-semibold"
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
