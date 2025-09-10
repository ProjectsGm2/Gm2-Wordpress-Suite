const puppeteer = require('puppeteer');
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost';
const AUTH = 'Basic ' + Buffer.from(process.env.WP_AUTH || 'admin:password').toString('base64');

async function uploadJPEG() {
  const jpegData = Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhISEhIVFRUVFRUVFRUVFRUVFRUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lICYtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKAAoAMBIgACEQEDEQH/xAAVEAEBAAAAAAAAAAAAAAAAAAAABP/aAAgBAQAAAAD/xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAhAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/AFP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AFP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AFP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/AhP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IhP/2gAMAwEAAgADAAAAECDD/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAwEBPxA//8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPxA//8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQABPxA//9k=', 'base64');
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

describe('next-gen picture', () => {
  jest.setTimeout(30000);
  it('serves avif and webp with img fallback', async () => {
    const media = await uploadJPEG();
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.goto(`${BASE_URL}/?attachment_id=${media.id}`, { waitUntil: 'networkidle0' });
    const hasPicture = await page.evaluate(() => {
      const pic = document.querySelector('picture');
      if (!pic) return false;
      const types = Array.from(pic.querySelectorAll('source')).map(s => s.getAttribute('type'));
      const img = pic.querySelector('img');
      return types.includes('image/avif') && types.includes('image/webp') && !!img;
    });
    await browser.close();
    expect(hasPicture).toBe(true);
  });
});
