<script setup lang="ts">
/**
 * Consumable LOT Detail Modal component presenting stock balance, PO number, receiving date, and unit cost.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useModalLock } from '@/composables/useModalLock';
import axios from 'axios';
import { X, ExternalLink } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate } from '@/lib/utils';
import Tabs from '@/Components/Tabs.vue';
import AssetPhotoGrid from '@/Components/AssetPhotoGrid.vue';

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

const { t, locale } = useI18n();
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

useModalLock(computed(() => props.isOpen));

const detailActiveTab = ref('Detail LOT');
const tabs = computed(() => [
  { id: 'Detail LOT', label: t('inventory.lotDetail') },
]);

const lotDetails = ref<any>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

const fetchLotDetails = async (id: number) => {
  isLoading.value = true;
  error.value = null;
  try {
    const response = await axios.get(`/smart/stock-management/lots/${id}`);
    lotDetails.value = response.data;
  } catch (err: any) {
    console.error(err);
    error.value = t('inventory.loadLotDetailsFailed');
  } finally {
    isLoading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    detailActiveTab.value = 'Detail LOT';
    if (props.lotId) {
      fetchLotDetails(props.lotId);
    }
  } else {
    lotDetails.value = null;
  }
}, { immediate: true });

const handleRequestSuccess = () => {
  if (props.lotId) {
    fetchLotDetails(props.lotId);
  }
  emit('close');
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
  return `Rp${formatted}`;
};

const formatLocation = (lot: any) => {
  if (!lot) return '-';
  if (lot.location && (lot.floor || lot.room)) {
    const parts = [lot.location];
    if (lot.floor) parts.push(lot.floor);
    if (lot.room) parts.push(lot.room);
    return parts.join(', ');
  }
  return lot.location || lot.location_name || '-';
};

const lotCategoryWithSubcategory = computed(() => {
  if (!lotDetails.value) return '-';
  const cat = lotDetails.value.barang_category;
  const sub = lotDetails.value.barang_subcategory;
  if (cat && sub && cat !== '-' && sub !== '-') {
    return `${cat} (${sub})`;
  }
  return cat || sub || '-';
});

const lotModelDisplay = computed(() => {
  if (!lotDetails.value) return '-';
  const brand = lotDetails.value.barang_brand && lotDetails.value.barang_brand !== '-' ? lotDetails.value.barang_brand : '';
  const name = lotDetails.value.barang_nama && lotDetails.value.barang_nama !== '-' ? lotDetails.value.barang_nama : '';
  const spec = lotDetails.value.barang_specification && lotDetails.value.barang_specification !== '-' ? lotDetails.value.barang_specification : '';
  return [brand, name, spec].filter(Boolean).join(' ') || '-';
});

const lotBurdenDisplay = computed(() => {
  if (!lotDetails.value?.burden) return '-';
  if (lotDetails.value.burden === 'Project') {
    const prjNo = lotDetails.value.project_no;
    const prjName = lotDetails.value.project_name;
    if (prjNo && prjName) {
      return `Project [${prjNo}] ${prjName}`;
    }
    if (prjNo) {
      return `Project [${prjNo}]`;
    }
    if (prjName) {
      return `Project ${prjName}`;
    }
    return 'Project';
  }
  return lotDetails.value.burden;
});

const lotVendorDisplay = computed(() => {
  if (!lotDetails.value) return '-';
  if (!lotDetails.value.vendor_id || lotDetails.value.vendor === '-' || !lotDetails.value.vendor || String(lotDetails.value.vendor).trim().toLowerCase() === 'beragam') {
    return t('inventory.vendorMultiple');
  }
  return lotDetails.value.vendor;
});

const linkedRequestId = computed<number | null>(() => {
  if (lotDetails.value?.request_id) return Number(lotDetails.value.request_id);
  if (lotDetails.value?.active_borrowing?.request_id) return Number(lotDetails.value.active_borrowing.request_id);
  if (lotDetails.value?.latest_request?.request_id) return Number(lotDetails.value.latest_request.request_id);
  return null;
});

const linkedRequestNumber = computed<string>(() => {
  return lotDetails.value?.request_number || 
    lotDetails.value?.active_borrowing?.request_number || 
    lotDetails.value?.latest_request?.request_number || 
    (linkedRequestId.value ? `REQ-${linkedRequestId.value}` : '-');
});

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

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close');
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
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
      <div v-if="isOpen" @click="emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
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
            class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-2 px-4 border-b border-border">
              <Tabs v-model="detailActiveTab" :tabs="tabs" />
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors cursor-pointer">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 space-y-4">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
                <p class="text-sm text-muted-foreground">{{ t('inventory.loadingLotDetails') }}</p>
              </div>

              <div v-else-if="error" class="text-center py-12">
                <p class="text-rose-500 font-medium">{{ error }}</p>
                <button @click="lotId && fetchLotDetails(lotId)" class="mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-[14px] text-sm">
                  {{ t('inventory.tryAgain') }}
                </button>
              </div>

              <div v-else-if="lotDetails && detailActiveTab === 'Detail LOT'" class="flex flex-col md:flex-row gap-4">
                <!-- Left Column: Photo -->
                <AssetPhotoGrid :photos="lotDetails.imageUrl ? [lotDetails.imageUrl] : []" />

                <!-- Details Single Column (takes full horizontal space) -->
                <div class="flex-grow flex flex-col gap-1.5 text-foreground">
                  <!-- 0. Nomor LOT (Only for consumables) -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.lotCode') }}:</span> {{ lotDetails.number }}
                  </p>

                  <!-- 1. Kategori (shows category + type) -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.category') }}:</span> {{ lotCategoryWithSubcategory }}
                  </p>

                  <!-- 3. Model (shows brand + name + spec) -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.modelLabel') }}:</span> {{ lotModelDisplay }}
                  </p>

                  <!-- 4. Jumlah stok (shows current_stock <uom> / initial_stock <uom>) -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.stockQuantity') }}:</span> 
                    {{ lotDetails.current_quantity ?? 0 }} {{ lotDetails.barang_uom || '' }} / {{ lotDetails.initial_quantity ?? 0 }} {{ lotDetails.barang_uom || '' }}
                  </p>

                  <!-- 5. *Lokasi (not "Lokasi Penyimpanan") -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.location') }}:</span> {{ formatLocation(lotDetails) }}
                  </p>

                  <!-- 6. Nomor PO/Nomor Memo (dynamic, if hibah make the nomor memo a hyperlink that opens the memo_url in new tab) -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ lotDetails.entry_type === 'Hibah' ? t('inventory.memoNumber') : t('inventory.poNumber') }}:</span>
                    <template v-if="lotDetails.entry_type === 'Hibah'">
                      <a 
                        v-if="lotDetails.memo_url"
                        :href="'/media/' + lotDetails.memo_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary hover:underline font-semibold inline-flex items-center gap-1 ml-1"
                      >
                        <span>{{ lotDetails.memo_number || '-' }}</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                      </a>
                      <span v-else class="ml-1">{{ lotDetails.memo_number || '-' }}</span>
                    </template>
                    <span v-else class="ml-1">{{ lotDetails.po_number || '-' }}</span>
                  </p>

                  <!-- 7. Tanggal Registrasi/Tanggal Hibah (dynamic) -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ lotDetails.entry_type === 'Hibah' ? t('inventory.grantDate') : t('inventory.registrationDate') }}:</span>
                    {{ formatDate(lotDetails.entry_type === 'Hibah' ? (lotDetails.date_of_grant || lotDetails.date_of_receipt) : lotDetails.date_of_receipt) }}
                  </p>

                  <!-- 8. Organizer -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.organizer') }}:</span> {{ lotDetails.organizer || '-' }}
                  </p>

                  <!-- 9. *Vendor -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.vendor') }}:</span> {{ lotVendorDisplay }}
                  </p>

                  <!-- 10. *Pembebanan (also shows the project number if project) -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.burden') }}:</span> {{ lotBurdenDisplay }}
                  </p>

                  <!-- 11. Permintaan Terkait (if linked to a request) -->
                  <p v-if="linkedRequestId" class="text-foreground">
                    <span class="text-foreground">{{ t('fulfillment.request') }}:</span>
                    <a 
                      :href="`/smart/requests/${linkedRequestId}`"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-primary hover:underline font-semibold inline-flex items-center gap-1 ml-1"
                    >
                      <span>{{ linkedRequestNumber }}</span>
                      <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                  </p>

                  <!-- 12. Pembaruan Terakhir -->
                  <p class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.lastUpdate') }}:</span> {{ lotDetails.updated_at || '-' }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div v-if="!isLoading && lotDetails" class="py-3 px-4 bg-muted/30 border-t border-border flex items-center justify-end gap-3">
              <template v-if="detailActiveTab === 'Detail LOT' && can('inventory.manage')">
                <Button
                  @click="handleEdit"
                  variant="primary"
                  size="lg"
                >
                  {{ t('inventory.editLotDetail') }}
                </Button>
                <Button
                  @click="handleDelete"
                  variant="destructive"
                  size="lg"
                >
                  {{ t('inventory.deleteLot') }}
                </Button>
              </template>
              <Button
                @click="emit('close')"
                variant="white"
                size="lg"
              >
                {{ t('common.back') }}
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
