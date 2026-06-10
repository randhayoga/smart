import { ref, watch, computed } from 'vue';

export interface NotificationItem {
  id: string;
  title: string;
  message: string;
  timestamp: string;
  read: boolean;
  type: 'info' | 'success' | 'warning' | 'error';
}

const STORAGE_KEY = 'smart_notifications';

// Load initial notifications from localStorage
const loadNotifications = (): NotificationItem[] => {
  try {
    const data = localStorage.getItem(STORAGE_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    console.error('Failed to parse notifications from localStorage', e);
    return [];
  }
};

export const notifications = ref<NotificationItem[]>(loadNotifications());

// Computed property for unread count
export const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length;
});

// Watch notifications and save to localStorage
watch(
  notifications,
  (newVal) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(newVal));
    } catch (e) {
      console.error('Failed to save notifications to localStorage', e);
    }
  },
  { deep: true }
);

// Actions
export const addNotification = (
  title: string,
  message: string,
  type: 'info' | 'success' | 'warning' | 'error' = 'info'
) => {
  const newNotif: NotificationItem = {
    id: Math.random().toString(36).substring(2, 9),
    title,
    message,
    timestamp: new Date().toISOString(),
    read: false,
    type,
  };
  notifications.value.unshift(newNotif);

  // Keep only the last 50 notifications to prevent bloat
  if (notifications.value.length > 50) {
    notifications.value = notifications.value.slice(0, 50);
  }
};

export const markAsRead = (id: string) => {
  const notif = notifications.value.find((n) => n.id === id);
  if (notif) {
    notif.read = true;
  }
};

export const markAllAsRead = () => {
  notifications.value.forEach((n) => {
    n.read = true;
  });
};

export const clearNotifications = () => {
  notifications.value = [];
};
