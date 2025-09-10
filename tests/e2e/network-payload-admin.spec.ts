import { test, expect } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = process.env.WP_AUTH || 'admin:password';
const [username, password] = AUTH.split(':');

// Check that toggling each Network Payload setting persists across reloads.
test('network payload settings persist', async ({ page }) => {
  // Log in as admin.
  await page.goto(`${BASE_URL}/wp-login.php`);
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForURL(`${BASE_URL}/wp-admin/**`);

  const tabs: Record<string, string[]> = {
    images: ['nextgen_images', 'webp', 'avif', 'no_originals'],
    compression: ['fallback_gzip'],
    lazy: ['smart_lazyload', 'auto_hero', 'lite_embeds'],
    scripts: ['asset_budget'],
  };

  for (const [tab, features] of Object.entries(tabs)) {
    await page.goto(`${BASE_URL}/wp-admin/admin.php?page=gm2_netpayload&tab=${tab}`);
    for (const feature of features) {
      const checkbox = page.locator(`input[name="gm2_netpayload_settings[${feature}]"]`);
      if (await checkbox.isChecked()) {
        await checkbox.uncheck();
      }
      await checkbox.check();
    }

    await page.click('text=Save Changes');
    await page.waitForSelector('div.updated, div.notice-success');
    await page.reload();

    for (const feature of features) {
      await expect(
        page.locator(`input[name="gm2_netpayload_settings[${feature}]"]`)
      ).toBeChecked();
    }
  }
});
