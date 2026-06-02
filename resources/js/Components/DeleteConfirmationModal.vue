<script setup lang="ts">
import { computed } from 'vue';
import { X, AlertTriangle } from 'lucide-vue-next';

interface Props {
  isOpen: boolean;
  itemCount: number;
  itemName?: string;
  itemData?: Record<string, any> | null;
}

const props = withDefaults(defineProps<Props>(), {
  itemName: 'Barang',
  itemData: null,
});

const emit = defineEmits(['close', 'confirm']);

const handleConfirm = () => {
  emit('confirm');
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return 'Rp0';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return 'Rp0';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
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

const displayFields = computed(() => {
  if (!props.itemData || props.itemCount !== 1) return [];

  const data = props.itemData;
  const fields: { label: string; value: any }[] = [];

  // Determine fields based on keys present in the item data (e.g. for ManajemenStok)
  if ('specification' in data || 'lastUpdate' in data || 'amount' in data) {
    if (data.code) fields.push({ label: 'Kode', value: data.code });
    if (data.category) fields.push({ label: 'Kategori', value: data.category });
    if (data.subcategory) fields.push({ label: 'Subkategori', value: data.subcategory });
    if (data.brand) fields.push({ label: 'Merek', value: data.brand });
    if (data.specification) fields.push({ label: 'Spesifikasi', value: data.specification });
    if (data.lastUpdate) fields.push({ label: 'Pembaruan Terakhir', value: data.lastUpdate });
    if (data.amount !== undefined) fields.push({ label: 'Jumlah', value: data.amount });
    return fields;
  }

  // Determine fields for Lot
  if ('lotCode' in data) {
    fields.push({ label: 'Kode LOT', value: data.lotCode });
    if (data.barang_brand) fields.push({ label: 'Merek', value: data.barang_brand });
    if (data.barang_specification) fields.push({ label: 'Spesifikasi', value: data.barang_specification });
    if (data.barang_category) fields.push({ label: 'Kategori', value: data.barang_category });
    if (data.barang_subcategory) fields.push({ label: 'Subkategori', value: data.barang_subcategory });
    
    const uom = data.barang_uom || '';
    const currentQty = data.current_quantity !== undefined && data.current_quantity !== null ? data.current_quantity : 0;
    const initialQty = data.initial_quantity !== undefined && data.initial_quantity !== null ? data.initial_quantity : 0;
    fields.push({ label: 'Jumlah stok tersedia', value: `${currentQty} ${uom}`.trim() });
    fields.push({ label: 'Jumlah stok diawal', value: `${initialQty} ${uom}`.trim() });
    
    if (!data.is_consumable) {
      fields.push({ label: 'Lokasi', value: formatLocation(data) });
    }
    
    if (data.poNumber) fields.push({ label: 'Nomor PO', value: data.poNumber });
    if (data.entryDate) fields.push({ label: 'Tanggal masuk', value: formatDateWithDashes(data.entryDate) });
    
    if (!data.is_consumable) {
      fields.push({ label: 'Harga Satuan', value: formatRupiah(data.unitPrice) });
    }
    
    if (data.organizer) fields.push({ label: 'Organizer', value: data.organizer });
    if (data.vendor) fields.push({ label: 'Vendor', value: data.vendor });
    if (data.updated_at) fields.push({ label: 'Pembaruan terakhir', value: data.updated_at });
    
    return fields;
  }

  // Determine fields for MasterData
  const name = props.itemName || 'Barang';
  
  if (data.code) {
    fields.push({ label: `Kode ${name}`, value: data.code });
  }
  
  if (data.is_consumable !== undefined) {
    fields.push({ 
      label: 'Klasifikasi', 
      value: data.is_consumable ? 'Habis Pakai' : 'Aset' 
    });
  }
  
  if (data.name) {
    fields.push({ label: `Nama ${name}`, value: data.name });
  }
  
  if (data.parent) {
    let parentLabel = 'Kategori Induk';
    if (name === 'Lantai') parentLabel = 'Lokasi';
    if (name === 'Ruangan') parentLabel = 'Lantai';
    fields.push({ label: parentLabel, value: data.parent });
  }

  return fields;
});
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
            class="bg-card w-full max-w-[576px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center p-1 justify-between border-b border-border">
              <h3 class="text-lg font-bold text-foreground p-2">Konfirmasi Penghapusan</h3>
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 flex flex-col items-center text-center space-y-4 flex-grow">
              <div class="w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/20 flex items-center justify-center text-[#CC0000]">
                <AlertTriangle class="w-6 h-6" />
              </div>
              <div class="space-y-4 w-full">
                <p class="text-destructive font-semibold text-base">
                  Apakah Anda yakin untuk menghapus {{ itemCount }} {{ itemName }} yang Anda pilih?
                </p>

                <!-- Single Item Info Details -->
                <div v-if="displayFields.length > 0" class="p-3 rounded-[14px] bg-muted/40 border border-border text-left space-y-2.5 w-full max-w-md mx-auto">
                  <div v-for="field in displayFields" :key="field.label" class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0">
                    <span class="col-span-5 text-muted-foreground font-medium">{{ field.label }}</span>
                    <span class="col-span-7 text-foreground font-semibold text-right break-words">
                      {{ field.value }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 bg-muted/30 border-t border-border flex items-center justify-end">
              <div class="flex items-center gap-3">
                <button 
                  @click="emit('close')"
                  class="px-5 py-2 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  Batal
                </button>
                <button 
                  @click="handleConfirm"
                  class="px-5 py-2 bg-destructive hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98]"
                >
                  Konfirmasi Penghapusan
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
