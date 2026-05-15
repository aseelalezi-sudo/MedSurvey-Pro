import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import "./i18n";
import App from "./App";
import { GlobalErrorHandler } from "./components/GlobalErrorHandler";

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <GlobalErrorHandler>
      <App />
    </GlobalErrorHandler>
  </StrictMode>
);
