import http from 'node:http';
import process from 'node:process';

const apiPort = process.env.VITE_API_PORT || process.env.PORT || '4001';
const apiHealthUrl = `http://127.0.0.1:${apiPort}/api/health`;

function checkHealth() {
  return new Promise(resolve => {
    const req = http.get(apiHealthUrl, res => {
      res.resume();
      resolve(res.statusCode && res.statusCode >= 200 && res.statusCode < 300);
    });

    req.on('error', () => resolve(false));
    req.setTimeout(1000, () => {
      req.destroy();
      resolve(false);
    });
  });
}

async function waitForApi(retries = 5) {
  for (let i = 0; i < retries; i++) {
    if (await checkHealth()) return true;
    await new Promise(r => setTimeout(r, 1000));
  }
  return false;
}

if (!(await waitForApi())) {
  console.error('');
  console.error(`تعذر تشغيل Vite لأن خدمة الـ API غير جاهزة: ${apiHealthUrl}`);
  console.error('شغّل المشروع عبر: npm.cmd run dev');
  console.error('أو شغّل الخادم الخلفي أولاً عبر: npm.cmd run dev:backend');
  console.error('');
  process.exit(1);
}
