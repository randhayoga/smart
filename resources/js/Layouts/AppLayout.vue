<script setup lang="ts">
/**
 * Main Application Layout wrapping Navbar, Sidebar, Toaster notifications, and background gradients.
 */
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import { Toaster } from '@/Components/ui/sonner';
import { useMercureNotifications } from '@/composables/useMercureNotifications';
import PageSkeleton from '@/Components/skeletons/PageSkeleton.vue';
import 'vue-sonner/style.css';

interface Props {
  title?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'SMART',
});

// Initialize global real-time notifications listener
useMercureNotifications();

const sidebarOpen = ref(false);
const isMobile = ref(false);

// Global navigation skeleton loading state
const isNavigating = ref(false);
const targetPath = ref('');
let navTimer: ReturnType<typeof setTimeout> | null = null;
let removeStartListener: (() => void) | null = null;
let removeFinishListener: (() => void) | null = null;

const checkMobile = () => {
  isMobile.value = window.innerWidth < 1024;
  if (!isMobile.value) {
    sidebarOpen.value = false;
  }
};

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
  sidebarOpen.value = false;
};

onMounted(() => {
  checkMobile();
  window.addEventListener('resize', checkMobile);

  // Hook into Inertia visit events to display realistic page skeleton
  removeStartListener = router.on('start', (event) => {
    try {
      const rawUrl = event.detail.visit?.url;
      const urlStr = typeof rawUrl === 'string'
        ? rawUrl
        : (rawUrl?.pathname || rawUrl?.href || String(rawUrl || ''));
      const urlObj = new URL(urlStr, window.location.origin);
      targetPath.value = urlObj.pathname;
    } catch {
      targetPath.value = window.location.pathname;
    }

    if (navTimer) clearTimeout(navTimer);
    navTimer = setTimeout(() => {
      isNavigating.value = true;
    }, 30);
  });

  removeFinishListener = router.on('finish', () => {
    if (navTimer) {
      clearTimeout(navTimer);
      navTimer = null;
    }
    isNavigating.value = false;
    targetPath.value = '';
  });
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
  if (navTimer) clearTimeout(navTimer);
  if (removeStartListener) removeStartListener();
  if (removeFinishListener) removeFinishListener();
});
</script>

<template>
  <div class="min-h-screen bg-background w-full overflow-x-hidden max-w-full">
    <Head :title="title" />
    
    <!-- Background decorative blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10">
      <div class="blob-primary w-96 h-96 -top-48 -left-48 opacity-20"></div>
      <div class="blob-secondary w-80 h-80 top-1/3 -right-40 opacity-15"></div>
      <div class="blob-primary w-64 h-64 bottom-0 left-1/4 opacity-10"></div>
    </div>
    
    <!-- Navbar -->
    <Navbar 
      :sidebar-open="sidebarOpen" 
      @toggle-sidebar="toggleSidebar" 
    />
    
    <!-- Sidebar -->
    <Sidebar 
      :open="sidebarOpen" 
      :is-mobile="isMobile"
      @close="closeSidebar"
    />
    
    <!-- Main content -->
    <main 
      class="transition-all duration-300 pt-[60px] sm:pt-[68px]"
      :class="[
        !isMobile ? 'lg:ml-64' : ''
      ]"
    >
      <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <Transition name="fade" mode="out-in">
          <!-- Realistic Page Skeleton displayed while navigating between views -->
          <div v-if="isNavigating" key="navigation-skeleton">
            <PageSkeleton :path="targetPath" />
          </div>

          <!-- Live Page Content -->
          <div v-else key="page-content">
            <!-- Page header slot -->
            <div v-if="$slots.header" class="mb-6">
              <slot name="header" />
            </div>
            
            <!-- Main content slot -->
            <slot />
          </div>
        </Transition>
      </div>
    </main>
    <Toaster />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
