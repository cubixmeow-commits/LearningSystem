import { chromium } from 'playwright';
import { mkdirSync } from 'fs';

const base = process.env.LEARN_BASE || 'http://127.0.0.1:8080';
const outDir = process.env.LEARN_SHOT_DIR || '/opt/cursor/artifacts/screenshots';
mkdirSync(outDir, { recursive: true });

const pages = [
  ['items', '/'],
  ['item-brief', '/item.php?id=11'],
  ['item-manual', '/item.php?id=45'],
  ['topics', '/topics.php'],
  ['lessons', '/lessons.php'],
  ['lesson', '/lesson.php?file=lessons/harness-engineering-for-ai-coding-agents-2026-07.md'],
  ['claims', '/claims.php'],
];

const viewports = [
  ['desktop', { width: 1280, height: 800 }],
  ['iphone', { width: 390, height: 844 }],
];

const browser = await chromium.launch({ headless: true });
for (const [vpName, viewport] of viewports) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  for (const [name, path] of pages) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(250);
    const file = `${outDir}/${name}-${vpName}.png`;
    await page.screenshot({ path: file, fullPage: true });
    console.log('wrote', file);
  }
  await context.close();
}
await browser.close();
