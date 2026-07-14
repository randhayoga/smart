<script setup lang="ts">
import { ref, watch } from 'vue';
import { X, FileText, ThumbsUp, Ban } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import Tabs from '@/Components/Tabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import JejakAuditTab from '@/Pages/Smart/MultiRoles/Tabs/JejakAuditTab.vue';

interface Props {
  open: boolean;
  approval: any;
  mode: 'pending' | 'decided';
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'approve'): void;
  (e: 'reject'): void;
}>();

const detailActiveTab = ref('Detail Aset');

watch(() => props.open, (newVal) => {
  if (newVal) {
    detailActiveTab.value = 'Detail Aset';
  }
});

const isVehicle = (item: any) => {
  if (!item) return false;
  const category = (item.category || '').toLowerCase();
  const subcategory = (item.subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return '-';
  let num: number;
  if (typeof val === 'string') {
    const cleanStr = val.replace(/[^0-9,-]/g, '');
    if (!cleanStr) return '-';
    num = parseFloat(cleanStr.replace(/,/g, '.'));
  } else {
    num = val;
  }
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null | undefined, floor: string | null, room: string | null) => {
  const parts = [];
  if (loc && loc !== '-') parts.push(loc);
  if (floor && floor !== '-') parts.push(floor);
  if (room && room !== '-') parts.push(room);
  return parts.join(', ') || '-';
};

const formatDateWithDashes = (dateStr: string | null | undefined) => {
  if (!dateStr || dateStr === '-') return '-';
  return dateStr.replace(/\//g, '-');
};

const openMemoFile = (path: string | null) => {
  if (!path) return;
  window.open('/storage/' + path, '_blank');
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
      <div 
        v-if="open && approval" 
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click="emit('update:open', false)"
      >
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open && approval"
            class="bg-card w-full max-w-[95%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between pt-2 px-4 border-b border-border">
              <Tabs v-model="detailActiveTab" :tabs="['Detail Aset', 'Jejak Audit']" />
              <button @click="emit('update:open', false)" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body contents -->
            <div class="overflow-y-auto max-h-[70vh] px-6 py-3 space-y-4">
              
              <!-- ── TAB 1: DETAIL ── -->
              <div v-if="detailActiveTab === 'Detail Aset'" class="flex flex-col md:flex-row gap-6">
                <!-- Image Column -->
                <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                  <img 
                    v-if="approval.unit_details.image_url" 
                    :src="approval.unit_details.image_url.startsWith('http') || approval.unit_details.image_url.startsWith('/') ? approval.unit_details.image_url : '/storage/' + approval.unit_details.image_url" 
                    class="w-full h-full object-cover" 
                  />
                  <img 
                    v-else 
                    src="https://placehold.co/400x400?text=Placeholder" 
                    class="w-full h-full object-cover opacity-50" 
                  />
                </div>

                <!-- Details Columns -->
                <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4 text-foreground">
                  <!-- Column 1: Item Info -->
                  <div class="md:col-span-3">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode Tipe:</span> {{ approval.unit_details.barang_code }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ approval.brand }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ approval.nama }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ approval.specification }}</p>
                    <p class="text-foreground">Kategori: {{ approval.category }}</p>
                    <p class="text-foreground">Subkategori: {{ approval.subcategory }}</p>
                    <p class="text-foreground">Satuan: {{ approval.unit_details.barang_unit }}</p>
                  </div>

                  <!-- Column 2: LOT Info -->
                  <div class="md:col-span-4">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ approval.unit_details.lot_code }}</p>
                    <p class="text-foreground">Organizer: {{ approval.unit_details.organizer }}</p>
                    <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(approval.unit_details.date_of_receipt) }}</p>
                    <p class="text-foreground">Umur: {{ approval.unit_details.age !== undefined && approval.unit_details.age !== null ? `${approval.unit_details.age} tahun` : '-' }}</p>
                    <p class="text-foreground">Vendor: {{ approval.unit_details.vendor }}</p>
                    <p class="text-foreground">Nomor PO: {{ approval.unit_details.po_number }}</p>
                  </div>

                  <!-- Column 3: Asset Info -->
                  <div class="md:col-span-5">
                    <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ approval.asset_code }}</p>
                    <!-- TNKB (Nopol) -->
                    <p v-if="isVehicle(approval)" class="font-bold text-foreground">
                      <span class="text-foreground">Nopol:</span> {{ approval.unit_details.vehicle_registration || '-' }}
                    </p>
                    
                    <!-- Decided mode fields -->
                    <p v-if="mode === 'decided'" class="text-foreground">
                      Keputusan: 
                      <span 
                        :class="[
                          'font-semibold',
                          approval.decision === 'approved' ? 'text-emerald-600' : 'text-rose-600'
                        ]"
                      >
                        {{ approval.decision === 'approved' ? 'Disetujui' : 'Ditolak' }}
                      </span>
                    </p>

                    <p class="text-foreground flex flex-col gap-1.5">
                      <span>
                        Status sebelumnya: 
                        <StatusBadge :status="approval.previous_status" />
                      </span>
                      <span>
                        Status diajukan: 
                        <StatusBadge :status="approval.proposed_status" />
                      </span>
                      
                      <!-- Decided mode fields -->
                      <span v-if="mode === 'decided'">
                        Status setelah approval: 
                        <StatusBadge 
                          :status="approval.unit_details.status" 
                          :proposed-status="approval.proposed_status"
                        />
                      </span>
                    </p>

                    <!-- Decided mode fields -->
                    <p class="text-foreground" v-if="mode === 'decided' && approval.note">
                      Catatan Manager: <span class="italic text-muted-foreground">"{{ approval.note }}"</span>
                    </p>

                    <p class="text-foreground">
                      Kondisi: 
                      <span 
                        :class="[
                          'font-semibold',
                          approval.unit_details.condition === 'Baik' ? 'text-emerald-600' :
                          approval.unit_details.condition === 'Kurang Baik' ? 'text-amber-600' :
                          'text-rose-600'
                        ]"
                      >
                        {{ approval.unit_details.condition }}
                      </span>
                    </p>
                    <p class="text-foreground">Nilai: {{ formatRupiah(approval.unit_details.price) }}</p>
                    <p class="text-foreground">Lokasi penyimpanan: {{ formatLocation(approval.unit_details.location, approval.unit_details.floor, approval.unit_details.room) }}</p>
                    <p class="text-foreground" v-if="mode === 'pending'">Pembaruan terakhir: {{ approval.requested_at }}</p>
                  </div>
                </div>
              </div>

              <!-- ── TAB 2: JEJAK AUDIT ── -->
              <JejakAuditTab 
                v-if="detailActiveTab === 'Jejak Audit'"
                :lifecycles="approval.unit_details.lifecycles" 
              />
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10 shrink-0">
              <!-- Purple Memo Button -->
               <Button 
                @click="openMemoFile(approval.memo_url)"
                variant="warning"
                size="lg"
                class="inline-flex items-center gap-2"
              >
                <FileText class="w-4 h-4" />
                Buka Memo / Berita Acara
              </Button>

              <!-- Lost Document Button (Conditional if Hilang) -->
              <Button 
                v-if="approval.proposed_status === 'Hilang'"
                @click="openMemoFile(approval.lost_doc_url)"
                variant="warning"
                size="lg"
                class="inline-flex items-center gap-2"
              >
                <FileText class="w-4 h-4" />
                Surat Keterangan Kehilangan
              </Button>

              <!-- Pending mode buttons -->
              <template v-if="mode === 'pending'">
                <!-- Green Approve Button -->
                <Button 
                  @click="emit('approve')"
                  variant="success"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <ThumbsUp class="w-4 h-4" />
                  Approve
                </Button>

                <!-- Red Reject Button -->
                <Button 
                  @click="emit('reject')"
                  variant="destructive"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <Ban class="w-4 h-4" />
                  Tolak
                </Button>
              </template>

              <!-- White Kembali Button -->
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
