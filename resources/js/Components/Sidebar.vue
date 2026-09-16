<script setup lang="ts">
/**
 * Application Sidebar component providing role-aware navigation menus, badges, and responsive mobile drawer.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronRight, LogOut, PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next';
import { mainNavigation, userNavigation, type NavItem, type NavSection } from '@/config/navigation';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Badge } from '@/Components/ui/badge';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/Components/ui/tooltip';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSelector from '@/Components/LanguageSelector.vue';

interface Props {
  open: boolean;
  isMobile: boolean;
  collapsed?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
});

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'toggle-collapse'): void;
}>();

const page = usePage();
const { t, te, locale } = useI18n();

// Determine if user is admin from shared props
const isAdmin = computed(() => (page.props.auth as { user: any; isAdmin?: boolean })?.isAdmin ?? false);
const isManager = computed(() => (page.props.auth as { user: any })?.user?.role === 'manager');
const isIfsManager = computed(() => (page.props.auth as { user: any })?.user?.role === 'ifs_manager');

// Select navigation based on user role
const navigation = computed<NavSection[]>(() => {
  let sections: NavSection[] = [];

  if (isIfsManager.value) {
    // Manager IFS:
    // Phase 1 views:
    // - Dashboard (/smart/dashboard)
    // - Approval Status (/smart/approve-status)
    // - History Approval Status (/smart/approve-status?history=true)
    const menuUtama = mainNavigation.find(section => section.title === 'MENU UTAMA');
    const approvalStatus = userNavigation.find(section => section.title === 'APPROVAL PENGHAPUSAN');
    
    // ==========================================
    // [PHASE 2 - IFS MANAGER EXTRA MENUS]
    // Uncomment below when transitioning to Phase 2
    // ==========================================
    /*
    const approvalPermintaan = userNavigation.find(section => section.title === 'APPROVAL PEMINJAMAN');
    
    const hiddenIfsStockTitles = [
      'Manajemen Barang',
      'Daftar Pending Nonaktif',
      'Master Data',
      'Pindai Barcode',
    ];

    const restOfAdmin = mainNavigation
      .filter(section => section.title !== 'MENU UTAMA' && section.title !== 'Permintaan')
      .map(section => {
        if (section.title === 'STOK') {
          return {
            ...section,
            items: section.items.filter(item => !hiddenIfsStockTitles.includes(item.title)),
          };
        }
        return section;
      });
    */
    // ==========================================
    
    const auditSectionRaw = mainNavigation.find(section => section.id === 'audit' || section.title === 'AUDIT');
    const ifsAudit = auditSectionRaw ? {
      ...auditSectionRaw,
      items: auditSectionRaw.items.filter(item => item.id === 'inventory_audit'),
    } : null;

    sections = [
      menuUtama,
      approvalStatus,
      ifsAudit,
      // ==========================================
      // [PHASE 2 - IFS EXTRA SECTIONS]
      // approvalPermintaan,
      // ...restOfAdmin
      // ==========================================
    ].filter((section): section is NavSection => !!section);
  } else if (isAdmin.value) {
    // Admin:
    // - Menu Utama
    // - Inventory
    // - Permintaan
    // - Audit
    sections = mainNavigation;
  }
  // ==========================================
  // [PHASE 2 - REGULAR MANAGER & REGULAR USER NAVIGATION]
  // Uncomment below when transitioning to Phase 2
  // ==========================================
  /*
  else if (isManager.value) {
    // Manager:
    // - Menu Utama
    // - Approval Permintaan
    // - Permintaan
    const menuUtama = userNavigation.find(section => section.title === 'MENU UTAMA');
    const approvalPermintaan = userNavigation.find(section => section.title === 'APPROVAL PEMINJAMAN');
    const permintaan = userNavigation.find(section => section.title === 'Permintaan');
    
    sections = [
      menuUtama,
      approvalPermintaan,
      permintaan,
    ].filter((section): section is NavSection => !!section);
  } else {
    // User:
    // - Menu Utama
    // - Permintaan
    const menuUtama = userNavigation.find(section => section.title === 'MENU UTAMA');
    const permintaan = userNavigation.find(section => section.title === 'Permintaan');
    
    sections = [
      menuUtama,
      permintaan,
    ].filter((section): section is NavSection => !!section);
  }
  */
  // ==========================================

  // Get dynamic counts from shared Inertia page props
  const pendingRequestCount = (page.props.auth as any)?.pendingRequestCount ?? 0;
  const pendingAssetStatusCount = (page.props.auth as any)?.pendingAssetStatusCount ?? 0;
  const pendingAdminApprovedCount = (page.props.auth as any)?.pendingAdminApprovedCount ?? 0;
  const activeRequestsCount = (page.props.auth as any)?.activeRequestsCount ?? pendingAdminApprovedCount;

  // Map the navigation items to inject badges dynamically
  return sections.map(section => ({
    ...section,
    items: section.items.map(item => {
      let badge = item.badge;
      
      // ==========================================
      // [PHASE 2 - REGULAR MANAGER PENDING REQUEST BADGE]
      // if (item.href === '/smart/approve') {
      //   badge = pendingRequestCount > 0 ? pendingRequestCount : undefined;
      // } else
      // ==========================================
      if (item.href === '/smart/approve-status') {
        badge = pendingAssetStatusCount > 0 ? pendingAssetStatusCount : undefined;
      } else if (item.href === '/smart/requests' || item.href === '/smart/inbox') {
        badge = activeRequestsCount > 0 ? activeRequestsCount : undefined;
      }
      
      return {
        ...item,
        badge
      };
    })
  }));
});

