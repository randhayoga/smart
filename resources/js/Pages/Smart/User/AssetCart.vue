<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Trash2, Minus, Plus } from 'lucide-vue-next';

interface Props {
  user?: any;
}
defineProps<Props>();

// --- Tipe Data ---
interface CartItem {
  id: number;
  brand: string;
  spec: string;
  category: string;
  code: string;
  stock: number; // Total stok tersedia
  quantity: number; // Jumlah yang diminta user
  selected: boolean;
  imageUrl?: string;
}

// --- Data Dummy Keranjang ---
// Nantinya diganti dengan data dari backend (tabel keranjang user)
const cartItems = ref<CartItem[]>([
  { id: 1, brand: 'Merek', spec: 'Spek', category: 'Kategori (Subkategori)', code: '#Nomor_Barang_1', stock: 10, quantity: 1, selected: false },
  { id: 2, brand: 'Merek', spec: 'Spek', category: 'Kategori (Subkategori)', code: '#Nomor_Barang_2', stock: 0,  quantity: 1, selected: false },
  { id: 3, brand: 'Merek', spec: 'Spek', category: 'Kategori (Subkategori)', code: '#Nomor_Barang_3', stock: 5,  quantity: 1, selected: false },
  { id: 4, brand: 'Merek', spec: 'Spek', category: 'Kategori (Subkategori)', code: '#Nomor_Barang_4', stock: 0,  quantity: 1, selected: false },
  { id: 5, brand: 'Merek', spec: 'Spek', category: 'Kategori (Subkategori)', code: '#Nomor_Barang_5', stock: 8,  quantity: 1, selected: false },
]);

// --- Filter: Tampilkan yang stok tersedia saja ---
const showAvailableOnly = ref(false);

const filteredItems = computed(() => {
  if (showAvailableOnly.value) {
    return cartItems.value.filter(item => item.stock > 0);
  }
  return cartItems.value;
});

// --- Computed: Item yang dipilih (untuk ringkasan & validasi) ---
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

// Tombol "Lanjut ke Konfirmasi" hanya aktif jika ada minimal 1 item yang dipilih
const canProceed = computed(() => selectedItems.value.length > 0);

// --- Aksi: Hapus item dari keranjang ---
const removeItem = (id: number) => {
  const index = cartItems.value.findIndex(item => item.id === id);
  if (index !== -1) {
    cartItems.value.splice(index, 1);
  }
};

// --- Aksi: Kurangi jumlah, minimal 1 ---
const decreaseQty = (item: CartItem) => {
  if (item.quantity > 1) {
    item.quantity--;
  }
};

// --- Aksi: Tambah jumlah, maksimal sesuai stok tersedia ---
const increaseQty = (item: CartItem) => {
  if (item.quantity < item.stock) {
    item.quantity++;
  }
};

// --- Aksi: Lanjut ke halaman konfirmasi ---
const handleProceed = () => {
  // Navigasi ke halaman konfirmasi dengan Inertia
  // TODO: Kirim selectedItems sebagai data ke controller via POST/session
  router.visit(route('smart.asset-cart.confirmation'));
};
</script>

<template>
  <AppLayout title="Keranjang Habis Pakai">
    <!-- Judul Halaman -->
    <div class="mb-2">
      <h1 class="text-2xl font-bold text-foreground">Keranjang Habis Pakai</h1>
      <p class="text-sm text-muted-foreground mt-1">Pilih barang-barang yang ingin dimasukkan dalam permintaan.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 mt-6">
      <!-- ============================================================ -->
      <!-- Kolom Kiri: Daftar Barang di Keranjang                        -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0">
        <!-- Filter atas -->
        <div class="flex justify-end mb-4">
          <label class="flex items-center gap-2.5 cursor-pointer select-none">
            <!-- Custom toggle/radio bulat -->
            <div class="relative flex items-center justify-center w-5 h-5 border-2 rounded-full transition-colors"
              :class="showAvailableOnly ? 'border-primary' : 'border-input'"
              @click="showAvailableOnly = !showAvailableOnly"
            >
              <div v-if="showAvailableOnly" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
            </div>
            <span class="text-sm font-medium text-foreground">Tampilkan barang yang tersedia saja</span>
          </label>
        </div>

        <!-- Daftar Item -->
        <div class="space-y-3">
          <!-- Jika keranjang kosong -->
          <div v-if="filteredItems.length === 0" class="bg-card border border-border rounded-[14px] p-10 text-center">
            <p class="text-muted-foreground text-sm">Keranjang kosong atau tidak ada barang yang sesuai filter.</p>
          </div>

          <!-- Item Card -->
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="bg-card border rounded-[14px] p-4 flex items-center gap-4 transition-colors"
            :class="item.selected ? 'border-primary/50 bg-primary/5' : 'border-border'"
          >
            <!-- Checkbox seleksi -->
            <div
              class="w-5 h-5 rounded border-2 flex-shrink-0 flex items-center justify-center cursor-pointer transition-colors"
              :class="item.selected ? 'bg-primary border-primary' : 'border-input bg-background'"
              @click="item.selected = !item.selected"
            >
              <!-- Checkmark icon -->
              <svg v-if="item.selected" class="w-3 h-3 text-white" viewBox="0 0 12 12" fill="none">
                <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>

            <!-- Gambar Barang -->
            <div class="w-16 h-16 flex-shrink-0 bg-muted rounded-[14px] overflow-hidden border border-border relative">
              <img v-if="item.imageUrl" :src="item.imageUrl" :alt="item.brand" class="w-full h-full object-cover" />
              <img v-else src="https://placehold.co/200x200?text=Barang" :alt="item.brand" class="w-full h-full object-cover opacity-50" />
            </div>

            <!-- Info Barang -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-foreground truncate">{{ item.brand }} {{ item.spec }}</p>
              <p class="text-xs text-muted-foreground truncate">{{ item.category }}</p>
              <p class="text-xs text-foreground mt-0.5">
                Jumlah stok:
                <span :class="item.stock > 0 ? 'text-foreground' : 'text-destructive font-medium'">
                  {{ item.stock > 0 ? `${item.stock} satuan` : 'Habis' }}
                </span>
              </p>
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

            <!-- Kontrol Jumlah / PO Reimburse -->
            <div class="flex-shrink-0">
              <!-- Jika stok habis, tampilkan tombol PO/Reimburse -->
              <Button
                v-if="item.stock === 0"
                class="bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-9 px-4 text-xs font-semibold"
              >
                PO / Reimburse
              </Button>

              <!-- Jika stok tersedia, tampilkan kontrol qty -->
              <div v-else class="flex items-center border border-input rounded-[14px] bg-background">
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
                  :disabled="item.quantity >= item.stock"
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
      <!-- Kolom Kanan: Ringkasan Peminjaman                             -->
      <!-- ============================================================ -->
      <div class="w-full lg:w-72 xl:w-80 flex-shrink-0">
        <div class="bg-card border border-border rounded-[14px] p-5 sticky top-24">
          <h2 class="text-base font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- Daftar item yang dipilih -->
          <div class="space-y-3 mb-6 min-h-[80px]">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              Belum ada barang yang dipilih.
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
          <!-- Disabled jika tidak ada item yang dipilih -->
          <Button
            class="w-full bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-10 font-semibold text-sm transition-opacity"
            :disabled="!canProceed"
            :class="{ 'opacity-50 cursor-not-allowed': !canProceed }"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>

          <!-- Petunjuk jika belum ada yang dipilih -->
          <p v-if="!canProceed" class="text-xs text-muted-foreground text-center mt-3">
            Pilih minimal 1 barang untuk melanjutkan.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
