<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/Components/ui/number-field";

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
  barang_id: number | null;
  brand: string;
  name: string;
  spec: string;
  category: string;
  category_name: string;
  subcategory_name: string;
  code: string;
  stock: number;       // Stok total tersedia
  quantity: number;    // Jumlah yang diminta
  selected: boolean;
  isPreorder?: boolean; // Bisa pre-order tapi stok saat ini 0
  imageUrl?: string;
}

// --- Helper: Mendapatkan tanggal & waktu lokal client ---
const getClientDefaultDate = () => {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
};

const getClientDefaultTime = () => {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2, '0');
  const min = String(now.getMinutes()).padStart(2, '0');
  return `${hh}:${min}`;
};

const handlePickerClick = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target && typeof target.showPicker === 'function') {
    target.showPicker();
  }
};

// --- State Tanggal Peminjaman ---
// HARUS diisi lebih dulu — semua aksi lain disabled sampai tanggal mulai diisi
const startDate = ref(getClientDefaultDate());
const startTime = ref(getClientDefaultTime());
const endDate   = ref('');
const endTime   = ref('');

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

watch(endDate, (newVal) => {
  if (!newVal || newVal.trim() === '') {
    endTime.value = '';
  }
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

// --- Aksi: Update jumlah ---
const updateQty = (item: CartItem, value: number) => {
  router.put(route('smart.borrow-cart.update', item.id), {
    quantity: value
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

const getItemDisplayName = (item: CartItem) => {
  if (!item.barang_id) {
    return item.subcategory_name;
  }
  const parts = [];
  if (item.brand && item.brand !== '-') {
    parts.push(item.brand);
  }
  if (item.name && item.name !== 'Tidak Spesifik') {
    parts.push(item.name);
  }
  if (item.spec && item.spec !== '-') {
    parts.push(item.spec);
  }
  return parts.join(' ') || 'Barang';
};
</script>

<template>
  <AppLayout title="Keranjang Peminjaman">
    <!-- Judul Halaman -->
    <div class="mb-2">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Keranjang Peminjaman</h1>
      <p class="text-muted-foreground mt-2">Pilih tanggal peminjaman lalu pilih barang-barang yang ingin dimasukkan dalam peminjaman.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 mt-6">
      <!-- ============================================================ -->
      <!-- Kolom Kiri: Form Tanggal + Daftar Barang                     -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- === Blok Tanggal Peminjaman (Must be selected first) === -->
        <div class="bg-card border border-border rounded-[14px] p-5">
          <h2 class="text-lg font-bold text-foreground mb-2">Tanggal Peminjaman</h2>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
            <!-- Tanggal Mulai -->
            <div class="space-y-1">
              <label class="text-sm font-medium text-foreground">
                Tanggal mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startDate"
                type="date"
                class="h-10 rounded-[14px] text-sm w-full cursor-pointer"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- Waktu Mulai -->
            <div class="space-y-1">
              <label class="text-sm font-medium text-foreground">
                Waktu mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startTime"
                type="time"
                class="h-10 rounded-[14px] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- Tanggal Selesai -->
            <div class="space-y-1">
              <label class="text-sm font-medium text-muted-foreground">Tanggal selesai</label>
              <Input
                v-model="endDate"
                type="date"
                class="h-10 rounded-[14px] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- Waktu Selesai -->
            <div class="space-y-1">
              <label class="text-sm font-medium text-muted-foreground">Waktu selesai</label>
              <Input
                v-model="endTime"
                type="time"
                class="h-10 rounded-[14px] text-sm w-full cursor-pointer"
                :disabled="!endDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>
          </div>

          <!-- Pesan bantu -->
          <div class="text-sm italic flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mt-3">
            <p class="text-destructive font-medium">*harus diisi</p>
            <p class="text-muted-foreground">tanggal &amp; waktu selesai dapat dikosongkan</p>
          </div>
        </div>

        <!-- === Daftar Item Keranjang === -->
        <ScrollArea class="border border-border rounded-[14px] bg-card h-[calc(100vh-390px)] sm:h-[calc(100vh-390px)]">
          <div class="p-6">
            <div class="space-y-3">
              <!-- Pesan jika kosong -->
              <div v-if="filteredItems.length === 0" class="text-center py-10">
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
                <!-- Checkbox seleksi -->
                <Checkbox 
                  v-model="item.selected"
                  class="cursor-pointer"
                  :disabled="!isDateSelected"
                />

                <!-- Image -->
                <div class="w-24 h-24 shrink-0 bg-muted rounded-[14px] overflow-hidden flex items-center justify-center border border-border relative">
                  <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
                  <img v-if="item.imageUrl" :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/storage/' + item.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
                  <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                  <template v-if="!item.barang_id">
                    <h3 class="text-lg font-bold text-foreground leading-snug">{{ item.subcategory_name }}</h3>
                    <p class="text-sm text-muted-foreground leading-normal">{{ item.category_name }}</p>
                    <p class="text-xs text-muted-foreground italic">*gambar hanya ilustrasi</p>
                  </template>
                  <template v-else>
                    <span v-if="item.brand && item.brand !== '-'" class="text-lg font-bold text-foreground leading-tight">
                      {{ item.brand }}
                    </span>
                    <h3 class="text-lg font-bold text-foreground leading-snug">
                      {{ item.name }}{{ item.spec && item.spec !== '-' ? ' ' + item.spec : '' }}
                    </h3>
                    <p class="text-sm text-muted-foreground leading-normal">
                      {{ item.category_name }} ({{ item.subcategory_name }})
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      {{ item.code }}
                    </p>
                  </template>
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
                <div class="flex-shrink-0">
                  <NumberField 
                    :model-value="item.quantity" 
                    @update:model-value="(val) => updateQty(item, val)"
                    :min="1" 
                    :max="999999" 
                    locale="id-ID" 
                    class="w-32"
                    :disabled="!isDateSelected"
                  >
                    <NumberFieldContent>
                      <NumberFieldDecrement />
                      <NumberFieldInput />
                      <NumberFieldIncrement />
                    </NumberFieldContent>
                  </NumberField>
                </div>
              </div>
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Kolom Kanan: Ringkasan Peminjaman (sticky)                    -->
      <!-- ============================================================ -->
      <div class="w-full w-lg flex-shrink-0">
        <div class="bg-card border border-border rounded-[14px] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- Daftar item yang dipilih -->
          <div class="space-y-3 mb-6">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              {{ !isDateSelected ? 'Pilih tanggal peminjaman terlebih dahulu.' : 'Belum ada barang yang dipilih.' }}
            </div>
            <div
              v-for="item in selectedItems"
              :key="item.id"
              class="flex items-center justify-between gap-2"
            >
              <span class="text-base text-foreground font-medium truncate flex-1">
                {{ getItemDisplayName(item) }}
              </span>
              <span class="text-base text-muted-foreground flex-shrink-0 whitespace-nowrap">
                {{ item.quantity }} satuan
              </span>
            </div>
          </div>

          <hr class="border-border mb-5" />

          <!-- Tombol Lanjut ke Konfirmasi -->
          <!-- Disabled jika: tanggal belum diisi ATAU tidak ada item yang dipilih -->
          <Button
            variant="primary"
            size="lg"
            class="w-full"
            :disabled="!canProceed"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>

          <!-- Petunjuk kontekstual -->
          <p v-if="!isDateSelected" class="text-xs text-muted-foreground text-center mt-3">
            Pilih tanggal mulai terlebih dahulu.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
