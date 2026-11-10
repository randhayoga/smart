<script setup lang="ts">
/**
 * Admin Multi-Roles Dashboard
 * Features: Classic Inventory Overview, Consumable Stock Movement Analytics, and WIP tabs.
 */
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';
import Tabs from '@/Components/Tabs.vue';
import ClassicDashboardTab from './Tabs/ClassicDashboardTab.vue';
import ConsumableMovementTab from './Tabs/ConsumableMovementTab.vue';
import { usePermissions } from '@/composables/usePermissions';
import { QrCode, ChevronRight } from 'lucide-vue-next';

interface ConsumableStat {
  subcategory_name: string;
  total_quantity: string | number;
}

interface CategoryStat {
  category_name: string;
  total_units: number;
}

interface Props {
  user: {
    name: string;
    email: string;
  };
  consumableSubcategoryStats?: ConsumableStat[];
  cfsCategoryStats?: CategoryStat[];
  ictCategoryStats?: CategoryStat[];
  stockMovementFilters?: any;
  stockMovementData?: any;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { can } = usePermissions();
const page = usePage();

const canScanBarcode = computed(() => can(['inventory.manage', 'inventory.borrow']));

type TabId = 'classic' | 'stock_movement' | 'wip';
const activeTab = ref<TabId>('classic');

const tabItems = computed(() => [
  { id: 'classic', label: t('admin.classicTab') },
  { id: 'stock_movement', label: t('admin.stockMovementTab') },
  { id: 'wip', label: t('admin.wipTab') },
]);

// Initialize active tab from URL query param if present
onMounted(() => {
  const url = new URL(page.url, window.location.origin);
  const tabParam = url.searchParams.get('tab');
  if (tabParam === 'stock_movement' || tabParam === 'consumable_movement') {
    activeTab.value = 'stock_movement';
  } else if (tabParam === 'wip') {
    activeTab.value = 'wip';
  }
});

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) return t('common.greetings.morning');
  if (hour >= 11 && hour < 15) return t('common.greetings.afternoon');
  if (hour >= 15 && hour < 19) return t('common.greetings.evening');
  return t('common.greetings.night');
});
</script>

<template>
  <AppLayout :title="t('nav.items.dashboard')">
    <div class="space-y-3">
      <!-- Header -->
      <div class="pb-1">
        <Heading as="h1">
          {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
        </Heading>
      </div>

      <!-- Quick Pindai Barcode Shortcut Button (Visible on Mobile & Tablet screens < lg) -->
      <div v-if="canScanBarcode" class="lg:hidden w-full">
        <Link
          href="/smart/scan"
          class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-gradient-primary text-white shadow-md hover:shadow-lg transition-all duration-200 active:scale-[0.99] group"
        >
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-white/20 backdrop-blur-sm rounded-lg text-white shrink-0">
              <QrCode class="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <p class="font-semibold text-base leading-tight">
                {{ t('admin.scanBarcodeTitle') }}
              </p>
              <p class="text-xs text-white/80 mt-0.5">
                {{ t('admin.scanBarcodeSubtitle') }}
              </p>
            </div>
          </div>
          <div class="p-1.5 bg-white/10 rounded-full group-hover:translate-x-1 transition-transform shrink-0">
            <ChevronRight class="w-5 h-5 text-white" />
          </div>
        </Link>
      </div>

      <!-- Tabs Navigation -->
      <Tabs v-model="activeTab" :tabs="tabItems" />

      <!-- Tab 1: Classic Dashboard -->
      <ClassicDashboardTab
        v-if="activeTab === 'classic'"
        :consumable-subcategory-stats="consumableSubcategoryStats"
        :cfs-category-stats="cfsCategoryStats"
        :ict-category-stats="ictCategoryStats"
      />

      <!-- Tab 2: Consumable Stock Movement Dashboard -->
      <ConsumableMovementTab
        v-else-if="activeTab === 'stock_movement'"
        :filters="stockMovementFilters"
        :movement-data="stockMovementData"
      />

      <!-- Tab 3: WIP Notice -->
      <div
        v-else-if="activeTab === 'wip'"
        class="p-12 bg-card rounded-xl border border-border shadow-sm flex items-center justify-center min-h-[400px]"
      >
        <p class="text-muted-foreground text-base font-medium">
          {{ t('admin.wipNotice') }}
        </p>
      </div>
    </div>
  </AppLayout>
</template>
