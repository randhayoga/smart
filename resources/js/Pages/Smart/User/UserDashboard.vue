<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Heading from '@/Components/Heading.vue';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

const { t } = useI18n();

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 11) return t('common.greetings.morning');
  if (hour >= 11 && hour < 15) return t('common.greetings.afternoon');
  if (hour >= 15 && hour < 19) return t('common.greetings.evening');
  return t('common.greetings.night');
});
</script>

<template>
  <AppLayout :title="$t('nav.items.dashboard')">
    <Heading as="h1">
      {{ greeting }}, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
    </Heading>
    
    <!-- Blank state -->
    <div class="bg-card rounded-xl border border-border p-8 min-h-[400px] flex items-center justify-center">
      <p class="text-muted-foreground italic">{{ $t('common.greetings.noContent') }}</p>
    </div>
  </AppLayout>
</template>
