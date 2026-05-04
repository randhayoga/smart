<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, LogOut } from 'lucide-vue-next';
import { mainNavigation, userNavigation, type NavItem, type NavSection } from '@/config/navigation';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Badge } from '@/Components/ui/badge';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

interface Props {
  open: boolean;
  isMobile: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'close'): void;
}>();

const page = usePage();

// Determine if user is admin from shared props
const isAdmin = computed(() => (page.props.auth as { user: any; isAdmin?: boolean })?.isAdmin ?? false);

// Select navigation based on user role
const navigation = computed(() => isAdmin.value ? mainNavigation : userNavigation);

const isActive = (href: string): boolean => {
  const currentPath = page.url;
  return currentPath === href || currentPath.startsWith(href + '/');
};
</script>

<template>
  <!-- Desktop Sidebar -->
  <aside
    v-if="!isMobile"
    class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 lg:top-[68px] lg:z-30 border-r border-border bg-sidebar"
  >
    <ScrollArea class="flex-1 py-4">
      <nav class="px-3 space-y-6">
        <div v-for="(section, sectionIndex) in navigation" :key="sectionIndex">
          <!-- Section Title -->
          <h3 
            v-if="section.title" 
            class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ section.title }}
          </h3>
          
          <!-- Nav Items -->
          <div class="space-y-1">
            <Link
              v-for="(item, itemIndex) in section.items"
              :key="itemIndex"
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
              <span class="flex-1">{{ item.title }}</span>
              <Badge 
                v-if="item.badge" 
                :class="[
                  'text-xs',
                  isActive(item.href) 
                    ? 'bg-white/20 text-white hover:bg-white/30' 
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
      </nav>
    </ScrollArea>
    
    <!-- Sidebar Footer -->
    <div class="p-4 border-t border-sidebar-border">
      <Link 
        :href="route('logout')" 
        method="post" 
        as="button"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition-all duration-200 hover:bg-red-50 cursor-pointer"
      >
        <LogOut class="h-5 w-5" />
        Keluar
      </Link>
    </div>
  </aside>
  
  <!-- Mobile Sidebar (Sheet) -->
  <Sheet :open="open && isMobile" @update:open="(val) => !val && emit('close')">
    <SheetContent side="left" class="w-[280px] max-w-[85vw] p-0 bg-sidebar flex flex-col h-full">
      <SheetHeader class="p-4 border-b border-sidebar-border flex-shrink-0">
        <SheetTitle class="flex items-center gap-2">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg overflow-hidden">
            <ApplicationLogo class="h-full w-full object-contain" />
          </div>
          <span class="font-bold text-xl text-gradient-primary">SMART</span>
        </SheetTitle>
      </SheetHeader>
      
      <!-- Scrollable Menu Area -->
      <div class="flex-1 overflow-hidden">
        <ScrollArea class="h-full py-4">
          <nav class="px-3 space-y-6">
          <div v-for="(section, sectionIndex) in navigation" :key="sectionIndex">
            <!-- Section Title -->
            <h3 
              v-if="section.title" 
              class="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
            >
              {{ section.title }}
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
                <span class="flex-1">{{ item.title }}</span>
                <Badge 
                  v-if="item.badge" 
                  :class="[
                    'text-xs',
                    isActive(item.href) 
                      ? 'bg-white/20 text-white hover:bg-white/30' 
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
      
      <!-- Fixed Logout Button at Bottom -->
      <div class="p-4 border-t border-sidebar-border flex-shrink-0 bg-sidebar">
        <Link 
          :href="route('logout')" 
          method="post" 
          as="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition-all duration-200 hover:bg-red-50 cursor-pointer"
        >
          <LogOut class="h-5 w-5" />
          Log Out
        </Link>
      </div>
    </SheetContent>
  </Sheet>
</template>
