<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2, Minus, Plus } from 'lucide-vue-next';

interface Props {
  user?: any;
  cartItems?: CartItem[];
  defaultStartDate?: string;
  defaultStartTime?: string;
  defaultEndDate?: string;
  defaultEndTime?: string;
}

const props = withDefaults(defineProps<Props>(), {
  cartItems: () => [],
  defaultStartDate: '',
  defaultStartTime: '',
  defaultEndDate: '',
  defaultEndTime: '',
});

// --- Tipe Data ---
interface CartItem {
  id: number;
  brand: string;
  spec: string;
  category: string;
  code: string;
  stock: number;       // Stok total tersedia
  quantity: number;    // Jumlah yang diminta
  selected: boolean;
  isPreorder?: boolean; // Bisa pre-order tapi stok saat ini 0
  imageUrl?: string;
}

// --- State Tanggal Peminjaman ---
// HARUS diisi lebih dulu — semua aksi lain disabled sampai tanggal mulai diisi
const startDate = ref(props.defaultStartDate);
const startTime = ref(props.defaultStartTime);
const endDate   = ref(props.defaultEndDate);
const endTime   = ref(props.defaultEndTime);

const cartItems = ref<CartItem[]>(props.cartItems.map(item => ({ ...item, selected: false })));

watch(() => props.cartItems, (newVal) => {
  const selectedMap = new Map(cartItems.value.map(i => [i.id, i.selected]));
  cartItems.value = newVal.map(item => ({
    ...item,
    selected: selectedMap.get(item.id) || false
  }));
}, { deep: true });

const isDateSelected = computed(() => startDate.value && startDate.value.trim() !== '');

// Jika tanggal mulai berubah → reset semua pilihan (karena ketersediaan bisa berubah)
watch(startDate, () => {
  cartItems.value.forEach(item => {
    item.selected = false;
    item.quantity = 1;
  });
});
watch(startTime, () => {
  cartItems.value.forEach(item => {
    item.selected = false;
    item.quantity = 1;
  });
});

const filteredItems = computed(() => {
  return cartItems.value;
});

// --- Computed: Item yang dipilih untuk ringkasan ---
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

// Tombol "Lanjut ke Konfirmasi" aktif jika ada tanggal mulai DAN minimal 1 item dipilih
const canProceed = computed(() => isDateSelected.value && selectedItems.value.length > 0);

// --- Aksi: Hapus item dari keranjang ---
const removeItem = (id: number) => {
  router.delete(route('smart.borrow-cart.destroy', id), {
    preserveScroll: true,
  });
};

// --- Aksi: Kurangi jumlah (minimal 1) ---
const decreaseQty = (item: CartItem) => {
  if (item.quantity > 1) {
    router.put(route('smart.borrow-cart.update', item.id), {
      quantity: item.quantity - 1
    }, {
      preserveScroll: true,
    });
  }
};

// --- Aksi: Tambah jumlah ---
const increaseQty = (item: CartItem) => {
  router.put(route('smart.borrow-cart.update', item.id), {
    quantity: item.quantity + 1
  }, {
    preserveScroll: true,
  });
};

// --- Aksi: Lanjut ke konfirmasi ---
const handleProceed = () => {
  const ids = selectedItems.value.map(i => i.id).join(',');
  router.get(route('smart.borrow-cart.confirmation'), {
    ids,
    start_date: startDate.value,
    start_time: startTime.value,
    end_date: endDate.value,
    end_time: endTime.value,
  });
};
</script>

