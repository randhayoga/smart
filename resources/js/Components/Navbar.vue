<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Menu, X, Search, Bell } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

import { Badge } from '@/Components/ui/badge';

interface Props {
  sidebarOpen: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'toggle-sidebar'): void;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const userInitials = computed(() => {
  if (!user.value?.name) return 'U';
  return user.value.name
    .split(' ')
    .map((n: string) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
});

const logout = () => {
  router.post(route('logout'));
};
</script>

<template>
  <header class="fixed top-0 z-50 w-full">
    <!-- Gradient accent line -->
    <div class="gradient-line"></div>
    
    <div class="flex h-14 sm:h-16 items-center justify-between gap-2 sm:gap-4 border-b border-border bg-card px-3 sm:px-4 lg:px-6">
      <!-- Left: Mobile menu button + Logo -->
      <div class="flex items-center gap-4">
        <!-- Mobile menu toggle -->
        <Button
          variant="ghost"
          size="icon"
          class="lg:hidden"
          @click="emit('toggle-sidebar')"
        >
          <Menu v-if="!sidebarOpen" class="h-5 w-5" />
          <X v-else class="h-5 w-5" />
        </Button>
        
        <!-- Logo -->
        <Link href="/smart/dashboard" class="flex items-center gap-2 group">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg overflow-hidden transition-transform group-hover:scale-105">
            <ApplicationLogo class="h-full w-full object-contain" />
          </div>
          <span class="hidden font-bold text-lg sm:text-xl text-gradient-primary min-[480px]:inline-block">
            SMART
          </span>
        </Link>
      </div>
      
      <!-- Right: Actions -->
      <div class="flex items-center gap-2">
        <!-- Notifications -->
        <Button variant="ghost" size="icon" class="relative">
          <Bell class="h-5 w-5" />
          <Badge 
            class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs bg-gradient-primary border-0"
          >
            3
          </Badge>
        </Button>
        
        <!-- User dropdown -->
        <!-- User Avatar -->
        <div class="px-2">
          <Avatar class="h-8 w-8">
            <AvatarImage :src="user?.avatar || ''" :alt="user?.name || ''" />
            <AvatarFallback class="bg-gradient-primary text-white text-sm">
              {{ userInitials }}
            </AvatarFallback>
          </Avatar>
        </div>
      </div>
    </div>
  </header>
</template>
