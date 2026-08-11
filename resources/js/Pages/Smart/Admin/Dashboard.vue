<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';
import Tabs from '@/Components/Tabs.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { DonutChart } from '@/Components/ui/chart';
import { StickyNote, Armchair, MonitorSmartphone, Package, Box, Monitor, PieChart as PieChartIcon, QrCode, ChevronRight } from 'lucide-vue-next';

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
}

const props = defineProps<Props>();

const tabs = ['Klasik', 'WIP'];
const activeTab = ref('Klasik');

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) return 'Selamat Pagi';
  if (hour >= 11 && hour < 15) return 'Selamat Siang';
  if (hour >= 15 && hour < 19) return 'Selamat Sore';
  return 'Selamat Malam';
});

const totalConsumablesQuantity = computed(() => {
  if (!props.consumableSubcategoryStats?.length) return 0;
  return props.consumableSubcategoryStats.reduce((sum, item) => sum + (Number(item.total_quantity) || 0), 0);
});

const totalCFSUnits = computed(() => {
  if (!props.cfsCategoryStats?.length) return 0;
  return props.cfsCategoryStats.reduce((sum, item) => sum + (Number(item.total_units) || 0), 0);
});

const totalICTUnits = computed(() => {
  if (!props.ictCategoryStats?.length) return 0;
  return props.ictCategoryStats.reduce((sum, item) => sum + (Number(item.total_units) || 0), 0);
});

// Formatted data for Donut / Pie Charts (filtering out 0 quantity/units items for clean rendering)
const consumableChartData = computed(() => {
  return (props.consumableSubcategoryStats || [])
    .map(item => ({
      subcategory_name: item.subcategory_name,
      total_quantity: Number(item.total_quantity) || 0,
    }))
    .filter(item => item.total_quantity > 0);
});

const cfsChartData = computed(() => {
  return (props.cfsCategoryStats || [])
    .map(item => ({
      category_name: item.category_name,
      total_units: Number(item.total_units) || 0,
    }))
    .filter(item => item.total_units > 0);
});

const ictChartData = computed(() => {
  return (props.ictCategoryStats || [])
    .map(item => ({
      category_name: item.category_name,
      total_units: Number(item.total_units) || 0,
    }))
    .filter(item => item.total_units > 0);
});

// Elegant, rich color palettes with high contrast against white background
const consumableChartColors = ['#6366F1', '#10B981', '#F59E0B', '#EC4899', '#0284C7', '#8B5CF6', '#EA580C', '#0D9488', '#E11D48'];
const cfsChartColors = ['#8B5CF6', '#0284C7', '#E11D48', '#10B981', '#F59E0B', '#6366F1', '#65A30D', '#EA580C'];
const ictChartColors = ['#0D9488', '#0284C7', '#F59E0B', '#E11D48', '#8B5CF6', '#10B981', '#6366F1', '#EC4899'];
</script>

