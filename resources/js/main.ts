import './index.css';
import './echo';
import './dashboard/ajax-helpers';
import { registerSW } from 'virtual:pwa-register';

// Register PWA service worker
const updateSW = registerSW({
  onNeedRefresh() {
    if (confirm('A new update is available. Do you want to refresh the page to apply changes?')) {
      updateSW(true);
    }
  },
  onOfflineReady() {
    // App is ready to work offline
  },
});
