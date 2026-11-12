// End-to-end run against a real WordPress site and a local Hibira Live Chat server.
//
//   LC_SESSION=<agent session cookie> node tests/e2e/live-chat.cjs [screenshot dir]
//
// Needs: WordPress on http://localhost:8090 (admin / admin-local-pass, a
// subscriber mei / mei-local-pass, a page /contact/ with
// [hibira_live_chat label="Chat with us"]), this plugin active and an
// mu-plugin that points hibira_lc_host at http://localhost:6001;
// pusher-server with LIVECHAT_ENABLED and LIVECHAT_PUBLIC_URL=http://localhost:6001,
// an agent (owner) of a workspace signed in with the session cookie
// LC_SESSION, and the workspace's identity secret in IDENTITY_SECRET.
const { chromium } = require('playwright');
const { execSync } = require('child_process');

const SITE = process.env.WP_URL || 'http://localhost:8090';
const LC = process.env.LC_URL || 'http://localhost:6001';
const OUT = process.argv[2] || '.';
const WP = process.env.WP_CLI || 'wp';
const SCALE = Number(process.env.SCALE || 2);

function expect(ok, what) {
  if (!ok) throw new Error('expected: ' + what);
  console.log('ok -', what);
}

function wp(args) {
  return execSync(`${WP} ${args}`, { encoding: 'utf8' }).trim();
}

async function login(page, user, pass) {
  await page.goto(SITE + '/wp-login.php');
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin|\/$/);
}

const scriptTag = (page) => page.evaluate(() => {
  const s = document.querySelector('script[data-workspace]');
  return s ? Object.fromEntries([...s.attributes].map((a) => [a.name, a.value])) : null;
});

