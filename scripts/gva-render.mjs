#!/usr/bin/env node
// Headless renderer for the GVA's JavaScript-rendered Liferay pages.
//
// The adjudicaciones / inicio / contínues pages load their PDF links via JS,
// so a plain HTTP fetch sees nothing. This script renders the page with a
// headless Chromium and prints, as JSON, every PDF link it finds:
//
//   node scripts/gva-render.mjs <url> [<url> ...]
//   → {"ok":true,"links":[{"titulo":"...","url":"https://.../x.pdf"}, ...]}
//
// Chromium binary: GVA_CHROMIUM_PATH env, else Playwright's default download.
// Exit code is always 0; failures are reported in the JSON ("ok":false).

import { chromium } from 'playwright-core';

const urls = process.argv.slice(2);
if (urls.length === 0) {
    console.log(JSON.stringify({ ok: false, error: 'no url given', links: [] }));
    process.exit(0);
}

function resolveExecutable() {
    if (process.env.GVA_CHROMIUM_PATH) return process.env.GVA_CHROMIUM_PATH;
    return undefined; // let playwright-core use its managed browser
}

const out = { ok: false, links: [] };
const seen = new Set();

let browser;
try {
    browser = await chromium.launch({
        executablePath: resolveExecutable(),
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
    });

    for (const url of urls) {
        const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await ctx.newPage();
        try {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
            await page.waitForTimeout(2000);
            const links = await page.$$eval('a[href]', (as) =>
                as
                    .map((a) => ({ titulo: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 280), url: a.href }))
                    .filter((x) => /\.pdf(\?|$)/i.test(x.url))
            );
            for (const l of links) {
                if (!seen.has(l.url)) {
                    seen.add(l.url);
                    out.links.push({ ...l, source: url });
                }
            }
        } catch (e) {
            out.error = (out.error ? out.error + '; ' : '') + `${url}: ${e.message}`;
        } finally {
            await ctx.close();
        }
    }
    out.ok = true;
} catch (e) {
    out.error = e.message;
} finally {
    if (browser) await browser.close().catch(() => {});
}

console.log(JSON.stringify(out));
process.exit(0);
