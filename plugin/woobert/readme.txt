=== Woobert ===
Contributors: anuragbhandari
Tags: woocommerce, ai, command-bar, productivity, merchant-experience
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Agentic command bar for WooCommerce merchants. Type a request in plain English and Woobert maps it to a WooCommerce REST API v3 action and runs it.

== Description ==

Press Cmd/Ctrl-K anywhere in wp-admin to open the WordPress command palette, then pick "Ask Woobert" and type a natural-language request such as "refund order 1042", "create a coupon SUMMER20 for 20% off", or "who are my top customers this month". Woobert turns the request into the matching action and executes it against the WooCommerce REST API v3 using your current admin session, so no API keys ever touch the browser. Destructive actions (refunds, deletes, status changes) ask for confirmation first.

Woobert is powered by Fern, a family of small function-calling models by Fernfly. See https://fernfly.com.

== Installation ==

1. Build the front-end: `npm install && npm run build` in this plugin directory.
2. Activate the plugin (WooCommerce must be active).
3. Under WooCommerce -> Woobert, set your inference endpoint URL and API key.
4. Press Cmd/Ctrl-K in wp-admin.

== Changelog ==

= 0.1.0 =
* Initial release: "Ask Woobert" command in the WordPress command palette, resolve/execute inference proxy, confirmation for destructive actions.
