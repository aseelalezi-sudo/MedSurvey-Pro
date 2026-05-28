import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

window.Pusher = Pusher;

const createTestEcho = () => ({
  private: () => ({
    listen: () => createTestEcho().private(),
    stopListening: () => createTestEcho().private(),
  }),
  leave: () => undefined,
  disconnect: () => undefined,
});

const broadcastingEnabled = import.meta.env.VITE_ENABLE_BROADCASTING === 'true';

const echo = import.meta.env.MODE === 'test' || !broadcastingEnabled
  ? createTestEcho()
  : new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
    });

export default echo;
