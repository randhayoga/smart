<script setup lang="ts">
/**
 * Hasil Pindai page component displaying barcode scan details, mobile-friendly inline editing,
 * and asset maintenance recording.
 * Responsive tab bar switches between shortened text titles and Lucide icons on ultra-small screens.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Info, SquarePen, Wrench } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbList, BreadcrumbItem, BreadcrumbLink, BreadcrumbSeparator, BreadcrumbPage } from '@/Components/ui/breadcrumb';
import { usePermissions } from '@/composables/usePermissions';
import { hasAssetStatus } from '@/lib/assetStatus';
import ScanAssetDetailTab from './ScanResult/ScanAssetDetailTab.vue';
import ScanEditAssetTab from './ScanResult/ScanEditAssetTab.vue';
import ScanMaintenanceTab from './ScanResult/ScanMaintenanceTab.vue';

interface Props {
  asset: any;
  locations?: any[];
  projects?: any[];
  floors?: any[];
  rooms?: any[];
  lot?: any;
  barang?: any;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { can } = usePermissions();

const activeTab = ref<'detail' | 'edit' | 'maintain'>('detail');

const isMaintenanceAllowed = computed(() => {
  if (!props.asset?.status) return true;
  return !hasAssetStatus(props.asset.status, 'Tidak Aktif', props.asset.approval_status) &&
         !hasAssetStatus(props.asset.status, 'Dihapus', props.asset.approval_status);
});

const canMaintain = computed(() => {
  return can('inventory.maintenance') && isMaintenanceAllowed.value;
});

const handleMaintenanceSaved = (payload: { maintenance: any; assetCondition: string }) => {
  if (payload.assetCondition && props.asset) {
    props.asset.condition = payload.assetCondition;
  }
  activeTab.value = 'detail';
};
</script>

<template>
  <AppLayout :title="`${t('scanner.scanResult')} - ${props.asset.number}`">
    <div class="max-w-md mx-auto space-y-4">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink href="/smart/scan">{{ t('scanner.breadcrumb') }}</BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>{{ props.asset.number }}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <div class="space-y-4">
        <!-- Tab Navigation (CSS-only breakpoint: >= 380px text, < 380px icons) -->
        <div class="flex rounded-xl bg-muted p-1 mb-4 border border-border">
          <!-- Detail Tab -->
          <button
            type="button"
            @click="activeTab = 'detail'"
            class="flex-1 py-1.5 rounded-lg text-center text-sm font-bold transition-all duration-200 cursor-pointer flex items-center justify-center gap-1.5"
            :class="activeTab === 'detail' ? 'bg-card text-primary shadow-sm border border-primary' : 'text-muted-foreground hover:text-foreground'"
            :title="t('scanner.tabDetail')"
            :aria-label="t('scanner.tabDetail')"
          >
            <Info class="w-4 h-4 min-[380px]:hidden shrink-0" />
            <span class="max-[380px]:hidden">{{ t('scanner.tabDetail') }}</span>
          </button>

          <!-- Edit Tab -->
          <button
            type="button"
            @click="activeTab = 'edit'"
            class="flex-1 py-1.5 rounded-lg text-center text-sm font-bold transition-all duration-200 cursor-pointer flex items-center justify-center gap-1.5"
            :class="activeTab === 'edit' ? 'bg-card text-primary shadow-sm border border-primary' : 'text-muted-foreground hover:text-foreground'"
            :title="t('scanner.tabEdit')"
            :aria-label="t('scanner.tabEdit')"
          >
            <SquarePen class="w-4 h-4 min-[380px]:hidden shrink-0" />
            <span class="max-[380px]:hidden">{{ t('scanner.tabEdit') }}</span>
          </button>

          <!-- Maintain Tab (Conditional based on permissions & asset status) -->
          <button
            v-if="canMaintain"
            type="button"
            @click="activeTab = 'maintain'"
            class="flex-1 py-1.5 rounded-lg text-center text-sm font-bold transition-all duration-200 cursor-pointer flex items-center justify-center gap-1.5"
            :class="activeTab === 'maintain' ? 'bg-card text-primary shadow-sm border border-primary' : 'text-muted-foreground hover:text-foreground'"
            :title="t('scanner.tabMaintain')"
            :aria-label="t('scanner.tabMaintain')"
          >
            <Wrench class="w-4 h-4 min-[380px]:hidden shrink-0" />
            <span class="max-[380px]:hidden">{{ t('scanner.tabMaintain') }}</span>
          </button>
        </div>

        <!-- Tab: Detail -->
        <ScanAssetDetailTab
          v-if="activeTab === 'detail'"
          :asset="props.asset"
          :locations="props.locations"
          :lot="props.lot"
          :barang="props.barang"
          @edit="activeTab = 'edit'"
        />

        <!-- Tab: Edit -->
        <ScanEditAssetTab
          v-else-if="activeTab === 'edit'"
          :asset="props.asset"
          :locations="props.locations"
          :projects="props.projects"
          :lot="props.lot"
          :barang="props.barang"
          @cancel="activeTab = 'detail'"
          @success="activeTab = 'detail'"
        />

        <!-- Tab: Maintain -->
        <ScanMaintenanceTab
          v-else-if="activeTab === 'maintain' && canMaintain"
          :asset="props.asset"
          @cancel="activeTab = 'detail'"
          @saved="handleMaintenanceSaved"
        />
      </div>
    </div>
  </AppLayout>
</template>
