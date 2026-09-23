<script setup lang="ts">
/**
 * Admin Item Detail Page component displaying item specifications, associated batch LOTs, and registered asset units.
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from "@/Components/ui/button";
import { ClipboardList } from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import EditTipeModal from './Modals/EditTipeModal.vue';
import ManualRequestBarangModal from './Modals/ManualRequestBarangModal.vue';
import DetailBarangTab from './Tabs/DetailBarangTab.vue';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';
import DaftarLOTTab from './Tabs/DaftarLOTTab.vue';

interface Props {
  itemId: string | number;
  barang: {
    id: number;
    code: string;
    category: string;
    subcategory: string;
    brand: string;
    name: string;
    specification: string;
    lastUpdate: string;
    amount: number;
    image_url: string | null;
    uom: string;
    subcategory_id: number;
    category_id: number;
    brand_id: number;
    uom_id: number;
    is_consumable: boolean;
  };
  brands: { id: number; name: string; }[];
  uoms: { id: number; name: string; }[];
  lots: {
    id: number;
    number: string;
    po_number: string;
    date_of_receipt: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id: number;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    assetCount: number;
    availableAssetCount: number;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    updated_at: string;
  }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
  units?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  units: () => [],
});

const { t } = useI18n();

const tabs = computed(() => [
  { id: 'Detail', label: t('inventory.typeDetail') },
  { 
    id: props.barang.is_consumable ? 'Daftar LOT' : 'Daftar Aset', 
    label: props.barang.is_consumable ? t('inventory.lotList') : t('inventory.assetList') 
  }
]);
const activeTab = ref('Detail');

const totalStok = computed(() => {
  const isConsumable = props.barang.is_consumable;
  return (props.lots || []).reduce((acc, lot) => {
    const qty = isConsumable ? (lot.current_quantity ?? 0) : (lot.assetCount ?? 0);
    return acc + Number(qty);
  }, 0);
});

const isEditModalOpen = ref(false);
const openEditModal = () => {
  isEditModalOpen.value = true;
};

const isManualRequestModalOpen = ref(false);
const openManualRequestModal = () => {
  isManualRequestModalOpen.value = true;
};

// Delete Modal Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'barang' | 'lot'>('barang');
const itemsToDelete = ref<any[]>([]);
const processing = ref(false);

const deleteFields = computed(() => null);

const openDeleteModal = () => {
  deleteMode.value = 'barang';
  itemsToDelete.value = [{
    id: props.barang.id,
    code: props.barang.code,
    category: props.barang.category,
    subcategory: props.barang.subcategory,
    brand: props.barang.brand,
    name: props.barang.name,
    specification: props.barang.specification,
    lastUpdate: props.barang.lastUpdate,
    amount: totalStok.value,
  }];
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemsToDelete.value = [];
};

const handleConfirmDelete = () => {
  if (itemsToDelete.value.length === 0) return;

  const ids = itemsToDelete.value.map(item => item.id);
  
  if (deleteMode.value === 'barang') {
    router.delete(`/smart/inventory/barangs/${ids[0]}`, {
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
      onSuccess: () => {
        closeDeleteModal();
      }
    });
  }
};

// Permissions & Notifications
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const { can } = usePermissions();
const flashSuccess = computed(() => (page.props as any).flash?.success);

watch(flashSuccess, (newVal) => {
  if (newVal && (page.props as any).flash?.success) {
    toast.success(newVal);
    if ((page.props as any).flash) {
      (page.props as any).flash.success = null;
    }
  }
}, { immediate: true });

const flashError = computed(() => (page.props as any).flash?.error);
const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

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

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isManualRequestModalOpen.value) {
      isManualRequestModalOpen.value = false;
    } else if (isEditModalOpen.value) {
      isEditModalOpen.value = false;
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
  <AppLayout :title="t('inventory.typeDetail')">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">{{ t('inventory.inventoryManagement') }}</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ props.barang.code }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
      <Tabs v-model="activeTab" :tabs="tabs" />

      <div v-if="can('inventory.manage') || can('inventory.manual_request')" class="flex items-center gap-3">
        <Button
          v-if="can('inventory.manual_request') && props.barang.is_consumable"
          @click="openManualRequestModal"
          variant="warning"
          size="lg"
        >
          <ClipboardList class="w-4 h-4" />
          {{ t('inventory.manualRequest') }}
        </Button>
        <Button v-if="can('inventory.manage')" @click="openEditModal" variant="primary" size="lg">
          {{ t('inventory.editTypeDetail') }}
        </Button>
        <Button v-if="can('inventory.manage')" @click="openDeleteModal" variant="destructive" size="lg">
          {{ t('inventory.deleteType') }}
        </Button>
      </div>
    </div>

    <div class="space-y-4">
      <DetailBarangTab
        v-if="activeTab === 'Detail'"
        :barang="props.barang"
        :lots="props.lots"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :locations="props.locations"
        :projects="props.projects"
      />

      <DaftarLOTTab
        v-else-if="activeTab === 'Daftar LOT'"
        :barang="props.barang"
        :lots="props.lots"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :locations="props.locations"
        :projects="props.projects"
      />

      <DaftarAsetTab
        v-else-if="activeTab === 'Daftar Aset'"
        :units="props.units"
        :locations="props.locations"
        :hide-barang-columns="true"
      />
    </div>

    <EditTipeModal
      v-model:open="isEditModalOpen"
      :items="[props.barang]"
      :uoms="props.uoms"
      :brands="props.brands"
      :lots="props.lots"
    />

    <ManualRequestBarangModal
      v-if="props.barang.is_consumable"
      v-model:open="isManualRequestModalOpen"
      :barang="props.barang"
      :available-stock="totalStok"
    />

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="itemsToDelete.length"
      :item-name="t('inventory.itemType')"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
      :fields="deleteFields"
      :max-width-class="itemsToDelete.length === 1 ? 'max-w-2xl' : undefined"
      :processing="processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>
