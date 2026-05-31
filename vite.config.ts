/// <reference types="vitest" />
import path from 'path';
import { fileURLToPath } from 'url';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vitest/config';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// https://vite.dev/config/
export default defineConfig(() => {
  return {
    plugins: [
      laravel({
        input: ['resources/css/app.css', 'resources/js/main.ts'],
        refresh: true,
      }),
      tailwindcss(),
      VitePWA({
        registerType: 'autoUpdate',
        manifest: {
          name: 'MedSurvey Pro',
          short_name: 'MedSurvey',
          description: 'نظام إدارة استبيانات رضا المرضى',
          theme_color: '#0f172a',
          background_color: '#0f172a',
          display: 'standalone',
          start_url: '/',
          scope: '/',
          id: '/',
          icons: [
            {
              src: '/pwa-192x192.png',
              sizes: '192x192',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: '/pwa-512x512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: '/pwa-512x512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'maskable',
            },
          ],
        },
        workbox: {
          navigateFallback: null,
          globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
        },
        buildBase: '/build/',
        outDir: 'public/build',
      })
    ].filter(Boolean),
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources/js'),
      },
    },
    build: {
      chunkSizeWarningLimit: 1000,
    },
    test: {
      globals: true,
      environment: 'jsdom',
      setupFiles: ['./resources/js/tests/setup.ts'],
      exclude: [
        '.kilo/**',
        'server/**',
        'tests-e2e/**',
        'node_modules/**',
        'public/build/**',
        'playwright-report/**',
        'test-results/**',
      ],
      coverage: {
        provider: 'v8' as const,
        reporter: ['text', 'json', 'html'],
      },
    },
  };
});
