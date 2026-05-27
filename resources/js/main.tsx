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
    if (confirm('A new update is available. Do you want to refresh the page to apply changes?')) {
      updateSW(true);
    }
  },
  onOfflineReady() {
    // App is ready to work offline
  },
});

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <GlobalErrorHandler>
      <App />
    </GlobalErrorHandler>
  </StrictMode>
);
