import { expect, test } from '@playwright/test';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import type { AddressInfo } from 'node:net';
import path from 'node:path';

for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 900 }]) {
  test(`offline navigation keeps private pages out of storage (${viewport.width}px)`, async ({ page, context }) => {
    await page.setViewportSize(viewport);
    await page.goto('/login');
    await page.evaluate(async () => {
      await navigator.serviceWorker.ready;
      if (!navigator.serviceWorker.controller) {
        await new Promise<void>((resolve) =>
          navigator.serviceWorker.addEventListener('controllerchange', () => resolve(), { once: true }),
        );
      }
    });
    await page.locator('input[name=username]').fill('browser-admin-A');
    await page.locator('input[type=password]').fill(process.env.TEST_ADMIN_PASSWORD ?? 'Password123!');
    await page.locator('button[type=submit]').click();
    await page.waitForURL('**/dashboard');
    const response = await page.goto('/dashboard/responses');
    expect(response?.headers()['cache-control']).toContain('no-store');
    await expect(page.locator('body')).toContainText('Browser department A');
    await expect(page.locator('body')).not.toContainText('Browser department B');
    const cachedPaths = await page.evaluate(async () => {
      await fetch('/api/health');
      const paths: string[] = [];
      for (const name of await caches.keys()) {
        for (const request of await (await caches.open(name)).keys()) {
          paths.push(new URL(request.url).pathname);
        }
      }
      return paths;
    });
    expect(cachedPaths).toContain('/offline.html');
    const staticFiles = ['/offline.html', '/manifest.webmanifest', '/favicon.png', '/system-logo.png', '/pwa-192x192.png', '/pwa-512x512.png'];
    expect(cachedPaths.filter((path) => !path.startsWith('/build/assets/') && !staticFiles.includes(path))).toEqual([]);
    await page.evaluate(async () => {
      const token = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')!.content;
      const result = await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': token } });
      if (!result.ok) throw new Error('Logout failed');
    });
    await page.goto('/dashboard/responses');
    await expect(page).toHaveURL(/\/login$/);
    await page.locator('input[name=username]').fill('browser-admin-B');
    await page.locator('input[type=password]').fill(process.env.TEST_ADMIN_PASSWORD ?? 'Password123!');
    await page.locator('button[type=submit]').click();
    await page.waitForURL('**/dashboard');
    await page.goto('/dashboard/responses');
    await expect(page.locator('body')).toContainText('Browser department B');
    await expect(page.locator('body')).not.toContainText('Browser department A');
    await context.setOffline(true);
    await page.goto('/dashboard/responses');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('الاتصال غير متاح');
    await expect(page.locator('form, table')).toHaveCount(0);
  });
}

test('declining an update keeps the active version until the update is accepted', async ({ page }) => {
  const root = path.resolve('public');
  const manifest = JSON.parse(await readFile(path.join(root, 'build/manifest.json'), 'utf8'));
  const main = manifest['resources/js/main.ts'].file;
  let generation = 1;
  const server = createServer(async (request, response) => {
    const pathname = new URL(request.url!, 'http://localhost').pathname;
    if (pathname === '/') {
      response.writeHead(200, { 'Content-Type': 'text/html' });
      response.end(`<!doctype html><script type="module" src="/build/${main}"></script>`);
      return;
    }
    const file = path.resolve(root, '.' + pathname);
    if (!file.startsWith(root + path.sep)) { response.writeHead(404).end(); return; }
    try {
      const data = await readFile(file);
      response.setHeader('Cache-Control', 'no-store');
      if (file.endsWith('.js')) response.setHeader('Content-Type', 'text/javascript');
      response.end(pathname === '/sw.js' ? Buffer.concat([data, Buffer.from(`\n// fixture ${generation}`)]) : data);
    } catch { response.writeHead(404).end(); }
  });
  await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve));
  try {
    await page.goto(`http://127.0.0.1:${(server.address() as AddressInfo).port}`);
    await page.waitForFunction(() => navigator.serviceWorker.controller !== null);
    generation++;
    const declined = page.waitForEvent('dialog').then((dialog) => dialog.dismiss());
    await page.evaluate(async () => { await (await navigator.serviceWorker.ready).update(); });
    await declined;
    await page.waitForFunction(async () => Boolean((await navigator.serviceWorker.ready).waiting));
    const accepted = page.waitForEvent('dialog').then((dialog) => dialog.accept());
    await page.reload({ waitUntil: 'domcontentloaded' });
    await accepted;
    await expect.poll(async () => {
      try { return await page.evaluate(async () => (await navigator.serviceWorker.ready).waiting === null); }
      catch { return false; }
    }).toBe(true);
  } finally {
    server.closeAllConnections();
    await new Promise<void>((resolve) => server.close(() => resolve()));
  }
});
