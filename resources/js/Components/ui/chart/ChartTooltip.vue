<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'

defineProps<{
  title?: string
  data: {
    name: string
    color: string
    value: any
  }[]
}>()
</script>

<template>
  <Card class="text-sm shadow-lg border bg-card text-card-foreground">
    <CardHeader v-if="title" class="p-3 pb-2 border-b">
      <CardTitle class="text-sm font-semibold text-muted-foreground">
        {{ title }}
      </CardTitle>
    </CardHeader>
    <CardContent class="p-3 min-w-[180px] flex flex-col gap-2">
      <div v-for="(item, key) in data" :key="key" class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-3 h-3 rounded-full shrink-0 inline-block" :style="{ backgroundColor: item.color }"></span>
          <span class="text-sm font-medium text-foreground truncate">{{ item.name }}</span>
        </div>
        <span class="text-sm font-bold text-foreground shrink-0 ml-3">{{ item.value }}</span>
      </div>
    </CardContent>
  </Card>
</template>
