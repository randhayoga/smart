<script setup lang="ts">
/**
 * Permintaan Aktif - Consolidated Active Requests Page
 * Combines Inbox, Perlu Alokasi, Parsial, Serah Terima, Lacak Peminjaman, and Pengembalian
 * following the Master Data design pattern with horizontal pill tabs and card enclosure.
 */
import { ref, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';
import Tabs from '@/Components/Tabs.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

// Tab Components
import InboxTab from './Tabs/InboxTab.vue';
import ConfirmedListTab from './Tabs/ConfirmedListTab.vue';
import PartialListTab from './Tabs/PartialListTab.vue';
import SerahTerimaTab from './Tabs/SerahTerimaTab.vue';
import BorrowedTab from './Tabs/BorrowedTab.vue';
import ReturnsTab from './Tabs/ReturnsTab.vue';

import type { SmartRequestData } from '@/types/request';

interface Props {
  user: any;
  activeTab?: string;
  inboxRequests: SmartRequestData[];
  confirmedRequests: any[];
  partialRequests: any[];
  handovers: any[];
  borrowedList: any[];
  returnsList: any[];
}

const props = withDefaults(defineProps<Props>(), {
  activeTab: 'Inbox',
  inboxRequests: () => [],
  confirmedRequests: () => [],
  partialRequests: () => [],
  handovers: () => [],
  borrowedList: () => [],
  returnsList: () => [],
});

const tabs = [
  'Inbox',
  'Perlu Alokasi',
  'Parsial',
  'Serah Terima',
  'Lacak Peminjaman',
  'Pengembalian'
];

// Mapping helper to resolve tabs from URL query case-insensitively
const resolveTab = (tabName?: string | null): string => {
  if (!tabName) return 'Inbox';
  const found = tabs.find(t => t.toLowerCase() === tabName.toLowerCase() || t.toLowerCase().replace(/\s+/g, '-') === tabName.toLowerCase());
  return found || 'Inbox';
};

const activeTab = ref(resolveTab(props.activeTab));

// Sync tab state with browser URL query
const page = usePage();

const syncTabFromUrl = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const tabParam = urlParams.get('tab');
  if (tabParam) {
    activeTab.value = resolveTab(tabParam);
  }
};

onMounted(() => {
  syncTabFromUrl();
});

watch(() => page.url, () => {
  syncTabFromUrl();
});

const tabPropMap: Record<string, string> = {
  'Inbox': 'inboxRequests',
  'Perlu Alokasi': 'confirmedRequests',
  'Parsial': 'partialRequests',
  'Serah Terima': 'handovers',
  'Lacak Peminjaman': 'borrowedList',
  'Pengembalian': 'returnsList'
};

watch(activeTab, (newTab) => {
  const currentUrl = new URL(window.location.href);
  if (currentUrl.searchParams.get('tab') !== newTab) {
    const propToFetch = tabPropMap[newTab];
    router.visit(route('smart.requests.index', { tab: newTab }), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: propToFetch ? [propToFetch, 'activeTab'] : ['activeTab'],
    });
  }
});
</script>

<template>
  <Head title="Permintaan Aktif" />

  <AppLayout title="Permintaan Aktif">
    <!-- Breadcrumb: Permintaan Aktif > -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink :href="route('smart.requests.index')">Permintaan Aktif</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-1">
      <!-- Tabs -->
      <Tabs v-model="activeTab" :tabs="tabs" />

      <!-- Main Card Enclosure (matching MasterData.vue) -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3">
          <Heading as="h2">Daftar {{ activeTab }}</Heading>

          <div class="mt-4">
            <!-- Inbox Tab -->
            <InboxTab 
              v-if="activeTab === 'Inbox'" 
              :requests="props.inboxRequests" 
            />

            <!-- Perlu Alokasi Tab -->
            <ConfirmedListTab 
              v-else-if="activeTab === 'Perlu Alokasi'" 
              :requests="props.confirmedRequests" 
            />

            <!-- Parsial Tab -->
            <PartialListTab 
              v-else-if="activeTab === 'Parsial'" 
              :requests="props.partialRequests" 
            />

            <!-- Serah Terima Tab -->
            <SerahTerimaTab 
              v-else-if="activeTab === 'Serah Terima'" 
              :handovers="props.handovers" 
            />

            <!-- Lacak Peminjaman Tab -->
            <BorrowedTab 
              v-else-if="activeTab === 'Lacak Peminjaman'" 
              :borrowed-list="props.borrowedList" 
            />

            <!-- Pengembalian Tab -->
            <ReturnsTab 
              v-else-if="activeTab === 'Pengembalian'" 
              :returns-list="props.returnsList" 
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
