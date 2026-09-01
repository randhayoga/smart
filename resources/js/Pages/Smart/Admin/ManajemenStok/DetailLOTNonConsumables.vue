<script setup lang="ts">
/**
 * Non-Consumable LOT Detail Page component presenting lot acquisition details, location mappings, and individual asset units.
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from "@/Components/ui/button";
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import EditLotModal from './Modals/EditLotModal.vue';
import DetailLOTNCTab from './Tabs/DetailLOTNCTab.vue';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';

interface Props {
  lot: {
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
    barang_subcategory: string;
    barang_uom: string;
  };
  units: {
    id: number;
    number: string;
    status: string;
    condition: string;
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
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
  users?: { id: number; name: string; }[];
}

const props = defineProps<Props>();

const tabs = ['Detail', 'Daftar Aset'];
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

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

// Deletion Modals Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'lot'>('lot');
const lotToDelete = ref<any>(null);
const processing = ref(false);

const deleteFields = computed(() => {
  if (deleteMode.value === 'lot') {
    return [
      { label: 'Kode LOT', value: props.lot.number },
      { label: 'Lokasi', value: formatLocation(props.lot.location, props.lot.floor, props.lot.room) },
      { label: 'Tanggal registrasi', value: formatDateWithDashes(props.lot.date_of_receipt) },
      { label: 'Harga default', value: formatRupiah(props.lot.unitPrice) },
      { label: 'Pembebanan', value: props.lot.burden || '-' },
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

const handleConfirmDelete = () => {
  if (deleteMode.value === 'lot') {
    router.delete(`/smart/inventory/lots/${props.lot.id}`, {
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
  <AppLayout title="Detail LOT">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">Manajemen Barang</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink :href="'/smart/inventory/' + (props.lot.barang_code ? props.lot.barang_code.replace(/[^a-zA-Z0-9]/g, '') : props.lot.barang_id)">{{ props.lot.barang_code }}</BreadcrumbLink>
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
          Edit Detail LOT
        </Button>
        <Button @click="openDeleteLotModal" variant="destructive" size="lg">
          Hapus LOT
        </Button>
      </div>
    </div>

    <div class="space-y-4">
      <DetailLOTNCTab
        v-if="activeTab === 'Detail'"
        :lot="props.lot"
        :units="props.units"
        :locations="props.locations"
        :floors="props.floors"
        :rooms="props.rooms"
        :organizers="props.organizers"
        :vendors="props.vendors"
      />

      <DaftarAsetTab
        v-else-if="activeTab === 'Daftar Aset'"
        :units="props.units"
        :locations="props.locations"
        :floors="props.floors"
        :rooms="props.rooms"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :users="props.users"
        :hide-barang-columns="true"
        :lot="props.lot"
        :barang="{ category: props.lot.barang_category }"
      />
    </div>

    <EditLotModal
      v-model:open="isLotModalOpen"
      :items="[props.lot]"
      :isConsumable="false"
      :parentImageUrl="(page.props as any).lot?.parent_image || null"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      :projects="props.projects"
    />

    <!-- Delete Confirmation Modal -->
    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="'LOT'"
      :item-data="lotToDelete"
      :fields="deleteFields"
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
