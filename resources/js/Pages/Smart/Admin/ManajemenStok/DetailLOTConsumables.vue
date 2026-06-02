<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { X } from 'lucide-vue-next';

interface Props {
  isOpen: boolean;
  lotId: number | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'edit', lot: any): void;
  (e: 'delete', lot: any): void;
}>();

const lotDetails = ref<any>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

const fetchLotDetails = async (id: number) => {
  isLoading.value = true;
  error.value = null;
  try {
    const response = await axios.get(`/smart/inventory/lots/${id}`);
    lotDetails.value = response.data;
  } catch (err: any) {
    console.error(err);
    error.value = 'Gagal memuat data detail LOT.';
  } finally {
    isLoading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.lotId) {
    fetchLotDetails(props.lotId);
  } else {
    lotDetails.value = null;
  }
});

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return 'Rp0';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return 'Rp0';
  
  // Format to RpXXX.XXX (dot as thousands separator, no decimal)
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  // Replace slashes with dashes to match DD-MM-YYYY format
  return dateStr.replace(/\//g, '-');
};

const formatLocation = (lot: any) => {
  if (!lot) return '-';
  const parts = [];
  if (lot.location) parts.push(lot.location);
  if (lot.floor) parts.push(lot.floor);
  if (lot.room) parts.push(lot.room);
  return parts.join(', ') || '-';
};

const handleEdit = () => {
  if (lotDetails.value) {
    emit('close');
    emit('edit', lotDetails.value);
  }
};

const handleDelete = () => {
  if (lotDetails.value) {
    emit('close');
    emit('delete', lotDetails.value);
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="isOpen" 
            class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center p-1 justify-between border-b border-border">
              <h3 class="text-lg font-bold text-foreground p-2">Detail LOT</h3>
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 space-y-4">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
                <p class="text-sm text-muted-foreground">Memuat detail LOT...</p>
              </div>

              <div v-else-if="error" class="text-center py-12">
                <p class="text-rose-500 font-medium">{{ error }}</p>
                <button @click="lotId && fetchLotDetails(lotId)" class="mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-[14px] text-sm">
                  Coba Lagi
                </button>
              </div>

              <div v-else-if="lotDetails" class="flex flex-col md:flex-row gap-4">
                <!-- Left Column: Photo -->
                <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                  <img v-if="lotDetails.imageUrl" :src="'/storage/' + lotDetails.imageUrl" class="w-full h-full object-cover" />
                  <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
                </div>

                <!-- Right Column: Details Grid -->
                <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4">
                  <!-- Left Details Column -->
                  <div class="md:col-span-4">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ lotDetails.barang_code }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ lotDetails.barang_brand }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ lotDetails.barang_specification }}</p>
                    <p class="text-foreground">Kategori: {{ lotDetails.barang_category }}</p>
                    <p class="text-foreground">Subkategori: {{ lotDetails.barang_subcategory }}</p>
                    <p class="text-foreground">Satuan: {{ lotDetails.barang_uom }}</p>
                  </div>

                  <!-- Right Details Column -->
                  <div class="md:col-span-8">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ lotDetails.lotCode }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Jumlah stok tersedia:</span> {{ lotDetails.current_quantity ?? 0 }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Jumlah stok diawal:</span> {{ lotDetails.initial_quantity ?? 0 }}</p>
                    <p class="text-foreground">Lokasi: {{ formatLocation(lotDetails) }}</p>
                    <p class="text-foreground">Nomor PO: {{ lotDetails.poNumber }}</p>
                    <p class="text-foreground">Tanggal masuk: {{ formatDateWithDashes(lotDetails.entryDate) }}</p>
                    <p class="text-foreground">Harga satuan: {{ formatRupiah(lotDetails.unitPrice) }}</p>
                    <p class="text-foreground">Organizer: {{ lotDetails.organizer }}</p>
                    <p class="text-foreground">Vendor: {{ lotDetails.vendor }}</p>
                    <p class="text-foreground">Pembaruan terakhir: {{ lotDetails.updated_at }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div v-if="!isLoading && lotDetails" class="py-3 px-4 bg-muted/30 border-t border-border flex items-center justify-end gap-3">
              <button 
                @click="handleEdit"
                class="px-5 py-2 bg-gradient-primary hover:opacity-90 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98]"
              >
                Edit Detail LOT
              </button>
              <button 
                @click="handleDelete"
                class="px-5 py-2 bg-destructive hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98]"
              >
                Hapus LOT
              </button>
              <button 
                @click="emit('close')"
                class="px-5 py-2 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98]"
              >
                Kembali
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
