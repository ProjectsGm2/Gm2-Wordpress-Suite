const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

let hasNode = false;
try {
  const result = execSync("php -r \"include 'tests/bootstrap.php'; echo class_exists('AE_CSS_Optimizer') && AE_CSS_Optimizer::has_node_capability() ? 1 : 0;\"").toString().trim();
  hasNode = result === '1';
} catch (e) {
  hasNode = false;
}

let hasServer = true;
try {
  execSync('curl -I --silent --fail http://localhost/');
} catch (e) {
  hasServer = false;
}

const describeOrSkip = (hasNode && hasServer) ? describe : describe.skip;

describeOrSkip('render regression', () => {
  const puppeteer = require('puppeteer');
  const pixelmatch = require('pixelmatch');
  const { PNG } = require('pngjs');

  const fixturesPath = path.join(__dirname, '__fixtures__', 'render-regression.json');
  let baselines = {};
  try {
    baselines = JSON.parse(fs.readFileSync(fixturesPath, 'utf8'));
  } catch (e) {}

  let targets = ['/', '/blog/'];
  try {
    const woo = execSync("php -r \"include 'tests/bootstrap.php'; echo class_exists('WooCommerce') ? 1 : 0;\"").toString().trim() === '1';
    if (woo) {
      targets.push('/shop/');
    }
  } catch (e) {}

  let browser;
  beforeAll(async () => {
    browser = await puppeteer.launch();
  });

  afterAll(async () => {
    if (browser) {
      await browser.close();
    }
  });

  targets.forEach(pagePath => {
    test(`optimizes CSS for ${pagePath}`, async () => {
      const page = await browser.newPage();
      const base = `http://localhost${pagePath}`;
      const baselineUrl = `${base}?ae-css-async=0`;

      await page.goto(baselineUrl, { waitUntil: 'networkidle0' });
      const baselineCSS = await page.evaluate(() =>
        performance.getEntriesByType('resource')
          .filter(r => r.initiatorType === 'link' && r.name.endsWith('.css'))
          .reduce((sum, r) => sum + (r.transferSize || r.encodedBodySize || 0), 0)
      );
      const baselineImage = await page.screenshot();

      await page.goto(base, { waitUntil: 'networkidle0' });
      const optimizedCSS = await page.evaluate(() =>
        performance.getEntriesByType('resource')
          .filter(r => r.initiatorType === 'link' && r.name.endsWith('.css'))
          .reduce((sum, r) => sum + (r.transferSize || r.encodedBodySize || 0), 0)
      );
      const optimizedImage = await page.screenshot();
      await page.close();

      expect(optimizedCSS).toBeLessThan(baselineCSS);

      const img1 = PNG.sync.read(baselineImage);
      const img2 = PNG.sync.read(optimizedImage);
      const diff = pixelmatch(img1.data, img2.data, null, img1.width, img1.height, { threshold: 0.01 });
      const diffRatio = diff / (img1.width * img1.height);
      expect(diffRatio).toBeLessThanOrEqual(0.01);

      const expectedBaseline = baselines[pagePath];
      if (expectedBaseline > 0) {
        expect(baselineCSS).toBeLessThanOrEqual(expectedBaseline);
      }
    }, 30000);
  });
});
