import { test, expect } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = process.env.WP_AUTH || 'admin:password';
const [username, password] = AUTH.split(':');

test('preset wizard applies a blueprint', async ({ page }) => {
  await page.goto(`${BASE_URL}/wp-login.php`);
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForURL(`${BASE_URL}/wp-admin/**`);

  await page.goto(`${BASE_URL}/wp-admin/admin.php?page=gm2-custom-posts`);
  await page.waitForSelector('#gm2-preset-wizard-root select');

  const presetSelect = page.locator('#gm2-preset-wizard-root select');
  await presetSelect.selectOption({ value: 'courses' });

  const applyButton = page.getByRole('button', { name: /Apply preset/i });
  await applyButton.click();

  const confirmButton = page.getByRole('button', { name: /Apply preset anyway/i });
  try {
    await confirmButton.waitFor({ state: 'visible', timeout: 2000 });
    await confirmButton.click();
  } catch (error) {
    // Confirmation modal did not appear.
  }

  const notice = page.locator('#gm2-preset-wizard-root .components-notice__content');
  await expect(notice).toContainText('Preset applied');

  await page.goto(`${BASE_URL}/wp-admin/admin.php?page=gm2-custom-posts`);
  const slugCells = page.locator('table.wp-list-table tbody td.column-slug');
  await expect(slugCells).toContainText('course');
});
