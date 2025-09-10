const puppeteer = require('puppeteer');
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = 'Basic ' + Buffer.from(process.env.WP_AUTH || 'admin:password').toString('base64');

async function uploadJPEG() {
  const jpegData = Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhISEhIVFRUVFRUVFRUVFRUVFRUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lICYtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKAAoAMBIgACEQEDEQH/xAVEAEBAAAAAAAAAAAAAAAAAAAABP/aAAgBAQAAAAD/xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAhAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/AFP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AFP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AFP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/AhP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IhP/2gAMAwEAAgADAAAAECDD/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAwEBPxA//8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPxA//8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQABPxA//9k=', 'base64');
  const form = new FormData();
  form.append('file', new Blob([jpegData], { type: 'image/jpeg' }), 'test.jpg');
  const res = await fetch(`${BASE_URL}/wp-json/wp/v2/media`, {
    method: 'POST',
    headers: { Authorization: AUTH },
    body: form,
  });
  if (!res.ok) {
    throw new Error(`Upload failed: ${res.status}`);
  }
  return res.json();
}

async function createPost(content) {
  const res = await fetch(`${BASE_URL}/wp-json/wp/v2/posts`, {
    method: 'POST',
    headers: {
      Authorization: AUTH,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ title: 'Lazyload Test', content, status: 'publish' }),
  });
  if (!res.ok) {
    throw new Error(`Post creation failed: ${res.status}`);
  }
  return res.json();
}

describe('lazyload', () => {
  jest.setTimeout(30000);
  it('sets eager on hero and lazy on others', async () => {
    const media1 = await uploadJPEG();
    const media2 = await uploadJPEG();
    const post = await createPost(`
      <p><img class="gm2-hero" src="${media1.source_url}" /></p>
      <p><img src="${media2.source_url}" /></p>
    `);

    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.goto(`${BASE_URL}/?p=${post.id}`, { waitUntil: 'networkidle0' });
    const attrs = await page.evaluate(() =>
      Array.from(document.querySelectorAll('img')).map(img => ({
        loading: img.getAttribute('loading'),
        fetchpriority: img.getAttribute('fetchpriority'),
      }))
    );
    await browser.close();

    expect(attrs[0].loading).toBe('eager');
    expect(attrs[0].fetchpriority).toBe('high');
    for (let i = 1; i < attrs.length; i++) {
      expect(attrs[i].loading).toBe('lazy');
    }
  });
});
