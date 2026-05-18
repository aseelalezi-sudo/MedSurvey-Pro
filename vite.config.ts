/// <reference types="vitest" />
import path from "path";
import { fileURLToPath } from "url";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vitest/config";
import { loadEnv } from "vite";
import { viteSingleFile } from "vite-plugin-singlefile";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// https://vite.dev/config/
export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, __dirname, '');
  const apiProxyTarget =
    env.VITE_API_PROXY_TARGET ||
    `http://127.0.0.1:${env.VITE_API_PORT || '4001'}`;

  return {
    plugins: [
      react(), 
      tailwindcss(), 
      command === 'build' ? viteSingleFile() : null
    ].filter(Boolean),
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "src"),
      },
    },
    server: {
      host: '127.0.0.1',
      port: 3000,
      strictPort: false,
      open: true,
      watch: {
        usePolling: true,
      },
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
        },
      },
    },
    preview: {
      host: '127.0.0.1',
      port: 3000,
      strictPort: false,
    },
    test: {
      globals: true,
      environment: 'jsdom',
      setupFiles: ['./src/tests/setup.ts'],
      exclude: ['.kilo/**', 'server/**', 'tests-e2e/**', 'node_modules/**', 'dist/**', 'playwright-report/**', 'test-results/**'],
      coverage: {
        provider: 'v8',
        reporter: ['text', 'json', 'html'],
      },
    },
  };
});