<template>
  <AppLayout title="Dashboard">
    <div class="space-y-3">
      <!-- Header -->
      <div class="pb-1">
        <Heading as="h1">
          {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
        </Heading>
      </div>

      <!-- Quick Pindai Barcode Shortcut Button (Visible only on Mobile & Tablet screens < lg) -->
      <div class="lg:hidden w-full">
        <Link
          href="/smart/scan-barcode"
          class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-gradient-primary text-white shadow-md hover:shadow-lg transition-all duration-200 active:scale-[0.99] group"
        >
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-white/20 backdrop-blur-sm rounded-lg text-white shrink-0">
              <QrCode class="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <p class="font-semibold text-base leading-tight">
                Pindai Barcode Aset
              </p>
              <p class="text-xs text-white/80 mt-0.5">
                Klik untuk membuka kamera pemindai
              </p>
            </div>
          </div>
          <div class="p-1.5 bg-white/10 rounded-full group-hover:translate-x-1 transition-transform shrink-0">
            <ChevronRight class="w-5 h-5 text-white" />
          </div>
        </Link>
      </div>

      <!-- Tabs -->
      <Tabs v-model="activeTab" :tabs="tabs" />

      <!-- Main Card Container (Klasik) -->
      <div v-if="activeTab === 'Klasik'" class="p-6 bg-card rounded-xl border border-border shadow-sm overflow-hidden space-y-6">
        <!-- Overview Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
          <Card class="h-full min-w-0">
            <CardContent class="p-3.5 sm:p-4 md:p-5 flex items-center justify-between h-full gap-3 sm:gap-4">
              <div class="min-w-0 flex-1">
                <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">Total Stok Barang Habis Pakai</p>
                <h3 class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
                  {{ totalConsumablesQuantity.toLocaleString('id-ID') }}
                </h3>
              </div>
              <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-primary/10 rounded-lg text-primary shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
                <StickyNote class="h-full w-auto max-h-full aspect-square" />
              </div>
            </CardContent>
          </Card>

          <Card class="h-full min-w-0">
            <CardContent class="p-3.5 sm:p-4 md:p-5 flex items-center justify-between h-full gap-3 sm:gap-4">
              <div class="min-w-0 flex-1">
                <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">Jumlah Aset (CFS)</p>
                <h3 class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
                  {{ totalCFSUnits.toLocaleString('id-ID') }}
                </h3>
              </div>
              <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-primary/10 rounded-lg text-primary shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
                <Armchair class="h-full w-auto max-h-full aspect-square" />
              </div>
            </CardContent>
          </Card>

          <Card class="h-full min-w-0 sm:col-span-2 lg:col-span-1">
            <CardContent class="p-3.5 sm:p-4 md:p-5 flex items-center justify-between h-full gap-3 sm:gap-4">
              <div class="min-w-0 flex-1">
                <p class="text-xs sm:text-sm font-semibold text-muted-foreground leading-tight">Jumlah Aset (ICT)</p>
                <h3 class="text-xl sm:text-2xl font-bold text-foreground mt-1 tracking-tight truncate">
                  {{ totalICTUnits.toLocaleString('id-ID') }}
                </h3>
              </div>
              <div class="self-stretch flex items-center justify-center p-2 sm:p-2.5 bg-primary/10 rounded-lg text-primary dark:text-emerald-400 shrink-0 min-h-[3rem] sm:min-h-[3.5rem] max-h-16 sm:max-h-20">
                <MonitorSmartphone class="h-full w-auto max-h-full aspect-square" />
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Pie & Donut Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Consumables Pie Chart -->
          <Card class="flex flex-col justify-between">
            <CardHeader class="pb-2">
              <div class="flex items-center space-x-2">
                <CardTitle class="text-lg font-semibold">Subkategori Habis Pakai</CardTitle>
              </div>
              <CardDescription class="text-sm">Distribusi kuantitas berdasarkan subkategori</CardDescription>
            </CardHeader>
            <CardContent class="p-5 flex-1 flex items-center justify-center">
              <DonutChart
                v-if="consumableChartData.length"
                type="pie"
                :data="consumableChartData"
                index="subcategory_name"
                category="total_quantity"
                :colors="consumableChartColors"
                :value-formatter="(v) => `${v.toLocaleString('id-ID')}`"
              />
              <div v-else class="py-12 text-center text-muted-foreground text-sm flex flex-col items-center gap-2">
                <Package class="w-8 h-8 opacity-40" />
                <span>Tidak ada data tersedia.</span>
              </div>
            </CardContent>
          </Card>

          <!-- CFS Category Pie Chart -->
          <Card class="flex flex-col justify-between">
            <CardHeader class="pb-2">
              <div class="flex items-center space-x-2">
                <CardTitle class="text-lg font-semibold">Kategori CFS (Non-Habis Pakai)</CardTitle>
              </div>
              <CardDescription class="text-sm">Distribusi total unit berdasarkan kategori CFS</CardDescription>
            </CardHeader>
            <CardContent class="p-5 flex-1 flex items-center justify-center">
              <DonutChart
                v-if="cfsChartData.length"
                type="pie"
                :data="cfsChartData"
                index="category_name"
                category="total_units"
                :colors="cfsChartColors"
                :value-formatter="(v) => `${v.toLocaleString('id-ID')}`"
              />
              <div v-else class="py-12 text-center text-muted-foreground text-sm flex flex-col items-center gap-2">
                <Box class="w-8 h-8 opacity-40" />
                <span>Tidak ada data tersedia.</span>
              </div>
            </CardContent>
          </Card>

          <!-- ICT Category Pie Chart -->
          <Card class="flex flex-col justify-between">
            <CardHeader class="pb-2">
              <div class="flex items-center space-x-2">
                <CardTitle class="text-lg font-semibold">Kategori ICT (Non-Habis Pakai)</CardTitle>
              </div>
              <CardDescription class="text-sm">Distribusi total unit berdasarkan kategori ICT</CardDescription>
            </CardHeader>
            <CardContent class="p-5 flex-1 flex items-center justify-center">
              <DonutChart
                v-if="ictChartData.length"
                type="pie"
                :data="ictChartData"
                index="category_name"
                category="total_units"
                :colors="ictChartColors"
                :value-formatter="(v) => `${v.toLocaleString('id-ID')}`"
              />
              <div v-else class="py-12 text-center text-muted-foreground text-sm flex flex-col items-center gap-2">
                <Monitor class="w-8 h-8 opacity-40" />
                <span>Tidak ada data tersedia.</span>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Main Card Container (WIP) -->
      <div v-else-if="activeTab === 'WIP'" class="p-12 bg-card rounded-xl border border-border shadow-sm flex items-center justify-center min-h-[400px]">
        <p class="text-muted-foreground text-base font-medium">
          Sedang dalam tahap pengembangan
        </p>
      </div>
    </div>
  </AppLayout>
</template>
