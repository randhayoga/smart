<script setup lang="ts">
/**
 * Language Selector component providing accessible switching between Bahasa Indonesia and English.
 * Synchronizes client reactive i18n state and backend Laravel session/cookie.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Languages, Check } from 'lucide-vue-next';
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { setI18nLanguage } from '@/locales';

interface SupportedLanguage {
  code: 'id' | 'en';
  name: string;
  short: string;
  flag: string;
}

const { locale } = useI18n();

const currentLocale = computed<'id' | 'en'>(() => (locale.value === 'en' ? 'en' : 'id'));

const languages: SupportedLanguage[] = [
  { code: 'id', name: 'Bahasa Indonesia', short: 'ID', flag: '🇮🇩' },
  { code: 'en', name: 'English', short: 'EN', flag: '🇬🇧' },
];

function selectLanguage(newLocale: 'id' | 'en') {
  if (newLocale === currentLocale.value) return;

  // 1. Update frontend reactive i18n instance immediately
  setI18nLanguage(newLocale);

  // 2. Persist to Laravel session and persistent cookie via router
  router.post(
    route('locale.update'),
    { locale: newLocale },
    {
      preserveScroll: true,
      preserveState: true,
    }
  );
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        size="sm"
        class="h-8 sm:h-9 px-2 sm:px-2.5 gap-1.5 font-medium text-xs text-muted-foreground hover:text-foreground rounded-lg transition-colors cursor-pointer"
        aria-label="Pilih Bahasa / Select Language"
      >
        <Languages class="h-4 w-4" />
        <span class="font-semibold uppercase tracking-wider">{{ currentLocale.toUpperCase() }}</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent
      align="end"
      :side-offset="8"
      class="w-48 p-1 bg-card border border-border rounded-xl shadow-lg z-50"
    >
      <DropdownMenuItem
        v-for="lang in languages"
        :key="lang.code"
        @click="selectLanguage(lang.code)"
        class="flex items-center justify-between px-3 py-2 text-xs rounded-lg cursor-pointer transition-colors"
        :class="currentLocale === lang.code ? 'bg-primary/10 text-primary font-semibold' : 'text-foreground hover:bg-muted'"
      >
        <span class="flex items-center gap-2.5">
          <span>{{ lang.name }}</span>
        </span>
        <Check v-if="currentLocale === lang.code" class="h-3.5 w-3.5 text-primary" />
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
