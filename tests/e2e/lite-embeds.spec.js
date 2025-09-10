const puppeteer = require('puppeteer');
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = 'Basic ' + Buffer.from(process.env.WP_AUTH || 'admin:password').toString('base64');

async function createPost(content) {
  const res = await fetch(`${BASE_URL}/wp-json/wp/v2/posts`, {
    method: 'POST',
    headers: {
      Authorization: AUTH,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ title: 'Lite Embed Test', content, status: 'publish' }),
  });
  if (!res.ok) {
    throw new Error(`Post creation failed: ${res.status}`);
  }
  return res.json();
}

describe('lite embeds', () => {
  jest.setTimeout(30000);
  it('loads youtube iframe on interaction', async () => {
    const videoId = 'M7lc1UVf-VE';
    const post = await createPost(`[gm2_lite_youtube id="${videoId}"]`);

    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.goto(`${BASE_URL}/?p=${post.id}`, { waitUntil: 'networkidle0' });

    const { hasIframe, posterBg } = await page.evaluate(() => ({
      hasIframe: !!document.querySelector('iframe'),
      posterBg: getComputedStyle(document.querySelector('.gm2-lite-embed__poster')).backgroundImage,
    }));

    expect(hasIframe).toBe(false);
    expect(posterBg).toMatch(/ytimg\.com/);

    await page.click('.gm2-lite-embed');
    await page.waitForSelector(`iframe[src*="youtube.com/embed/${videoId}"]`, { timeout: 5000 });
    const iframeSrc = await page.$eval('iframe', el => el.src);

    await browser.close();
    expect(iframeSrc).toContain(`youtube.com/embed/${videoId}`);
  });
});
