<script setup lang="ts">
/**
 * Non-Consumable LOT Detail Page component presenting lot acquisition details, location mappings, and individual asset units.
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from "@/Components/ui/button";
import { formatDate } from '@/lib/utils';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import EditLotModal from './Modals/EditLotModal.vue';
import NonConsumableLotDetailTab from './Tabs/NonConsumableLotDetailTab.vue';
import NonConsumableListTab from './Tabs/NonConsumableListTab.vue';

interface Props {
  lot: {
    id: number;
    number: string;
    po_number?: string | null;
    date_of_receipt: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id?: number | null;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    burden?: string;
    updated_at: string;
    
    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_brand: string;
    barang_nama: string;
    barang_specification: string;
    barang_category: string;
    barang_category_code?: string;
    barang_subcategory: string;
    barang_uom: string;
  };
  units: {
    id: number;
    number: string;
    status: string;
    condition: string;
    type?: string;
    classification?: string;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
  }[];
  brands: { id: number; name: string; }[];
  uoms: { id: number; name: string; }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
  users?: { id: number; name: string; }[];
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const tabs = computed(() => [
  { id: 'Detail', label: t('inventory.lotDetail') },
  { id: 'Daftar Tidak Habis Pakai', label: t('inventory.nonConsumableList') }
]);
const activeTab = ref('Detail');

// Lot Edit Modal Setup
const isLotModalOpen = ref(false);
const openEditLotModal = () => {
  isLotModalOpen.value = true;
};

// Flash Notifications
const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const flashError = computed(() => (page.props as any).flash?.error);

watch(flashSuccess, (newVal) => {
  if (newVal && (page.props as any).flash?.success) {
    toast.success(newVal);
    if ((page.props as any).flash) {
      (page.props as any).flash.success = null;
    }
  }
}, { immediate: true });

watch(flashError, (newVal) => {
  if (newVal) {
    errorModalMessage.value = newVal;
    isErrorModalOpen.value = true;
  }
}, { immediate: true });

const closeErrorModal = () => {
  isErrorModalOpen.value = false;
  if ((page.props as any).flash) {
    (page.props as any).flash.error = null;
  }
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

// Deletion Modals Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'lot'>('lot');
const lotToDelete = ref<any>(null);
const processing = ref(false);

const deleteFields = computed(() => {
  if (deleteMode.value === 'lot') {
    const isComp = props.lot.category_code === 'COMP' || props.lot.category === 'Computer' || props.lot.category === 'COMP' || props.lot.barang_category === 'COMP';
    const isConsumable = props.lot.is_consumable ?? false;
    const availableStock = isConsumable ? (props.lot.current_quantity ?? 0) : (props.lot.availableAssetCount ?? (props.lot.assetCount ?? 0));
    const initialStock = isConsumable ? (props.lot.initial_quantity ?? 0) : (props.lot.assetCount ?? 0);

    return [
      { label: t('inventory.lotCode'), value: props.lot.number },
      { label: t('inventory.category'), value: props.lot.barang_category || props.lot.category || '-' },
      { label: t('inventory.subcategory'), value: props.lot.barang_subcategory || props.lot.subcategory || '-' },
      { label: t('inventory.brand'), value: props.lot.barang_brand || props.lot.brand || '-' },
      { label: t('inventory.name'), value: props.lot.barang_nama || props.lot.name || '-' },
      ...(!isComp ? [{ label: t('inventory.specification'), value: props.lot.barang_specification || props.lot.specification || '-' }] : []),
      { label: t('inventory.availableStock'), value: availableStock },
      { label: t('inventory.initialStock'), value: initialStock },
      { label: t('inventory.location'), value: formatLocation(props.lot.location, props.lot.floor, props.lot.room) },
      { label: t('inventory.poNumber'), value: props.lot.po_number || '-' },
      { label: t('inventory.registrationDate'), value: formatDate(props.lot.date_of_receipt) },
      { label: t('inventory.age'), value: props.lot.age !== undefined && props.lot.age !== null ? `${props.lot.age} ${t('inventory.yearUnit')}` : '-' },
      { label: t('inventory.defaultUnitPrice'), value: formatRupiah(props.lot.unitPrice) },
      { label: t('inventory.organizer'), value: props.lot.organizer || '-' },
      { label: t('inventory.vendor'), value: (props.lot.vendor_id && props.lot.vendor !== '-') ? props.lot.vendor : (props.lot.vendor || t('inventory.vendorMultiple')) },
      ...(isConsumable ? [{ label: t('inventory.burden'), value: props.lot.burden || '-' }] : []),
      { label: t('inventory.lastUpdate'), value: props.lot.updated_at || '-' }
    ];
  }
  return [];
});

const openDeleteLotModal = () => {
  deleteMode.value = 'lot';
  lotToDelete.value = props.lot;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
};

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const parentBarangUrl = computed(() => {
  const code = props.lot.barang_code ? props.lot.barang_code.replace(/[^a-zA-Z0-9]/g, '') : props.lot.barang_id;
  return `/smart/stock-management/${code}`;
});

const handleConfirmDelete = () => {
  if (deleteMode.value === 'lot') {
    router.delete(`/smart/stock-management/lots/${props.lot.id}?redirect_to=${encodeURIComponent(parentBarangUrl.value)}`, {
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
      onSuccess: () => {
        closeDeleteModal();
      },
      onError: (err) => {
        if ((page.props as any).flash?.error) {
          closeDeleteModal();
        }
      }
    });
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isLotModalOpen.value) {
      isLotModalOpen.value = false;
    } else if (isDeleteModalOpen.value) {
      closeDeleteModal();
    } else if (isErrorModalOpen.value) {
      closeErrorModal();
    }
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
  <AppLayout :title="t('inventory.lotDetail')">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/stock-management">{{ t('inventory.inventoryManagement') }}</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink :href="'/smart/stock-management/' + (props.lot.barang_code ? props.lot.barang_code.replace(/[^a-zA-Z0-9]/g, '') : props.lot.barang_id)">{{ props.lot.barang_code }}</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ props.lot.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
      <Tabs v-model="activeTab" :tabs="tabs" />

      <div class="flex items-center gap-3">
        <Button @click="openEditLotModal" variant="primary" size="lg">
          {{ t('inventory.editLotDetail') }}
        </Button>
        <Button @click="openDeleteLotModal" variant="destructive" size="lg">
          {{ t('inventory.deleteLot') }}
        </Button>
      </div>
    </div>

    <div class="space-y-4">
      <NonConsumableLotDetailTab
        v-if="activeTab === 'Detail'"
        :lot="props.lot"
        :units="props.units"
        :locations="props.locations"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :projects="props.projects"
      />

      <NonConsumableListTab
        v-else-if="activeTab === 'Daftar Tidak Habis Pakai'"
        :units="props.units"
        :locations="props.locations"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :projects="props.projects"
        :users="props.users"
        :hide-barang-columns="true"
        :lot="props.lot"
        :barang="{ category: props.lot.barang_category }"
        :show-error-modal="false"
        status-scope="all"
      />
    </div>

    <EditLotModal
      v-model:open="isLotModalOpen"
      :items="[props.lot]"
      :isConsumable="false"
      :uom="props.lot.barang_uom"
      :parentImageUrl="(page.props as any).lot?.parent_image || null"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :projects="props.projects"
    />

    <!-- Delete Confirmation Modal -->
    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="t('inventory.lotItem')"
      :item-data="lotToDelete"
      :fields="deleteFields"
      :max-width-class="'max-w-2xl'"
      :processing="processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <!-- Delete Error Modal -->
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>
