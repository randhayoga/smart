<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Menu, X, Search, Bell, Info, CheckCircle2, AlertTriangle, XCircle, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Badge } from '@/Components/ui/badge';

// Dropdown and ScrollArea components
import { 
  DropdownMenu, 
  DropdownMenuTrigger, 
  DropdownMenuContent,
  DropdownMenuSeparator
} from '@/Components/ui/dropdown-menu';
import { ScrollArea } from '@/Components/ui/scroll-area';

// Notification Store
import { 
  notifications, 
  unreadCount, 
  markAsRead, 
  markAllAsRead, 
  clearNotifications 
} from '@/stores/notificationStore';

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

const formatTime = (isoString: string) => {
  const date = new Date(isoString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);

  if (diffMins < 1) return 'Baru saja';
  if (diffMins < 60) return `${diffMins}m lalu`;
  if (diffHours < 24) return `${diffHours}j lalu`;

  return date.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
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
          <X v-else class="h-5 w-5 cursor-pointer" />
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
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
              <Bell class="h-5 w-5" />
              <Badge 
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs bg-gradient-primary border-0 animate-pulse"
              >
                {{ unreadCount }}
              </Badge>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-80 sm:w-96 p-0 bg-card border border-border rounded-lg shadow-lg">
            <div class="p-4 flex items-center justify-between border-b border-border">
              <h3 class="font-semibold text-sm">Notifikasi</h3>
              <div class="flex items-center gap-2">
                <button 
                  v-if="unreadCount > 0"
                  @click="markAllAsRead" 
                  class="text-xs text-primary hover:underline font-medium bg-transparent border-0 cursor-pointer"
                >
                  Tandai semua dibaca
                </button>
                <button 
                  v-if="notifications.length > 0"
                  @click="clearNotifications" 
                  class="text-xs text-muted-foreground hover:text-destructive hover:underline font-medium ml-2 bg-transparent border-0 cursor-pointer"
                >
                  Hapus semua
                </button>
              </div>
            </div>
            
            <ScrollArea class="max-h-[300px] overflow-y-auto">
              <div v-if="notifications.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                Tidak ada notifikasi
              </div>
              <div v-else class="divide-y divide-border">
                <div 
                  v-for="item in notifications" 
                  :key="item.id" 
                  class="p-4 flex gap-3 hover:bg-muted/50 transition-colors relative cursor-pointer"
                  :class="[ !item.read ? 'bg-muted/30 font-medium' : '' ]"
                  @click="markAsRead(item.id)"
                >
                  <!-- Unread indicator dot -->
                  <span v-if="!item.read" class="absolute left-1.5 top-5 h-2 w-2 rounded-full bg-primary"></span>
                  
                  <div class="flex-shrink-0 mt-0.5">
                    <CheckCircle2 v-if="item.type === 'success'" class="h-5 w-5 text-emerald-500" />
                    <AlertTriangle v-else-if="item.type === 'warning'" class="h-5 w-5 text-amber-500" />
                    <XCircle v-else-if="item.type === 'error'" class="h-5 w-5 text-destructive" />
                    <Info v-else class="h-5 w-5 text-blue-500" />
                  </div>
                  
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-foreground flex items-center justify-between gap-2">
                      <span class="truncate">{{ item.title }}</span>
                      <span class="text-[10px] text-muted-foreground font-normal whitespace-nowrap">
                        {{ formatTime(item.timestamp) }}
                      </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1 break-words line-clamp-2 font-normal">
                      {{ item.message }}
                    </p>
                  </div>
                </div>
              </div>
            </ScrollArea>
          </DropdownMenuContent>
        </DropdownMenu>
        
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
