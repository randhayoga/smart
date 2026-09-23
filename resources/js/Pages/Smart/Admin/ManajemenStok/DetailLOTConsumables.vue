<script setup lang="ts">
/**
 * Consumable LOT Detail Modal component presenting stock balance, PO number, receiving date, and unit cost.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useModalLock } from '@/composables/useModalLock';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate } from '@/lib/utils';
import Tabs from '@/Components/Tabs.vue';
import FormulirPermintaanBarangHabisPakai from './Tabs/FormulirPermintaanBarangHabisPakai.vue';

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
const tabs = computed(() => {
  const items = [
    { id: 'Detail LOT', label: t('inventory.lotDetail') },
  ];
  if (can('inventory.manual_request')) {
    items.push({ id: 'Permintaan', label: t('inventory.manualRequest') });
  }
  return items;
});

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
});

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
                <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                  <img v-if="lotDetails.imageUrl" :src="'/media/' + lotDetails.imageUrl" class="w-full h-full object-cover" />
                  <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
                </div>

                <!-- Right Column: Details Grid -->
                <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4">
                  <!-- Left Details Column -->
                  <div class="md:col-span-4">
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.typeCode') }}:</span> {{ lotDetails.barang_code }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.brand') }}:</span> {{ lotDetails.barang_brand }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.name') }}:</span> {{ lotDetails.barang_nama }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.specification') }}:</span> {{ lotDetails.barang_specification }}</p>
                    <p class="text-foreground">{{ t('inventory.category') }}: {{ lotDetails.barang_category }}</p>
                    <p class="text-foreground">{{ t('inventory.subcategory') }}: {{ lotDetails.barang_subcategory }}</p>
                    <p class="text-foreground">{{ t('inventory.uom') }}: {{ lotDetails.barang_uom }}</p>
                  </div>

                  <!-- Right Details Column -->
                  <div class="md:col-span-8">
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.lotCode') }}:</span> {{ lotDetails.number }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.availableStock') }}:</span> {{ lotDetails.current_quantity ?? 0 }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.initialStock') }}:</span> {{ lotDetails.initial_quantity ?? 0 }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.minStockThreshold') }}:</span> {{ lotDetails.barang_min_stock_threshold !== null && lotDetails.barang_min_stock_threshold !== undefined ? `${lotDetails.barang_min_stock_threshold} ${lotDetails.barang_uom || ''}`.trim() : '-' }}</p>
                    <p class="text-foreground">{{ t('inventory.location') }}: {{ formatLocation(lotDetails) }}</p>
                    <p class="text-foreground">{{ t('inventory.poNumber') }}: {{ lotDetails.po_number }}</p>
                    <p class="text-foreground">{{ t('inventory.registrationDate') }}: {{ formatDate(lotDetails.date_of_receipt) }}</p>
                    <p class="text-foreground">{{ t('inventory.age') }}: {{ lotDetails.age !== undefined && lotDetails.age !== null ? `${lotDetails.age} ${t('inventory.yearUnit')}` : '-' }}</p>
                    <p class="text-foreground">{{ t('inventory.unitPrice') }}: {{ formatRupiah(lotDetails.unitPrice) }}</p>
                    <p class="text-foreground">{{ t('inventory.burden') }}: {{ lotDetails.burden || '-' }}</p>
                    <p v-if="lotDetails.burden === 'Project'" class="text-foreground">{{ t('inventory.project') }}: {{ lotDetails.project_no ? `${lotDetails.project_no} (${lotDetails.project_name || '-'})` : '-' }}</p>
                    <p class="text-foreground">{{ t('inventory.organizer') }}: {{ lotDetails.organizer }}</p>
                    <p class="text-foreground">{{ t('inventory.vendor') }}: {{ lotDetails.vendor }}</p>
                    <p class="text-foreground">{{ t('inventory.lastUpdate') }}: {{ lotDetails.updated_at }}</p>
                  </div>
                </div>
              </div>

              <div v-else-if="lotDetails && detailActiveTab === 'Permintaan'" class="py-1">
                <FormulirPermintaanBarangHabisPakai
                  mode="lot"
                  :lot="lotDetails"
                  :available-stock="lotDetails.current_quantity ?? 0"
                  @success="handleRequestSuccess"
                  @cancel="emit('close')"
                />
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