(async () => {
  for (const o of ['workspace', 'identity_secret', 'launcher', 'color', 'lang']) {
    try { execSync(`${WP} option delete hibira_lc_${o}`, { stdio: 'ignore' }); } catch (e) { /* not set */ }
  }
  wp('option update hibira_lc_hide_for_staff no');
  const browser = await chromium.launch();
  const lcCookie = { name: 'hlc_session', value: process.env.LC_SESSION, domain: 'localhost', path: '/' };

  // 1. Not connected: no widget, and the admin page offers one-click connect.
  const admin = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: SCALE });
  await admin.addCookies([lcCookie]);
  const a = await admin.newPage();
  a.on('pageerror', (e) => console.log('admin pageerror', e.message));
  await login(a, 'admin', 'admin-local-pass');
  await a.goto(SITE + '/');
  expect(!(await scriptTag(a)), 'no widget before connecting');
  await a.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat');
  await a.waitForSelector('text=Sign in with Hibira');
  await a.screenshot({ path: `${OUT}/admin-not-connected-en-desktop-light.png` });

  // 2. Sign in with Hibira → chat.hibira.io/portal/connect → Connect → back.
  await a.click('text=Sign in with Hibira');
  await a.waitForURL(LC + '/portal/connect?**');
  const q = new URL(a.url()).searchParams;
  expect(q.get('site') === SITE && q.get('platform') === 'wordpress' && q.get('return_to').startsWith(SITE + '/wp-admin/'), 'connect page gets the site and this settings page');
  await a.locator('main button.btn.primary', { hasText: 'Connect' }).waitFor();
  await a.waitForTimeout(400);
  await a.screenshot({ path: `${OUT}/connect-en-desktop-light.png` });
  await a.locator('main button.btn.primary', { hasText: 'Connect' }).click();
  await a.waitForURL(/hibira_lc=connected/);
  const ws = wp('option get hibira_lc_workspace');
  expect(/^ws_/.test(ws), 'workspace saved: ' + ws);
  await a.waitForSelector('text=Connected to');
  await a.screenshot({ path: `${OUT}/admin-connected-en-desktop-light.png`, fullPage: true });

  // A replayed return (same state) is refused.
  await a.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat&hibira_workspace=ws_evil&state=' + q.get('state'));
  expect((await a.url()).includes('hibira_lc=bad_state') && wp('option get hibira_lc_workspace') === ws, 'a replayed state is refused');

  // 3. A visitor sees the chat, and the shortcode button opens it.
  const visitor = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: SCALE, locale: 'ja-JP' });
  const v = await visitor.newPage();
  v.on('pageerror', (e) => console.log('visitor pageerror', e.message));
  await v.goto(SITE + '/contact/');
  const tag = await scriptTag(v);
  expect(tag && tag['data-workspace'] === ws && tag.async !== undefined && !tag['data-user-id'], 'script tag with the workspace, async, no user');
  await v.waitForFunction(() => !!window.HibiraLiveChat, null, { timeout: 15000 });
  await v.click('.hibira-live-chat-open');
  const box = v.locator('textarea').first();
  await box.waitFor({ state: 'visible', timeout: 10000 });
  expect(true, 'shortcode button opens the chat');
  await box.fill('日本への配送はできますか？');
  await v.keyboard.press('Enter');
  await v.waitForTimeout(2500);
  await v.screenshot({ path: `${OUT}/site-chat-ja-desktop-light.png` });

  // 4. The message reaches the inbox.
  await a.goto(LC + '/inbox');
  await a.waitForSelector('text=日本への配送はできますか', { timeout: 15000 });
  await a.waitForTimeout(800);
  await a.screenshot({ path: `${OUT}/inbox-en-desktop-light.png` });
  expect(true, 'the visitor message is in the inbox');

  // 5. Options: hide from staff, no button, colour, site language.
  await a.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat');
  await a.check('#hibira-lc-hide-for-staff');
  await a.uncheck('#hibira-lc-launcher');
  await a.selectOption('#hibira-lc-lang', 'site');
  await a.evaluate(() => { document.querySelector('#hibira-lc-color').value = '#0e7490'; });
  await a.click('#submit');
  await a.waitForSelector('text=Settings saved');
  await a.goto(SITE + '/');
  expect(!(await scriptTag(a)), 'hidden from a signed-in administrator');
  await v.goto(SITE + '/');
  const t2 = await scriptTag(v);
  expect(t2['data-launcher'] === 'none' && t2['data-color'] === '#0e7490' && t2['data-lang'] === 'en-us', 'launcher off, colour and site language on the tag');

  // 6. Verified visitors: a signed-in customer gets a signed ID.
  await a.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat');
  await a.fill('#hibira-lc-secret', process.env.IDENTITY_SECRET);
  await a.check('#hibira-lc-launcher');
  await a.click('#submit');
  await a.waitForSelector('text=Settings saved');
  const shopper = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: SCALE, colorScheme: 'dark', locale: 'zh-TW' });
  const s = await shopper.newPage();
  await login(s, 'mei', 'mei-local-pass');
  await s.goto(SITE + '/contact/');
  const t3 = await scriptTag(s);
  const id = wp('user get mei --field=ID');
  const want = require('crypto').createHmac('sha256', process.env.IDENTITY_SECRET).update(id).digest('hex');
  expect(t3['data-user-id'] === id && t3['data-user-hash'] === want && t3['data-user-name'] === 'Mei Lin' && t3['data-user-email'] === 'mei@example.com', 'signed-in customer has a verified ID, name and email');
  expect(!(await a.content()).includes(process.env.IDENTITY_SECRET), 'the secret is never printed back');
  await s.waitForFunction(() => !!window.HibiraLiveChat, null, { timeout: 15000 });
  await s.click('.hibira-live-chat-open');
  const sbox = s.locator('textarea').first();
  await sbox.waitFor({ state: 'visible', timeout: 10000 });
  await sbox.fill('我的訂單什麼時候出貨？');
  await s.keyboard.press('Enter');
  await s.waitForTimeout(2500);
  await s.screenshot({ path: `${OUT}/site-chat-verified-zh-TW-mobile-dark.png` });

  // 7. The admin page on a phone, dark.
  const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: SCALE, colorScheme: 'dark' });
  await phone.addCookies(await admin.cookies());
  const p = await phone.newPage();
  await p.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat');
  await p.waitForSelector('text=Connected to');
  await p.screenshot({ path: `${OUT}/admin-connected-en-mobile.png`, fullPage: true });

  // 8. Disconnect removes the widget.
  await a.goto(SITE + '/wp-admin/admin.php?page=hibira-live-chat');
  await a.click('text=Disconnect');
  await a.waitForURL(/hibira_lc=disconnected/);
  await v.goto(SITE + '/');
  expect(!(await scriptTag(v)), 'no widget after disconnecting');

  await browser.close();
  console.log('all checks passed');
})().catch((e) => { console.error(e); process.exit(1); });
