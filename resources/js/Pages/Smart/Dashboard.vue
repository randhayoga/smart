<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) return 'Selamat Pagi';
  if (hour >= 11 && hour < 15) return 'Selamat Siang';
  if (hour >= 15 && hour < 19) return 'Selamat Sore';
  return 'Selamat Malam';
});
</script>

<template>
  <AppLayout title="Dashboard">
    <template #header>
      <h1 class="text-lg sm:text-2xl lg:text-3xl font-bold leading-tight">
        {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
      </h1>
    </template>
    
    <!-- Blank state -->
    <div class="bg-card rounded-xl border border-border p-8 min-h-[400px] flex items-center justify-center">
      <p class="text-muted-foreground italic">Sedang dalam pengerjaan.</p>
    </div>
  </AppLayout>
</template>
