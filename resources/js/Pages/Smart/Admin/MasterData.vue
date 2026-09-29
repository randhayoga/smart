<script setup lang="ts">
/**
 * MasterData re-export wrapper.
 * The primary component has been modularized and relocated to ./MasterData/MasterData.vue.
 */
import MasterData from './MasterData/MasterData.vue';

defineProps<{
  user?: any;
  categories?: any[];
  subcategories?: any[];
  uoms?: any[];
  brands?: any[];
  organizers?: any[];
  vendors?: any[];
  locations?: any[];
}>();
</script>

<template>
  <MasterData v-bind="$props" />
</template>
