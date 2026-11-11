# Hibira Live Chat for WordPress

A chat bubble on every page of a WordPress site, connected to a
[Hibira Live Chat](https://chat.hibira.io/?utm_source=github&utm_medium=readme&utm_campaign=live-chat)
workspace: AI answers from the business's help articles, translation both
ways, and one inbox for the team. For store and site owners: sign in, press
Connect, done.

This is a separate plugin from [Hibira Realtime](https://github.com/hibira-io/wordpress)
(realtime comments, readers now, order status for developers).

## How it works

```
wp-admin › Live Chat › Sign in with Hibira
  ──► https://chat.hibira.io/portal/connect?site=<home origin>&return_to=<this page>&state=<random>&platform=wordpress
  ──► sign in, Connect: the site is added to the workspace's sites (plan limits apply)
  ──► back to wp-admin with ?hibira_workspace=ws_…&state=… (state checked against a per-user transient)
every page ──► <script src="https://chat.hibira.io/w/v1.js" data-workspace="ws_…" async>
```

- `includes/class-hibira-lc.php`: options, the connect URL, the identity
  hash and the script tag's attributes (pure functions, unit tested).
- `includes/class-hibira-lc-widget.php`: enqueues the widget
  (`wp_enqueue_scripts`, async, footer), adds the `data-` attributes
  (`script_loader_tag`), the `[hibira_live_chat]` shortcode.
- `includes/class-hibira-lc-admin.php`: the Live Chat admin page: connect,
  paste a workspace ID, disconnect, widget options, identity secret.
- `assets/js/open.js`: `.hibira-live-chat-open` and `a[href="#hibira-live-chat"]`
  open the chat (`HibiraLiveChat.open()`), also before the widget has loaded.

Sites that are not on https (other than localhost), or whose admin is on
another origin than the site, paste the workspace ID instead and add the
site in Settings › Widget on chat.hibira.io.

wp-config.php: `HIBIRA_LIVECHAT_WORKSPACE`, `HIBIRA_LIVECHAT_IDENTITY_SECRET`.
Filters: `hibira_lc_show` (show on this page), `hibira_lc_attributes`
(the script tag's attributes), `hibira_lc_host` (another Live Chat host).

Verified visitors: `data-user-id` is the WordPress user ID and
`data-user-hash` is `hex(HMAC-SHA256(identity secret, user ID))`, the same as
pusher-server `internal/livechat/identity.go`.

## Develop

```sh
php tests/run.php          # unit tests
```

`tests/e2e/live-chat.cjs` drives a real WordPress site with Playwright (setup
in the header of the file): the admin page, one-click connect against a
stand-in chat.hibira.io, the script tag, the shortcode and staff hiding.

Release: bump `Version` in `hibira-live-chat.php` and `HIBIRA_LC_VERSION`,
`Stable tag` in `readme.txt`, update `CHANGELOG.md`, then publish a GitHub
Release tagged `v<version>`; `release` attaches `hibira-live-chat.zip` and
`wporg-deploy` publishes trunk, the tag and `.wordpress-org/` assets to
WordPress.org over SVN (secret `SVN_PASSWORD`, once the plugin is approved).

## 中文摘要

WordPress 的 Hibira Live Chat 外掛，給網站與商店老闆用：在後台「Live Chat」按「Sign in with Hibira」，
在 chat.hibira.io 登入並按「連接」，網站會自動加入工作區的網站清單並回到後台，對話泡泡就出現在每一頁，
不用複製任何程式碼。沒有 https 的網站可以改貼工作區 ID。可設定是否顯示、右下角圓形按鈕、對已登入的編輯/管理員隱藏、
顏色、跟隨網站語言或訪客瀏覽器語言；填入身分密鑰（lcid_…）後，已登入的會員會以驗證身分出現在收件匣（Starter 方案以上）。
短代碼 `[hibira_live_chat]` 與連到 `#hibira-live-chat` 的選單項目都能打開對話。這是獨立外掛，與 Hibira Realtime 外掛分開。
