<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';

interface Props {
  title?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'SMART',
});

const sidebarOpen = ref(false);
const isMobile = ref(false);

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
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
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
        <!-- Page header slot -->
        <div v-if="$slots.header" class="mb-6">
          <slot name="header" />
        </div>
        
        <!-- Main content slot -->
        <slot />
      </div>
    </main>
  </div>
</template>
