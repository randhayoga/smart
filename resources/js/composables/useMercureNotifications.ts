import { ref, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { pushRealtimeNotification, type NotificationItem } from '@/stores/notificationStore';

let activeEventSource: EventSource | null = null;
let reconnectTimer: any = null;

export function useMercureNotifications() {
  const page = usePage();
  const isConnected = ref(false);

  const connect = async () => {
    // If not authenticated, do not connect
    const auth = (page.props.auth as any);
    if (!auth?.user?.id) {
      disconnect();
      return;
    }

    let hubUrl = auth?.mercure?.hubUrl;
    let topic = auth?.mercure?.topic;
    let token = auth?.mercure?.token;

    // Fallback: If token or credentials not in page props, fetch from endpoint
    if (!hubUrl || !topic || !token) {
      try {
        const res = await axios.get('/smart/notifications/mercure-token');
        if (res.data?.hubUrl && res.data?.topic && res.data?.token) {
          hubUrl = res.data.hubUrl;
          topic = res.data.topic;
          token = res.data.token;
        }
      } catch (err) {
        console.warn('[Mercure] Unable to fetch subscription token:', err);
        return;
      }
    }

    if (!hubUrl || !topic || !token) {
      return;
    }

    // Close any previous connection
    if (activeEventSource) {
      activeEventSource.close();
      activeEventSource = null;
    }

    try {
      const url = new URL(hubUrl, window.location.origin);
      url.searchParams.append('topic', topic);
      url.searchParams.append('authorization', token);

      const es = new EventSource(url.toString());
      activeEventSource = es;

      es.onopen = () => {
        isConnected.value = true;
      };

      es.onmessage = (event) => {
        try {
          const payload: NotificationItem = JSON.parse(event.data);
          if (payload && payload.id) {
            // Push directly to reactive notification store to update Navbar bell & list
            pushRealtimeNotification(payload);
          }
        } catch (err) {
          console.error('[Mercure] Error parsing notification event:', err);
        }
      };

      es.onerror = () => {
        isConnected.value = false;
        if (activeEventSource) {
          activeEventSource.close();
          activeEventSource = null;
        }

        // Attempt reconnection after backoff with fresh token
        if (!reconnectTimer) {
          reconnectTimer = setTimeout(async () => {
            reconnectTimer = null;
            // Fetch fresh token before reconnecting in case token expired
            try {
              const res = await axios.get('/smart/notifications/mercure-token');
              if (res.data && auth?.mercure) {
                auth.mercure.token = res.data.token;
              }
            } catch (e) {
              // Ignore network retry failure
            }
            connect();
          }, 5000);
        }
      };
    } catch (err) {
      console.error('[Mercure] Connection initialization failed:', err);
    }
  };

  const disconnect = () => {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
    if (activeEventSource) {
      activeEventSource.close();
      activeEventSource = null;
    }
    isConnected.value = false;
  };

  onMounted(() => {
    connect();
  });

  // Watch for auth user changes (e.g. login/logout or user ID change)
  watch(
    () => (page.props.auth as any)?.user?.id,
    (newId, oldId) => {
      if (newId !== oldId) {
        disconnect();
        if (newId) {
          connect();
        }
      }
    }
  );

  return {
    isConnected,
    connect,
    disconnect,
  };
}
