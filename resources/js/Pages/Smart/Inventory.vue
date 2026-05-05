<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Package } from 'lucide-vue-next';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout title="Manajemen Inventory">
    <template #header>
      <div class="flex items-center gap-3">
        <div class="p-2 bg-primary/10 rounded-lg">
          <Package class="w-6 h-6 text-primary" />
        </div>
        <h1 class="text-lg sm:text-2xl lg:text-3xl font-bold leading-tight">
          Manajemen Inventory
        </h1>
      </div>
    </template>
    
    <div class="bg-card rounded-xl border border-border p-8 min-h-[400px] flex items-center justify-center">
      <p class="text-muted-foreground italic">Modul Manajemen Inventory sedang dalam pengerjaan.</p>
    </div>
  </AppLayout>
</template>
