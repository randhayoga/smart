<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Menu, X, Search, Bell, Info, CheckCircle2, AlertTriangle, XCircle, Trash2, Building2, LogOut, EllipsisVertical, Mail, SquareArrowOutUpRight } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Badge } from '@/Components/ui/badge';

// Dropdown and ScrollArea components
import { 
  DropdownMenu, 
  DropdownMenuTrigger, 
  DropdownMenuContent, 
  DropdownMenuSeparator,
  DropdownMenuItem
} from '@/Components/ui/dropdown-menu';
import { ScrollArea } from '@/Components/ui/scroll-area';

// Notification Store
import { 
  notifications, 
  unreadCount, 
  hasReadNotifications,
  markAsRead, 
  markAsUnread,
  deleteNotification,
  markAllAsRead, 
  clearReadNotifications,
  setNotifications
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

// Watch shared notifications from Inertia props & sync to store
watch(
  () => (page.props.auth as any)?.notifications,
  (newVal: any) => {
    if (Array.isArray(newVal)) {
      setNotifications(newVal);
    }
  },
  { immediate: true }
);

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

const goToNotification = (item: any) => {
  if (!item.url) return;
  if (!item.read) {
    markAsRead(item.id);
  }
  router.visit(item.url);
};

const formatTime = (isoString: string) => {
  const date = new Date(isoString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);

  if (diffMins < 1) return 'Baru saja';
  if (diffMins < 60) return `${diffMins} mnt lalu`;
  if (diffHours < 24) return `${diffHours} jam lalu`;

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
          <DropdownMenuContent align="end" class="w-128 sm:w-192 p-0 bg-card border border-border rounded-lg shadow-lg">
            <div class="px-4 py-2 flex items-center justify-between border-b border-border">
              <h3 class="font-medium text-base">Notifikasi</h3>
              <div class="flex items-center gap-2">
                <button 
                  v-if="unreadCount > 0"
                  @click="markAllAsRead" 
                  class="text-xs text-primary hover:underline font-medium bg-transparent border-0 cursor-pointer"
                >
                  Tandai semua dibaca
                </button>
                <button 
                  v-if="hasReadNotifications"
                  @click="clearReadNotifications" 
                  class="text-xs text-muted-foreground hover:text-destructive hover:underline font-medium ml-2 bg-transparent border-0 cursor-pointer"
                >
                  Hapus semua terbaca
                </button>
              </div>
            </div>
            
            <ScrollArea class="max-h-[420px] overflow-y-auto">
              <div v-if="notifications.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                Tidak ada notifikasi
              </div>
              <div v-else class="divide-y divide-border">
                <div 
                  v-for="item in notifications" 
                  :key="item.id" 
                  class="px-4 py-3 flex items-start gap-2 hover:bg-muted/50 transition-colors relative cursor-pointer group"
                  :class="[ !item.read ? 'bg-muted/30 font-medium' : '' ]"
                  @click="markAsRead(item.id)"
                >
                  <!-- Unread indicator dot -->
                  <span v-if="!item.read" class="absolute left-2.5 top-4 h-2 w-2 rounded-full bg-primary"></span>
                  
                  <div class="flex-shrink-0 self-center">
                    <CheckCircle2 v-if="item.type === 'success'" class="h-6 w-6 text-emerald-500" />
                    <AlertTriangle v-else-if="item.type === 'warning'" class="h-6 w-6 text-amber-500" />
                    <XCircle v-else-if="item.type === 'error'" class="h-6 w-6 text-destructive" />
                    <Info v-else class="h-6 w-6 text-blue-500" />
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

                  <!-- Item options and Go To button -->
                  <div class="flex-shrink-0 -mr-1 self-center flex items-center gap-0.5" @click.stop>
                    <button
                      v-if="item.url"
                      type="button"
                      @click.stop="goToNotification(item)"
                      class="h-7 w-7 rounded-md flex items-center justify-center text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer border-0 bg-transparent"
                      title="Buka halaman terkait"
                      aria-label="Buka halaman terkait"
                    >
                      <SquareArrowOutUpRight class="h-3.5 w-3.5" />
                    </button>

                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <button
                          type="button"
                          class="h-7 w-7 rounded-md flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer border-0 bg-transparent"
                          aria-label="Opsi notifikasi"
                        >
                          <EllipsisVertical class="h-4 w-4" />
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" class="w-44 p-1 bg-card border border-border rounded-lg shadow-md">
                        <DropdownMenuItem 
                          v-if="item.read"
                          @click.stop="markAsUnread(item.id)"
                          class="cursor-pointer text-xs flex items-center gap-2 px-2.5 py-1.5 hover:bg-primary/10 focus:bg-primary/10 hover:text-primary focus:text-primary transition-colors"
                        >
                          <Mail class="h-3.5 w-3.5" />
                          <span>Tandai belum dibaca</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem 
                          @click.stop="deleteNotification(item.id)"
                          class="cursor-pointer text-xs flex items-center gap-2 px-2.5 py-1.5 text-destructive hover:text-destructive focus:text-destructive focus:bg-destructive/10 data-[highlighted]:text-destructive data-[highlighted]:bg-destructive/10"
                        >
                          <Trash2 class="h-3.5 w-3.5 text-destructive" />
                          <span>Hapus</span>
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
              </div>
            </ScrollArea>
          </DropdownMenuContent>
        </DropdownMenu>
        
        <!-- User dropdown -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button 
              type="button" 
              class="flex items-center gap-2 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card cursor-pointer border-0 bg-transparent p-0.5 hover:opacity-90 transition-opacity"
              aria-label="User Menu"
            >
              <Avatar class="h-8 w-8">
                <AvatarImage :src="user?.avatar || ''" :alt="user?.name || ''" />
                <AvatarFallback class="bg-gradient-primary text-white text-sm font-semibold">
                  {{ userInitials }}
                </AvatarFallback>
              </Avatar>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-64 p-2 bg-card border border-border rounded-lg shadow-lg">
            <div class="px-2 py-1">
              <p class="text-base font-semibold text-foreground truncate">
                {{ user?.name || 'User' }}
              </p>
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground truncate">
                <span class="truncate">{{ user?.org_name || 'Tanpa Organisasi' }}</span>
              </div>
            </div>
            <DropdownMenuSeparator class="my-1.5 border-t border-border" />
            <DropdownMenuItem 
              @click="logout" 
              class="cursor-pointer text-destructive focus:text-destructive focus:bg-destructive/10 flex items-center gap-2"
            >
              <LogOut class="h-4 w-4" />
              <span>Keluar</span>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  </header>
</template>
