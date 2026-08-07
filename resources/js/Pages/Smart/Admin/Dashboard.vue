<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';

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
    <Heading as="h1">
      {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
    </Heading>
    
    <div class="mt-8 space-y-6">
      <div>
        <h2 class="text-lg font-semibold">Total Quantity of Consumable Subcategories:</h2>
        <ul class="list-disc list-inside mt-2">
          <li v-for="stat in consumableSubcategoryStats" :key="stat.subcategory_name">
            {{ stat.subcategory_name }}: {{ stat.total_quantity }}
          </li>
          <li v-if="!consumableSubcategoryStats?.length" class="text-gray-500">No data available.</li>
        </ul>
      </div>

      <div>
        <h2 class="text-lg font-semibold">Total Units of Non-Consumable Categories (CFS):</h2>
        <ul class="list-disc list-inside mt-2">
          <li v-for="stat in cfsCategoryStats" :key="stat.category_name">
            {{ stat.category_name }}: {{ stat.total_units }}
          </li>
          <li v-if="!cfsCategoryStats?.length" class="text-gray-500">No data available.</li>
        </ul>
      </div>

      <div>
        <h2 class="text-lg font-semibold">Total Units of Non-Consumable Categories (ICT):</h2>
        <ul class="list-disc list-inside mt-2">
          <li v-for="stat in ictCategoryStats" :key="stat.category_name">
            {{ stat.category_name }}: {{ stat.total_units }}
          </li>
          <li v-if="!ictCategoryStats?.length" class="text-gray-500">No data available.</li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