const parseUrlPathAndQuery = (urlStr: string) => {
  const [cleanUrl] = urlStr.split('#');
  const [pathname, queryString] = cleanUrl.split('?');
  const params = new URLSearchParams(queryString || '');
  return { pathname, params };
};

const isActive = (href: string): boolean => {
  const current = parseUrlPathAndQuery(page.url);
  const target = parseUrlPathAndQuery(href);

  // If target specified query params (like history=true), ensure current URL matches them
  for (const [key, value] of target.params.entries()) {
    if (current.params.get(key) !== value) {
      return false;
    }
  }

  // If current URL has history=true but target does not specify history, it shouldn't match
  // (differentiates "Perlu Approval" /smart/approve-status from "Sudah Diproses" /smart/approve-status?history=true)
  if (current.params.get('history') === 'true' && target.params.get('history') !== 'true') {
    return false;
  }

  // Prevent parent /smart/inventory from activating on separate sub-pages
  if (target.pathname === '/smart/inventory' && (
    current.pathname.startsWith('/smart/inventory/assets') ||
    current.pathname.startsWith('/smart/inventory/stok-habis-pakai') ||
    current.pathname.startsWith('/smart/inventory/pending-nonaktif')
  )) {
    return false;
  }

  // Check exact pathname match
  if (current.pathname === target.pathname) {
    return true;
  }

  // Active requests unified section (/smart/requests) matches its child workflow routes
  if (target.pathname === '/smart/requests') {
    const activeRequestPrefixes = [
      // ==========================================
      // [PHASE 2 - ACTIVE REQUEST CHILD WORKFLOWS]
      // '/smart/fulfillment',
      // '/smart/inbox',
      // '/smart/partial',
      // '/smart/handover',
      // '/smart/returns',
      // ==========================================
      '/smart/borrowed',
    ];
    if (activeRequestPrefixes.some(prefix => current.pathname === prefix || current.pathname.startsWith(prefix + '/'))) {
      return true;
    }
  }

  // Check nested child paths (e.g. /smart/inbox/123 -> /smart/inbox)
  return current.pathname.startsWith(target.pathname + '/');
};
</script>

