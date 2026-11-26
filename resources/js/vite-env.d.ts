/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

declare module 'lucide/dist/esm/iconsAndAliases.mjs' {
  const icons: typeof import('lucide').icons;
  export = icons;
}
