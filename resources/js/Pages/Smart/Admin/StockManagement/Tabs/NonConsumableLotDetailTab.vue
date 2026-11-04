<script setup lang="ts">
/**
 * Detail LOT Non-Consumable Tab component rendering non-consumable lot overview and its associated individual asset units.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, ExternalLink } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import { formatDate, formatLocation } from '@/lib/utils';
import AssetPhotoGrid from '@/Components/AssetPhotoGrid.vue';
import CreateAssetModal from '../Modals/CreateAssetModal.vue';
import NonConsumableListTab from './NonConsumableListTab.vue';

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
    age?: number | null;
    burden?: string;
    project_id?: number | null;
    project_name?: string | null;
    project_no?: string | null;
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
    barang_subcategory_code?: string;
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
  locations: any[];
  floors?: any[];
  rooms?: any[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
  projects?: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const isComp = computed(() => {
  return props.lot.barang_category_code === 'COMP' ||
    props.lot.barang_category === 'Computer' ||
    props.lot.barang_category === 'COMP';
});

const isCreateAssetModalOpen = ref(false);

const openCreateAssetModal = () => {
  isCreateAssetModalOpen.value = true;
};

const handleAssetSuccess = () => {
  // Page reload happens via Inertia, nothing else is needed here
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
  return `Rp${formatted}`;
};

const distinctPhotos = computed(() => {
  if (props.lot?.imageUrl) return [props.lot.imageUrl];
  const photos: string[] = [];
  const seen = new Set<string>();
  for (const u of props.units || []) {
    if (u.image_url && !seen.has(u.image_url)) {
      seen.add(u.image_url);
      photos.push(u.image_url);
      if (photos.length === 4) break;
    }
  }
  return photos;
});

const lotCategoryWithSubcategory = computed(() => {
  const cat = props.lot?.barang_category;
  const sub = props.lot?.barang_subcategory;
  if (cat && sub && cat !== '-' && sub !== '-') {
    return `${cat} (${sub})`;
  }
  return cat || sub || '-';
});

const lotModelDisplay = computed(() => {
  const brand = props.lot?.barang_brand && props.lot?.barang_brand !== '-' ? props.lot.barang_brand : '';
  const name = props.lot?.barang_nama && props.lot?.barang_nama !== '-' ? props.lot.barang_nama : '';
  const spec = props.lot?.barang_specification && props.lot?.barang_specification !== '-' ? props.lot.barang_specification : '';
  return [brand, name, spec].filter(Boolean).join(' ') || '-';
});

const availableUnitsCount = computed(() => {
  return (props.units || []).filter(u => u.status === 'Tersedia').length;
});

const totalUnitsCount = computed(() => {
  return (props.units || []).length;
});

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateAssetModalOpen.value) {
      isCreateAssetModalOpen.value = false;
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
  <div class="space-y-4">
    <!-- Detail LOT Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">{{ t('inventory.lotDetail') }}</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <!-- Photo Grid (1, 2, or 4 images from in-memory units) -->
        <AssetPhotoGrid :photos="distinctPhotos" />

        <!-- Details Single Column (takes full horizontal space) -->
        <div class="flex-grow flex flex-col gap-1.5 text-foreground">
          <!-- 1. Nomor LOT -->
          <p class="font-bold text-foreground">
            <span class="text-foreground">{{ t('inventory.lotCode') }}:</span> {{ props.lot.number }}
          </p>

          <!-- 2. Kategori (shows category + type) -->
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
            {{ availableUnitsCount }} {{ props.lot.barang_uom || '' }} / {{ totalUnitsCount }} {{ props.lot.barang_uom || '' }}
          </p>

          <!-- 6. Nomor PO/Nomor Memo (dynamic) -->
          <p class="text-foreground">
            <span class="text-foreground">{{ props.lot.entry_type === 'Hibah' ? t('inventory.memoNumber') : t('inventory.poNumber') }}:</span>
            <template v-if="props.lot.entry_type === 'Hibah'">
              <a 
                v-if="props.lot.memo_url"
                :href="'/media/' + props.lot.memo_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary hover:underline font-semibold inline-flex items-center gap-1 ml-1"
              >
                <span>{{ props.lot.memo_number || '-' }}</span>
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
              <span v-else class="ml-1">{{ props.lot.memo_number || '-' }}</span>
            </template>
            <span v-else class="ml-1">{{ props.lot.po_number || '-' }}</span>
          </p>

          <!-- 7. Tanggal Registrasi/Tanggal Hibah (dynamic) -->
          <p class="text-foreground">
            <span class="text-foreground">{{ props.lot.entry_type === 'Hibah' ? t('inventory.grantDate') : t('inventory.registrationDate') }}:</span>
            {{ formatDate(props.lot.entry_type === 'Hibah' ? (props.lot.date_of_grant || props.lot.date_of_receipt) : props.lot.date_of_receipt) }}
          </p>

          <!-- 8. Organizer -->
          <p class="text-foreground">
            <span class="text-foreground">{{ t('inventory.organizer') }}:</span> {{ props.lot.organizer || '-' }}
          </p>

          <!-- 11. Pembaruan Terakhir -->
          <p class="text-foreground">
            <span class="text-foreground">{{ t('inventory.lastUpdate') }}:</span> {{ props.lot.updated_at || '-' }}
          </p>
        </div>
      </div>
    </div>

    <!-- NonConsumableListTab Card using the unified component -->
    <NonConsumableListTab
      :units="props.units"
      :locations="props.locations"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :projects="props.projects"
      :hide-barang-columns="true"
      :lot="props.lot"
      :barang="{ category: props.lot.barang_category, category_code: props.lot.barang_category_code }"
      :show-error-modal="false"
      filter-variant="simple"
      status-scope="all"
    >
      <template #extra-actions>
        <Button @click="openCreateAssetModal" variant="primary" size="lg">
          <Plus class="w-4 h-4" />
          <span>{{ t('inventory.newAsset') }}</span>
        </Button>
      </template>
    </NonConsumableListTab>
  </div>

  <CreateAssetModal
    v-model:open="isCreateAssetModalOpen"
    :lot="props.lot"
    :units="props.units"
    :barang="{ category: props.lot.barang_category, category_code: props.lot.barang_category_code, subcategory_code: props.lot.barang_subcategory_code }"
    :locations="props.locations"
    :projects="props.projects"
    @success="handleAssetSuccess"
  />
</template>