<template>
  <!-- Desktop Sidebar -->
  <aside
    v-if="!isMobile"
    class="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:top-[68px] lg:z-30 border-r border-border bg-sidebar transition-[width] duration-300 ease-in-out"
    :class="[
      collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64'
    ]"
  >
    <ScrollArea class="flex-1 py-4">
      <TooltipProvider :delay-duration="100">
        <nav class="space-y-6" :class="[collapsed ? 'px-2' : 'px-3']">
          <div v-for="(section, sectionIndex) in navigation" :key="sectionIndex">
            <!-- Section Title / Divider -->
            <div v-if="section.title">
              <h3 
                v-if="!collapsed"
                class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground truncate"
              >
                {{ section.titleKey && te(section.titleKey) ? t(section.titleKey) : section.title }}
              </h3>
              <div 
                v-else-if="sectionIndex > 0" 
                class="my-3 mx-2 border-t border-sidebar-border/70"
              />
            </div>
            
            <!-- Nav Items -->
            <div class="space-y-1">
              <div
                v-for="(item, itemIndex) in section.items"
                :key="itemIndex"
              >
                <!-- When Collapsed: Wrapped in Tooltip -->
                <Tooltip v-if="collapsed">
                  <TooltipTrigger as-child>
                    <Link
                      :href="item.href"
                      class="group relative flex items-center justify-center rounded-lg p-2.5 text-sm font-medium transition-all duration-200"
                      :class="[
                        isActive(item.href)
                          ? 'bg-gradient-primary text-white shadow-button'
                          : 'text-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                      ]"
                    >
                      <component 
                        :is="item.icon" 
                        class="h-5 w-5 flex-shrink-0 transition-transform group-hover:scale-110" 
                      />
                      <!-- Badge indicator dot for collapsed mode -->
                      <span 
                        v-if="item.badge" 
                        class="absolute top-1.5 right-1.5 flex h-2 w-2"
                      >
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                      </span>
                    </Link>
                  </TooltipTrigger>
                  <TooltipContent side="right" :side-offset="8" class="flex items-center gap-2">
                    <span>{{ item.titleKey && te(item.titleKey) ? t(item.titleKey) : item.title }}</span>
                    <Badge 
                      v-if="item.badge" 
                      class="text-xs bg-primary/20 text-primary border-primary/30"
                    >
                      {{ item.badge }}
                    </Badge>
                  </TooltipContent>
                </Tooltip>

                <!-- When Expanded: Standard Link with Full Content -->
                <Link
                  v-else
                  :href="item.href"
                  class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200"
                  :class="[
                    isActive(item.href)
                      ? 'bg-gradient-primary text-white shadow-button'
                      : 'text-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                  ]"
                >
                  <component 
                    :is="item.icon" 
                    class="h-5 w-5 flex-shrink-0 transition-transform group-hover:scale-110" 
                  />
                  <span class="flex-1 truncate">{{ item.titleKey && te(item.titleKey) ? t(item.titleKey) : item.title }}</span>
                  <Badge 
                    v-if="item.badge" 
                    :class="[
                      'text-xs',
                      isActive(item.href) 
                        ? 'bg-white/90 text-primary hover:bg-white' 
                        : 'bg-primary/10 text-primary hover:bg-primary/20'
                    ]"
                  >
                    {{ item.badge }}
                  </Badge>
                  <ChevronRight 
                    v-if="item.children" 
                    class="h-4 w-4 opacity-50 transition-transform group-hover:translate-x-0.5" 
                  />
                </Link>
              </div>
            </div>
          </div>
        </nav>
      </TooltipProvider>
    </ScrollArea>
    
    <!-- Sidebar Footer -->
    <div class="p-3 border-t border-sidebar-border space-y-1">
      <TooltipProvider :delay-duration="100">
        <!-- Collapse / Expand Toggle Button -->
        <Tooltip v-if="collapsed">
          <TooltipTrigger as-child>
            <button
              data-testid="sidebar-collapse-toggle"
              type="button"
              class="flex w-full items-center justify-center rounded-lg p-2.5 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-sidebar-accent transition-all duration-200 cursor-pointer"
              @click="emit('toggle-collapse')"
              :aria-label="t('common.sidebar.expand')"
            >
              <PanelLeftOpen class="h-5 w-5" />
            </button>
          </TooltipTrigger>
          <TooltipContent side="right" :side-offset="8">
            {{ t('common.sidebar.expand') }}
          </TooltipContent>
        </Tooltip>

        <button
          v-else
          data-testid="sidebar-collapse-toggle"
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-sidebar-accent transition-all duration-200 cursor-pointer"
          @click="emit('toggle-collapse')"
          :aria-label="t('common.sidebar.collapse')"
        >
          <PanelLeftClose class="h-5 w-5 flex-shrink-0" />
          <span class="truncate">{{ t('common.sidebar.collapse') }}</span>
        </button>

        <!-- Logout Button -->
        <Tooltip v-if="collapsed">
          <TooltipTrigger as-child>
            <Link 
              :href="route('logout')" 
              method="post" 
              as="button"
              class="flex w-full items-center justify-center rounded-lg p-2.5 text-sm font-medium text-red-600 transition-all duration-200 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer"
              :aria-label="$t('common.userMenu.logout')"
            >
              <LogOut class="h-5 w-5" />
            </Link>
          </TooltipTrigger>
          <TooltipContent side="right" :side-offset="8">
            {{ $t('common.userMenu.logout') }}
          </TooltipContent>
        </Tooltip>

        <Link 
          v-else
          :href="route('logout')" 
          method="post" 
          as="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition-all duration-200 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer"
        >
          <LogOut class="h-5 w-5 flex-shrink-0" />
          <span class="truncate">{{ $t('common.userMenu.logout') }}</span>
        </Link>
      </TooltipProvider>
    </div>
  </aside>
  
  <!-- Mobile Sidebar (Sheet) -->
  <Sheet :open="open && isMobile" @update:open="(val) => !val && emit('close')">
    <SheetContent side="left" class="w-[280px] max-w-[85vw] p-0 bg-sidebar flex flex-col h-full">
      <SheetHeader class="px-4 h-14 !flex-row items-center justify-start text-left border-b border-sidebar-border flex-shrink-0">
        <SheetTitle class="flex items-center gap-2 text-left">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg overflow-hidden shrink-0">
            <ApplicationLogo class="h-full w-full object-contain" />
          </div>
          <span class="font-bold text-xl text-gradient-primary leading-none">SMART</span>
        </SheetTitle>
        <SheetDescription class="sr-only">
          Navigasi utama untuk aplikasi SMART.
        </SheetDescription>
      </SheetHeader>
      
      <!-- Scrollable Menu Area -->
      <div class="flex-1 overflow-hidden">
        <ScrollArea class="h-full">
          <nav class="px-3 space-y-6">
          <div v-for="(section, sectionIndex) in navigation" :key="sectionIndex">
            <!-- Section Title -->
            <h3 
              v-if="section.title" 
              class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
            >
              {{ section.titleKey && te(section.titleKey) ? t(section.titleKey) : section.title }}
            </h3>
            
            <!-- Nav Items -->
            <div class="space-y-1">
              <Link
                v-for="(item, itemIndex) in section.items"
                :key="itemIndex"
                :href="item.href"
                @click="emit('close')"
                class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200"
                :class="[
                  isActive(item.href)
                    ? 'bg-gradient-primary text-white shadow-button'
                    : 'text-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                ]"
              >
                <component 
                  :is="item.icon" 
                  class="h-5 w-5 flex-shrink-0 transition-transform group-hover:scale-110" 
                />
                <span class="flex-1">{{ item.titleKey && te(item.titleKey) ? t(item.titleKey) : item.title }}</span>
                <Badge 
                  v-if="item.badge" 
                  :class="[
                    'text-xs',
                    isActive(item.href) 
                    ? 'bg-white/90 text-primary hover:bg-white' 
                    : 'bg-primary/10 text-primary hover:bg-primary/20'
                  ]"
                >
                  {{ item.badge }}
                </Badge>
              </Link>
            </div>
          </div>
        </nav>
      </ScrollArea>
      </div>
      
      <!-- Fixed Language Selector & Logout in Mobile Footer -->
      <div class="p-4 border-t border-sidebar-border flex-shrink-0 bg-sidebar space-y-2">
        <div class="flex items-center justify-between px-1">
          <span class="text-xs font-medium text-muted-foreground">{{ $t('common.selectLanguage') }}</span>
          <LanguageSelector />
        </div>
        <Link 
          :href="route('logout')" 
          method="post" 
          as="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition-all duration-200 hover:bg-red-50 cursor-pointer"
        >
          <LogOut class="h-5 w-5" />
          {{ $t('common.userMenu.logout') }}
        </Link>
      </div>
    </SheetContent>
  </Sheet>
</template>