<template>
  <AppLayout title="Keranjang Peminjaman">
    <!-- Judul Halaman -->
    <div class="mb-2">
      <h1 class="text-2xl font-bold text-foreground">Keranjang Peminjaman</h1>
      <p class="text-sm text-muted-foreground mt-1">Pilih tanggal peminjaman lalu pilih barang-barang yang ingin dimasukkan dalam peminjaman.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 mt-6">
      <!-- ============================================================ -->
      <!-- Kolom Kiri: Form Tanggal + Daftar Barang                     -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- === Blok Tanggal Peminjaman (Must be selected first) === -->
        <div class="bg-card border border-border rounded-[14px] p-5">
          <h2 class="text-sm font-bold text-foreground mb-4">Tanggal Peminjaman</h2>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
            <!-- Tanggal Mulai -->
            <div class="space-y-1">
              <label class="text-xs font-medium text-foreground">
                Tanggal mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startDate"
                type="date"
                class="h-10 rounded-[14px] text-sm w-full"
              />
            </div>

            <!-- Waktu Mulai -->
            <div class="space-y-1">
              <label class="text-xs font-medium text-foreground">
                Waktu mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startTime"
                type="time"
                class="h-10 rounded-[14px] text-sm w-full"
                :disabled="!startDate"
              />
            </div>

            <!-- Tanggal Selesai -->
            <div class="space-y-1">
              <label class="text-xs font-medium text-muted-foreground">Tanggal selesai</label>
              <Input
                v-model="endDate"
                type="date"
                class="h-10 rounded-[14px] text-sm w-full"
                :disabled="!startDate"
              />
            </div>

            <!-- Waktu Selesai -->
            <div class="space-y-1">
              <label class="text-xs font-medium text-muted-foreground">Waktu selesai</label>
              <Input
                v-model="endTime"
                type="time"
                class="h-10 rounded-[14px] text-sm w-full"
                :disabled="!startDate"
              />
            </div>
          </div>

          <!-- Pesan bantu -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mt-3">
            <p v-if="!isDateSelected" class="text-xs text-destructive font-medium">*harus diisi</p>
            <p class="text-xs text-muted-foreground">Tanggal &amp; waktu selesai dapat dikosongkan</p>
          </div>
        </div>



        <!-- === Daftar Item Keranjang === -->
        <div class="space-y-3">
          <!-- Pesan jika kosong -->
          <div v-if="filteredItems.length === 0" class="bg-card border border-border rounded-[14px] p-10 text-center">
            <p class="text-muted-foreground text-sm">Keranjang kosong atau tidak ada barang yang sesuai filter.</p>
          </div>

          <!-- Item Card -->
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="bg-card border rounded-[14px] p-4 flex items-center gap-4 transition-colors"
            :class="[
              item.selected ? 'border-primary/50 bg-primary/5' : 'border-border',
              !isDateSelected ? 'opacity-60' : ''
            ]"
          >
            <!-- Checkbox seleksi (disabled jika tanggal belum dipilih) -->
            <div
              class="w-5 h-5 rounded border-2 flex-shrink-0 flex items-center justify-center transition-colors"
              :class="[
                isDateSelected ? 'cursor-pointer' : 'cursor-not-allowed opacity-40',
                item.selected ? 'bg-primary border-primary' : 'border-input bg-background'
              ]"
              @click="isDateSelected && (item.selected = !item.selected)"
            >
              <svg v-if="item.selected" class="w-3 h-3 text-white" viewBox="0 0 12 12" fill="none">
                <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>

            <!-- Gambar Barang -->
            <div class="w-16 h-16 flex-shrink-0 bg-muted rounded-[14px] overflow-hidden border border-border">
              <img v-if="item.imageUrl" :src="item.imageUrl" :alt="item.brand" class="w-full h-full object-cover" />
              <img v-else src="https://placehold.co/200x200?text=Barang" :alt="item.brand" class="w-full h-full object-cover opacity-50" />
            </div>

            <!-- Info Barang -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-foreground truncate">{{ item.brand }} {{ item.spec }}</p>
              <p class="text-xs text-muted-foreground truncate">{{ item.category }}</p>

            </div>

            <!-- Tombol Hapus -->
            <Button
              variant="ghost"
              size="icon"
              class="text-destructive hover:bg-destructive/10 hover:text-destructive flex-shrink-0 rounded-full"
              @click="removeItem(item.id)"
              title="Hapus dari keranjang"
            >
              <Trash2 class="w-4 h-4" />
            </Button>

            <!-- Kontrol Jumlah -->
            <!-- Semua disabled jika tanggal belum dipilih -->
            <div class="flex-shrink-0" :class="{ 'opacity-40 pointer-events-none': !isDateSelected }">
              <div class="flex items-center border border-input rounded-[14px] bg-background">
                <button
                  type="button"
                  class="w-9 h-9 flex items-center justify-center text-muted-foreground hover:bg-muted/50 rounded-l-[14px] transition-colors disabled:opacity-40"
                  :disabled="item.quantity <= 1"
                  @click="decreaseQty(item)"
                >
                  <Minus class="w-3.5 h-3.5" />
                </button>
                <div class="w-10 h-9 flex items-center justify-center border-x border-input text-sm font-medium">
                  {{ item.quantity }}
                </div>
                <button
                  type="button"
                  class="w-9 h-9 flex items-center justify-center text-muted-foreground hover:bg-muted/50 rounded-r-[14px] transition-colors disabled:opacity-40"
                  @click="increaseQty(item)"
                >
                  <Plus class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ============================================================ -->
      <!-- Kolom Kanan: Ringkasan Peminjaman (sticky)                    -->
      <!-- ============================================================ -->
      <div class="w-full lg:w-72 xl:w-80 flex-shrink-0">
        <div class="bg-card border border-border rounded-[14px] p-5 sticky top-24">
          <h2 class="text-base font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- Daftar item yang dipilih -->
          <div class="space-y-3 mb-6 min-h-[80px]">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              {{ !isDateSelected ? 'Pilih tanggal peminjaman terlebih dahulu.' : 'Belum ada barang yang dipilih.' }}
            </div>
            <div
              v-for="item in selectedItems"
              :key="item.id"
              class="flex items-center justify-between gap-2"
            >
              <span class="text-sm text-foreground font-medium truncate flex-1">
                {{ item.brand }} {{ item.spec }}
              </span>
              <span class="text-sm text-muted-foreground flex-shrink-0 whitespace-nowrap">
                {{ item.quantity }} satuan
              </span>
            </div>
          </div>

          <hr class="border-border mb-5" />

          <!-- Tombol Lanjut ke Konfirmasi -->
          <!-- Disabled jika: tanggal belum diisi ATAU tidak ada item yang dipilih -->
          <Button
            class="w-full bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-10 font-semibold text-sm transition-opacity"
            :disabled="!canProceed"
            :class="{ 'opacity-50 cursor-not-allowed': !canProceed }"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>

          <!-- Petunjuk kontekstual -->
          <p v-if="!isDateSelected" class="text-xs text-muted-foreground text-center mt-3">
            Pilih tanggal mulai terlebih dahulu.
          </p>
          <p v-else-if="selectedItems.length === 0" class="text-xs text-muted-foreground text-center mt-3">
            Pilih minimal 1 barang untuk melanjutkan.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
