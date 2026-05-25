import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import "./i18n";
import "./echo";
import App from "./App";
import { GlobalErrorHandler } from "./components/GlobalErrorHandler";
import { registerSW } from 'virtual:pwa-register';

// Register PWA service worker
const updateSW = registerSW({
  onNeedRefresh() {
    if (confirm('يتوفر تحديث جديد للنظام، هل تود تحديث الصفحة الآن لتطبيق التغييرات؟')) {
      updateSW(true);
    }
  },
  onOfflineReady() {
    console.log('التطبيق جاهز للعمل بدون اتصال بالإنترنت.');
  },
});

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <GlobalErrorHandler>
      <App />
    </GlobalErrorHandler>
  </StrictMode>
);
