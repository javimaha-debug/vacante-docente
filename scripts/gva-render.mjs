#!/usr/bin/env node
// Headless renderer for the GVA's JavaScript-rendered Liferay pages.
//
// The adjudicaciones / inicio / contínues pages load their content via JS, so a
// plain HTTP fetch sees nothing. This script renders the page with a headless
// Chromium and prints, as JSON, every listing PDF it finds:
//
//   node scripts/gva-render.mjs <url> [<url> ...]
//   → {"ok":true,"links":[{"titulo":"...","url":"https://.../x.pdf"}, ...]}
//
// IMPORTANT: most listing PDFs (provisional participants, vacants, …) are NOT
// linked directly on the landing page — they live one hop deeper, inside the
// Liferay news-article pages ("/-/<slug>"). So we also follow relevant article
// links found on the landing page and extract the PDFs inside them.
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

// Keywords (accent-insensitive) that mark a news-article link as worth
// following from the landing page to look for listing PDFs inside it.
const FOLLOW_KEYWORDS = [
    'provisional', 'definitiu', 'definitiva', 'participant', 'vacant', 'vacante',
    'adjudicaci', 'borsa', 'bolsa', 'interi', 'interino', 'suprimit', 'desplac',
    'llistat', 'llista', 'barem', 'baremo', 'admes', 'admis', 'exclos', 'exclui',
];

// Hard cap on how many article pages we follow per landing page, to bound runtime.
const MAX_FOLLOW = 12;

const norm = (s) => (s || '').toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '');

const out = { ok: false, links: [] };
const seen = new Set();

async function extractPdfLinks(page) {
    return page.$$eval('a[href]', (as) =>
        as
            .map((a) => ({
                titulo: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 280),
                url: a.href,
            }))
            .filter((x) => /\.pdf(\?|$)/i.test(x.url))
    );
}

// Article ("/-/<slug>") links on a Liferay landing page whose text looks
// relevant. Returns [{url, titulo}].
async function extractArticleLinks(page, keywords) {
    return page.$$eval(
        'a[href]',
        (as, kws) => {
            const norm = (s) => (s || '').toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '');
            const result = [];
            const seen = new Set();
            for (const a of as) {
                const href = a.href;
                // Liferay friendly-URL articles contain "/-/". Skip PDFs (handled
                // separately) and obvious non-content (login, rss, mailto…).
                if (!href || !href.includes('/-/')) continue;
                if (/\.pdf(\?|$)/i.test(href)) continue;
                if (/^(mailto:|javascript:|tel:)/i.test(href)) continue;
                const text = norm((a.textContent || '').replace(/\s+/g, ' ').trim());
                if (!text) continue;
                if (!kws.some((k) => text.includes(k))) continue;
                if (seen.has(href)) continue;
                seen.add(href);
                result.push({ url: href, titulo: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 280) });
            }
            return result;
        },
        keywords
    );
}

let browser;
try {
    browser = await chromium.launch({
        executablePath: resolveExecutable(),
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
    });

    const followKw = FOLLOW_KEYWORDS.map(norm);

    for (const url of urls) {
        const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await ctx.newPage();
        const articleLinks = [];
        try {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
            await page.waitForTimeout(2000);

            // 1) Direct PDF links on the landing page.
            for (const l of await extractPdfLinks(page)) {
                if (!seen.has(l.url)) {
                    seen.add(l.url);
                    out.links.push({ ...l, source: url });
                }
            }

            // 2) Collect relevant article links to follow.
            articleLinks.push(...await extractArticleLinks(page, followKw));
        } catch (e) {
            out.error = (out.error ? out.error + '; ' : '') + `${url}: ${e.message}`;
        } finally {
            await ctx.close();
        }

        // 3) Follow each relevant article and harvest the PDFs inside it. The
        // article's own title prefixes the PDF link text so classification has
        // context even when the inner link is just "descarregar" / a filename.
        let followed = 0;
        for (const art of articleLinks) {
            if (followed >= MAX_FOLLOW) break;
            followed++;
            const ctx2 = await browser.newContext({ ignoreHTTPSErrors: true });
            const page2 = await ctx2.newPage();
            try {
                // Article bodies render fast; domcontentloaded keeps the crawl
                // within the process timeout even with a dozen articles.
                await page2.goto(art.url, { waitUntil: 'domcontentloaded', timeout: 25000 });
                await page2.waitForTimeout(1200);
                for (const l of await extractPdfLinks(page2)) {
                    if (seen.has(l.url)) continue;
                    seen.add(l.url);
                    const inner = l.titulo && l.titulo.length > 3 ? l.titulo : '';
                    const titulo = inner && norm(inner) !== norm(art.titulo)
                        ? `${art.titulo} — ${inner}`
                        : art.titulo;
                    out.links.push({ titulo: titulo.slice(0, 280), url: l.url, source: art.url });
                }
            } catch (e) {
                out.error = (out.error ? out.error + '; ' : '') + `${art.url}: ${e.message}`;
            } finally {
                await ctx2.close();
            }
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
