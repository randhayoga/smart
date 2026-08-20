import { ref, computed } from 'vue';
import axios from 'axios';

export interface NotificationItem {
  id: string;
  title: string;
  message: string;
  timestamp: string;
  read: boolean;
  type: 'info' | 'success' | 'warning' | 'error';
  url?: string | null;
}

export const notifications = ref<NotificationItem[]>([]);

// Computed property for unread count
export const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length;
});

// Computed property for whether any read notifications exist
export const hasReadNotifications = computed(() => {
  return notifications.value.some((n) => n.read);
});

/**
  Set notifications array directly (e.g. from Inertia page props)
 */
export const setNotifications = (items: NotificationItem[]) => {
  notifications.value = items || [];
};

/**
  Fetch notifications from database API
 */
export const fetchNotifications = async () => {
  try {
    const response = await axios.get('/smart/notifications');
    if (response.data?.notifications) {
      notifications.value = response.data.notifications;
    }
  } catch (e) {
    console.error('Failed to fetch notifications from server', e);
  }
};

/**
  Add a client-side notification (transient or locally pushed)
 */
export const addNotification = (
  title: string,
  message: string,
  type: 'info' | 'success' | 'warning' | 'error' = 'info',
  url: string | null = null
) => {
  const newNotif: NotificationItem = {
    id: Math.random().toString(36).substring(2, 9),
    title,
    message,
    timestamp: new Date().toISOString(),
    read: false,
    type,
    url,
  };
  notifications.value.unshift(newNotif);

  const maxItems = Math.max(15, unreadCount.value);
  if (notifications.value.length > maxItems) {
    notifications.value = notifications.value.slice(0, maxItems);
  }
};

/**
  Push a real-time notification received from Mercure SSE stream
 */
export const pushRealtimeNotification = (item: NotificationItem) => {
  const exists = notifications.value.some((n) => n.id === item.id);
  if (!exists) {
    notifications.value.unshift(item);
    const maxItems = Math.max(15, unreadCount.value);
    if (notifications.value.length > maxItems) {
      notifications.value = notifications.value.slice(0, maxItems);
    }
  }
};

/**
  Mark single notification as read in UI & DB
 */
export const markAsRead = async (id: string) => {
  const notif = notifications.value.find((n) => n.id === id);
  if (notif) {
    notif.read = true;
  }
  try {
    await axios.post(`/smart/notifications/${id}/read`);
  } catch (e) {
    console.error(`Failed to mark notification ${id} as read`, e);
  }
};

/**
  Mark single notification as unread in UI & DB
 */
export const markAsUnread = async (id: string) => {
  const notif = notifications.value.find((n) => n.id === id);
  if (notif) {
    notif.read = false;
  }
  try {
    await axios.post(`/smart/notifications/${id}/unread`);
  } catch (e) {
    console.error(`Failed to mark notification ${id} as unread`, e);
  }
};

/**
  Delete single notification in UI & DB
 */
export const deleteNotification = async (id: string) => {
  notifications.value = notifications.value.filter((n) => n.id !== id);
  try {
    await axios.delete(`/smart/notifications/${id}`);
  } catch (e) {
    console.error(`Failed to delete notification ${id}`, e);
  }
};

/**
  Mark all notifications as read in UI & DB
 */
export const markAllAsRead = async () => {
  notifications.value.forEach((n) => {
    n.read = true;
  });
  try {
    await axios.post('/smart/notifications/read-all');
  } catch (e) {
    console.error('Failed to mark all notifications as read', e);
  }
};

/**
  Clear only already read notifications in UI & DB
 */
export const clearReadNotifications = async () => {
  notifications.value = notifications.value.filter((n) => !n.read);
  try {
    await axios.delete('/smart/notifications/clear');
  } catch (e) {
    console.error('Failed to clear read notifications', e);
  }
};
