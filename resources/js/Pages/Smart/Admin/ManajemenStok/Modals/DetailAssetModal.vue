<script setup lang="ts">
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/StatusBadge.vue';

interface Props {
  open: boolean;
  asset: any;
  lot?: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'edit', asset: any): void;
}>();

const isVehicle = computed(() => {
  if (!props.asset) return false;
  const category = (finalBarangCategory.value || '').toLowerCase();
  const subcategory = (finalBarangSubcategory.value || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

// Helper Functions
const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

const formatDateWithDashes = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

const getConditionLabel = (cond: string) => {
  return cond || '';
};

const getAge = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return null;
  const receipt = new Date(dateStr);
  if (isNaN(receipt.getTime())) return null;
  const diffTime = new Date().getTime() - receipt.getTime();
  const years = Math.floor(diffTime / (1000 * 60 * 60 * 24 * 365));
  return years >= 0 ? years : 0;
};

// Dynamic attribute resolution
const finalLotAge = computed(() => {
  if (props.lot?.age !== undefined && props.lot?.age !== null) return props.lot.age;
  if (props.asset?.lot_age !== undefined && props.asset?.lot_age !== null) return props.asset.lot_age;
  return getAge(finalLotDateOfReceipt.value);
});

const finalLotNumber = computed(() => props.lot?.number || props.asset?.lot_number || '');
const finalLotOrganizer = computed(() => props.lot?.organizer || props.asset?.lot_organizer || '');
const finalLotDateOfReceipt = computed(() => props.lot?.date_of_receipt || props.asset?.lot_date_of_receipt || '');
const finalLotVendor = computed(() => props.lot?.vendor || props.asset?.lot_vendor || '');
const finalLotPoNumber = computed(() => props.lot?.po_number || props.asset?.lot_po_number || '');
const finalLotImageUrl = computed(() => props.lot?.imageUrl || props.asset?.lot_imageUrl || '');

const finalBarangCode = computed(() => props.lot?.barang_code || props.asset?.barang_code || '');
const finalBarangBrand = computed(() => props.lot?.barang_brand || props.asset?.barang_brand || '');
const finalBarangNama = computed(() => props.lot?.barang_nama || props.asset?.barang_nama || '');
const finalBarangSpecification = computed(() => props.lot?.barang_specification || props.asset?.barang_specification || '');
const finalBarangCategory = computed(() => props.lot?.barang_category || props.asset?.barang_category || '');
const finalBarangSubcategory = computed(() => props.lot?.barang_subcategory || props.asset?.barang_subcategory || '');
const finalBarangUom = computed(() => props.lot?.barang_uom || props.asset?.barang_uom || '');
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
      <div v-if="open" @click="emit('update:open', false)" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-full md:max-w-[80%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Detail Aset</h3>
              <button @click="emit('update:open', false)" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh]">
              <div class="flex flex-col md:flex-row gap-6">
                <!-- Image Column -->
                <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                  <img 
                    v-if="asset && asset.image_url" 
                    :src="'/storage/' + asset.image_url" 
                    class="w-full h-full object-cover" 
                  />
                  <img 
                    v-else-if="finalLotImageUrl" 
                    :src="'/storage/' + finalLotImageUrl" 
                    class="w-full h-full object-cover" 
                  />
                  <img 
                    v-else 
                    src="https://placehold.co/400x400?text=Placeholder" 
                    class="w-full h-full object-cover opacity-50" 
                  />
                </div>

                <!-- Details Columns -->
                <div v-if="asset" class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4 text-foreground">
                  <!-- Column 1: Item Info -->
                  <div class="md:col-span-3">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode Tipe:</span> {{ finalBarangCode }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ finalBarangBrand }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ finalBarangNama }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ finalBarangSpecification }}</p>
                    <p class="text-foreground">Kategori: {{ finalBarangCategory }}</p>
                    <p class="text-foreground">Subkategori: {{ finalBarangSubcategory }}</p>
                    <p class="text-foreground">Satuan: {{ finalBarangUom }}</p>
                  </div>

                  <!-- Column 2: LOT Info -->
                  <div class="md:col-span-4">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ finalLotNumber }}</p>
                    <p class="text-foreground">Organizer: {{ finalLotOrganizer }}</p>
                    <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(finalLotDateOfReceipt) }}</p>
                    <p class="text-foreground">Umur: {{ finalLotAge !== null && finalLotAge !== undefined ? `${finalLotAge} tahun` : '-' }}</p>
                    <p class="text-foreground">Vendor: {{ finalLotVendor }}</p>
                    <p class="text-foreground">Nomor PO: {{ finalLotPoNumber }}</p>
                  </div>

                  <!-- Column 3: Asset Info -->
                  <div class="md:col-span-5">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ asset.number }}</p>
                    <!-- TNKB (Nopol) -->
                    <p v-if="isVehicle" class="font-bold text-foreground">
                      <span class="text-foreground">Nopol:</span> {{ asset.vehicle_registration || '-' }}
                    </p>
                    <p class="text-foreground">
                      Status: 
                      <StatusBadge 
                        :status="asset.status" 
                        :proposed-status="asset.proposed_status" 
                      />
                    </p>
                    <p class="text-foreground">
                      Kondisi: 
                      <span 
                        :class="[
                          'font-semibold',
                          asset.condition === 'Baik' ? 'text-emerald-600' :
                          asset.condition === 'Kurang Baik' ? 'text-amber-600' :
                          'text-rose-600'
                        ]"
                      >
                        {{ getConditionLabel(asset.condition) }}
                      </span>
                    </p>
                    <p class="text-foreground">Nilai: {{ formatRupiah(asset.price) }}</p>
                    <p class="text-foreground">Lokasi penyimpanan: {{ formatLocation(asset.location, asset.floor, asset.room) }}</p>
                    <p class="text-foreground">Pembaruan terakhir: {{ asset.updated_at || '-' }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
              <Button 
                @click="
                  emit('update:open', false);
                  emit('edit', props.asset);
                "
                variant="primary"
                size="lg"
              >
                Edit Detail Aset
              </Button>

              <Button 
                @click="emit('update:open', false)"
                variant="white"
                size="lg"
              >
                Kembali
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
