=== Hibira Live Chat ===
Contributors: hibira
Tags: live chat, chat, customer support, chatbot, help desk
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Talk to your visitors and customers from a chat bubble on every page, with AI answers from your help articles and translation both ways.

== Description ==

Hibira Live Chat adds a chat bubble to your site. Visitors write to you, and your team answers from one shared inbox.

* **Sign in and you are done.** Press Sign in with Hibira, choose Connect, and the chat shows on your site. Your site is added to the workspace for you. No code to copy.
* **AI answers** common questions from your own help articles and pages, and hands the conversation to a person when it should.
* **Translation both ways.** A visitor writes in Japanese, you read and answer in your language, they read the answer in Japanese. The chat speaks English, Traditional and Simplified Chinese, Japanese, Korean, Thai and Vietnamese.
* **One inbox for your team**, with assignment, internal notes, who is viewing a conversation, ratings, and LINE messages next to the website chat.
* **Signed-in customers are verified.** With an identity secret, customers who are signed in to your site show in the inbox with their name and email and keep one conversation on every device.
* **Your look.** Colour, logo, greeting and layout are set on chat.hibira.io; the colour can also be set here.
* **Open the chat from your own buttons**: the `[hibira_live_chat]` shortcode, or any menu item or link to `#hibira-live-chat`.

The plugin adds one small script to your pages. The chat runs on Hibira, so your server does no extra work.

== External services ==

This plugin connects your site to Hibira Live Chat (https://chat.hibira.io), a hosted live chat service, which it needs to work.

* On every page where the chat shows, the visitor's browser loads the chat script from chat.hibira.io. When the visitor opens the chat or sends a message, the messages, the page address, their IP address, their browser language and browser details are sent to Hibira Live Chat so your team can answer them.
* If you set an identity secret, the user ID of signed-in users, and unless you turn it off their display name and email, are added to the page so the chat can verify them. The secret itself never leaves your server.
* Sign in with Hibira opens chat.hibira.io with your site's address and this settings page's address, so the site can be added to your workspace and you can come back here.

Terms: https://chat.hibira.io/legal/terms — Privacy policy: https://chat.hibira.io/legal/privacy

== Installation ==

1. Install and activate the plugin.
2. Go to Live Chat in the admin menu and press Sign in with Hibira. Create a free account if you do not have one, then press Connect.
3. That's it: the chat bubble shows on your site. Answer from the inbox at https://chat.hibira.io/inbox.

If your site is not on https, paste your workspace ID instead (Settings › Widget on chat.hibira.io) and add your site address to the workspace's sites there.

You can also define `HIBIRA_LIVECHAT_WORKSPACE` and `HIBIRA_LIVECHAT_IDENTITY_SECRET` in wp-config.php.

== Frequently Asked Questions ==

= Is there a free plan? =

Yes. The Free plan is enough to try the chat on your site. Paid plans add more agents, more AI answers, verified visitors and more; see https://chat.hibira.io/#pricing.

= The chat does not show on my site =

Check that Show the chat is ticked, that you are not hiding it from your team while signed in, and that your site address is in the workspace's sites on chat.hibira.io (Settings › Widget). One-click connect adds it for you.

= Does it work with page caching? =

Yes. The script tag is the same for every visitor unless you use an identity secret; then signed-in users get their own attributes, which caching plugins do not cache for signed-in users.

= How do I open the chat from a button? =

Add the shortcode `[hibira_live_chat label="Chat with us"]`, or `[hibira_live_chat label="Talk to sales" topic="sales"]` to mark the conversation as a sales question. A menu item or any link to `#hibira-live-chat` opens it too. Untick Chat button to hide the round button and use only your own.

== Screenshots ==

1. The chat on your site, opened from a "Chat with us" button and answered in the visitor's language.
2. One click to connect: sign in with Hibira from the Live Chat page.
3. Approve the site on chat.hibira.io, and you are sent back connected.
4. Settings: chat button, hide from your team, colour, language and verified customers.
5. Every conversation lands in the shared Hibira inbox.
6. Signed-in customers are verified, on phones and in dark mode too.

== Changelog ==

= 0.1.0 =
* First release: one-click connect, the chat on every page, colour and language, verified signed-in users, a shortcode and #hibira-live-chat links that open the chat.
