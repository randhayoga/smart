<script setup lang="ts">
/**
 * Top Navbar component rendering the application header, mobile menu toggler, real-time notification popover, and user profile menu.
 */
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Menu, X, Search, Bell, Info, CheckCircle2, AlertTriangle, XCircle, Trash2, Building2, LogOut, EllipsisVertical, Mail, SquareArrowOutUpRight } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Badge } from '@/Components/ui/badge';
import { formatDateTime } from '@/lib/utils';

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

  return formatDateTime(date);
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
          <DropdownMenuContent 
            align="end" 
            :collision-padding="12"
            :side-offset="8"
            class="w-[calc(100vw-1.5rem)] sm:w-[420px] md:w-[500px] lg:w-[560px] xl:w-[600px] max-w-[calc(100vw-1.5rem)] sm:max-w-none p-0 bg-card border border-border rounded-xl shadow-xl z-50 overflow-hidden"
          >
            <!-- Header -->
            <div class="px-3.5 sm:px-4 lg:px-5 py-2.5 sm:py-3 flex items-center justify-between gap-2 border-b border-border bg-card">
              <div class="flex items-center gap-2">
                <h3 class="font-semibold text-sm sm:text-base text-foreground">Notifikasi</h3>
                <span 
                  v-if="unreadCount > 0" 
                  class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-primary/10 text-primary border border-primary/20"
                >
                  {{ unreadCount }} baru
                </span>
              </div>
              <div class="flex items-center gap-2 sm:gap-3 text-xs">
                <button 
                  v-if="unreadCount > 0"
                  @click="markAllAsRead" 
                  class="text-xs text-primary hover:underline font-medium bg-transparent border-0 cursor-pointer p-0"
                >
                  Tandai semua dibaca
                </button>
                <button 
                  v-if="hasReadNotifications"
                  @click="clearReadNotifications" 
                  class="text-xs text-muted-foreground hover:text-destructive hover:underline font-medium bg-transparent border-0 cursor-pointer p-0"
                >
                  Hapus terbaca
                </button>
              </div>
            </div>
            
            <!-- Scroll Area -->
            <ScrollArea class="max-h-[min(70vh,420px)] lg:max-h-[480px] overflow-y-auto">
              <div v-if="notifications.length === 0" class="py-10 px-4 flex flex-col items-center justify-center text-center text-muted-foreground gap-2">
                <Bell class="h-8 w-8 opacity-30 stroke-1" />
                <p class="text-sm font-medium">Tidak ada notifikasi</p>
              </div>
              <div v-else class="divide-y divide-border">
                <div 
                  v-for="item in notifications" 
                  :key="item.id" 
                  class="px-3 sm:px-4 lg:px-5 py-3 flex items-start gap-2.5 sm:gap-3 lg:gap-3.5 hover:bg-muted/40 transition-colors relative cursor-pointer group"
                  :class="[ !item.read ? 'bg-primary/[0.03] dark:bg-primary/[0.06]' : '' ]"
                  @click="markAsRead(item.id)"
                >
                  <!-- Unread indicator dot -->
                  <span v-if="!item.read" class="absolute left-1.5 sm:left-2 top-4 h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-primary ring-2 ring-background"></span>
                  
                  <!-- Type Icon Container -->
                  <div class="flex-shrink-0 mt-0.5">
                    <div 
                      class="h-8 w-8 rounded-lg flex items-center justify-center"
                      :class="[
                        item.type === 'success' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
                        item.type === 'warning' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                        item.type === 'error' ? 'bg-destructive/10 text-destructive' :
                        'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                      ]"
                    >
                      <CheckCircle2 v-if="item.type === 'success'" class="h-4.5 w-4.5" />
                      <AlertTriangle v-else-if="item.type === 'warning'" class="h-4.5 w-4.5" />
                      <XCircle v-else-if="item.type === 'error'" class="h-4.5 w-4.5" />
                      <Info v-else class="h-4.5 w-4.5" />
                    </div>
                  </div>
                  
                  <!-- Text Body -->
                  <div class="flex-1 min-w-0 pr-1">
                    <div class="flex items-start justify-between gap-2">
                      <h4 
                        class="text-xs sm:text-sm font-semibold text-foreground leading-snug line-clamp-2"
                        :class="[ !item.read ? 'font-bold text-foreground' : 'font-semibold text-foreground/90' ]"
                      >
                        {{ item.title }}
                      </h4>
                      <span class="text-[10px] sm:text-xs text-muted-foreground font-normal whitespace-nowrap shrink-0 mt-0.5">
                        {{ formatTime(item.timestamp) }}
                      </span>
                    </div>
                    <p class="text-[11px] sm:text-xs text-muted-foreground mt-1 break-words line-clamp-2 sm:line-clamp-3 leading-relaxed font-normal">
                      {{ item.message }}
                    </p>
                  </div>

                  <!-- Item Action Buttons -->
                  <div class="flex-shrink-0 -mr-1 flex items-center gap-0.5 self-center" @click.stop>
                    <button
                      v-if="item.url"
                      type="button"
                      @click.stop="goToNotification(item)"
                      class="h-8 w-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer border-0 bg-transparent"
                      title="Buka halaman terkait"
                      aria-label="Buka halaman terkait"
                    >
                      <SquareArrowOutUpRight class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    </button>

                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <button
                          type="button"
                          class="h-8 w-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer border-0 bg-transparent"
                          aria-label="Opsi notifikasi"
                        >
                          <EllipsisVertical class="h-4 w-4" />
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" class="w-48 p-1 bg-card border border-border rounded-lg shadow-md z-50">
                        <DropdownMenuItem 
                          v-if="item.read"
                          @click.stop="markAsUnread(item.id)"
                          class="cursor-pointer text-xs flex items-center gap-2 px-2.5 py-2 hover:bg-primary/10 focus:bg-primary/10 hover:text-primary focus:text-primary transition-colors rounded-md"
                        >
                          <Mail class="h-3.5 w-3.5" />
                          <span>Tandai belum dibaca</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem 
                          @click.stop="deleteNotification(item.id)"
                          class="cursor-pointer text-xs flex items-center gap-2 px-2.5 py-2 text-destructive hover:text-destructive focus:text-destructive focus:bg-destructive/10 data-[highlighted]:text-destructive data-[highlighted]:bg-destructive/10 transition-colors rounded-md"
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
