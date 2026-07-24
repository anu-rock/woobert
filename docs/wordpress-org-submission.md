# Publishing to the WordPress.org plugin directory

Everything needed to submit Hoobert, plus what still has to be decided. Sources:
[Add your plugin](https://wordpress.org/plugins/developers/add/),
[detailed guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/),
[plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/),
[common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/).

## The name

The plugin was called **Woobert** until July 2026. It could not have shipped under
that name: WordPress.org treats "woo" as restricted on behalf of Automattic, and a
slug is **permanent once approved**, so there would have been no fixing it later.

The mechanics, since they matter when naming anything else in this project. "woo"
is the only entry in Plugin Check's `PORTMANTEAUS` list (`Trademarks_Check.php`),
matched with `stripos( $slug, 'woo' ) === 0`. The bar is that a slug may not
*begin* with "woo". The message the tool prints ("cannot be used at all")
overstates its own rule, but "woobert" failed either way. This sits under
[guideline 17](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#17-plugins-must-respect-trademarks-copyrights-and-project-names).

To screen a candidate name without renaming anything, extract `TRADEMARK_SLUGS`
and `PORTMANTEAUS` from that file and run the slug through the same two loops.
Terms ending in `-` are prefix-only; the rest match anywhere, minus the
`for-TRADEMARK` / `with-TRADEMARK` exceptions. "hoobert" is clear on both lists.

WooCommerce may still appear *after* the product name, which is why the readme
title is `Hoobert - AI Command Bar for WooCommerce`. That "X for WooCommerce" form
is explicitly allowed.

## Assets

Sources live in `assets-src/`, outputs in `.wordpress-org/`. Regenerate with:

```bash
node scripts/build-wporg-assets.mjs
```

It rasterises through headless Chrome, so there are no npm dependencies. Set
`CHROME_BIN` if Chrome is somewhere unusual.

| File | Size | Source | Status |
| --- | --- | --- | --- |
| `.wordpress-org/icon-128x128.png` | 128x128 | `assets-src/hoobert-owl.png` | done |
| `.wordpress-org/icon-256x256.png` | 256x256 | same | done |
| `.wordpress-org/banner-772x250.png` | 772x250 | `assets-src/banner.html` | done |
| `.wordpress-org/banner-1544x500.png` | 1544x500 | same | done |
| `.wordpress-org/screenshot-1..7.png` | any | shot by hand, see below | done |
| `.wordpress-org/blueprints/blueprint.json` | - | hand-written | done |

### About the owl

`assets-src/hoobert-owl.png` is the original "funny owl" artwork by agustrisana
on Flaticon. It is the mark for **every** output: the directory icons, the owl in
the banner, and the copy shipped inside the plugin for the settings screen.

`assets-src/hoobert-owl.svg` is a vector redraw in the Fernfly palette, kept only
as a stand-in for when the original is missing. It is close but not identical, so
the two must never appear in the same build. The script enforces that: whichever
source it picks drives all outputs, and it prints which one it used.

That is also why `.wordpress-org/icon.svg` is **not** committed. wp.org serves
`icon.svg` to modern browsers with the PNGs as fallback, so shipping the redraw
next to PNGs of the original would give the listing two different icons depending
on the browser. The script emits `icon.svg` only when the redraw is the mark, and
deletes it otherwise. SVG is optional in the wp.org spec; the PNGs are not.

The plugin always ships `assets/hoobert-owl.png`, rasterised at 256px from
whichever source applies, so `class-settings.php` has one predictable filename to
reference.

The Flaticon free licence requires attribution wherever the icon appears. It is
in three places: the plugin settings screen (`Hoobert_Settings::render_credits`),
`readme.txt`, and the repository README.

## Compliance

Verified by running WordPress.org's own tool against the plugin:

```bash
docker compose run --rm --entrypoint wp wpcli plugin install plugin-check --activate
docker compose run --rm --entrypoint wp wpcli plugin check hoobert \
  --exclude-directories=node_modules,src,scripts,build --format=csv
```

Current result: **zero errors, zero warnings other than the naming ones above.**
Re-run it before every submission.

What the guidelines ask for, and where it is satisfied:

| Guideline | Where |
| --- | --- |
| 1. GPL-compatible licence | `LICENSE` (GPL-2.0), headers in `hoobert.php` and `readme.txt` |
| 2. Stable tag matches a real version | `bump-version.js` keeps `Stable tag` in step with the plugin header |
| 4. No obfuscation, source available | `src/` is committed; readme links the repo; the build is `wp-scripts` |
| 5. No trialware or locked features | Everything in the plugin works; only the model is external |
| 7. External services documented | `== External service ==` in readme.txt, plus the settings screen |
| 7. No calling home without consent | No request until both settings are saved and the merchant runs a command |
| 8. SaaS integration, not remote code | Only tool names and arguments come back; execution is local against `wc/v3` |
| 9. No tracking without opt-in | None. No analytics, no telemetry, no phone-home |
| 10. "Powered by" is not forced | No storefront output at all |
| 11. Unique prefixes | `Hoobert_`, `HOOBERT_`, `hoobert_`, `hoobert/v1` |
| 12. No hidden files | `.DS_Store` removed; the release workflow strips them from the zip |
| 13. No unneeded files | The zip ships only `hoobert.php`, `includes/`, `assets/`, `build/`, `tools.json`, `readme.txt`, `LICENSE` |
| 17. Trademarks | Clear. `hoobert` passes both of Plugin Check's lists; see **The name** above |

Security specifics reviewers look for, all present: `ABSPATH` guards on every PHP
file, `permission_callback` on all three REST routes, `manage_woocommerce` checks,
`wp_rest` nonce on every request, `$wpdb->prepare()` on every query, escaped
output throughout, `wp_remote_post` rather than cURL, and no `ALLOW_UNFILTERED_UPLOADS`.

Three things changed during this pass, worth knowing about:

- **The settings page no longer echoes the API key.** It used to render the stored
  key as the `value` of the password field, which put the secret in plain text in
  the DOM on every page load: readable in view-source, and captured by any
  screenshot or screen share of that page. The field now renders empty with a
  "Saved" placeholder, an empty submission keeps the existing key, and there is an
  explicit checkbox to remove it. This matters immediately, because a settings-page
  screenshot is going in the public listing.
- **The bundle only loads for users who can use it.** `admin_enqueue_scripts` was
  enqueueing the palette JS and CSS on every wp-admin screen for every user,
  including ones without `manage_woocommerce` who get a dead command. It is now
  gated on the capability.
- **A privacy policy suggestion is registered.** `Hoobert_Settings::privacy_policy_content()`
  hooks `wp_add_privacy_policy_content`, so the suggested wording shows up under
  Settings -> Privacy for merchants writing their own policy. Not required, but
  it is the expected courtesy for a plugin with an external service, and it saves
  a round of reviewer questions.

## Submitting

1. Build the zip exactly as the release workflow does: a single top-level folder
   named after the slug, containing only the shipped files.
2. Upload at <https://wordpress.org/plugins/developers/add/> while logged in.
   Assets are **not** part of this zip; they go to SVN after approval.
3. Wait. Review takes 1 to 10 days, usually under 5 business days. Expect at least
   one round of questions, most likely about the external service.
4. On approval you get SVN write access and a permanent slug.

## After approval

Add `SVN_USERNAME` and `SVN_PASSWORD` as repository secrets and the release
workflow publishes every subsequent release automatically: `trunk`, a version tag,
and everything in `.wordpress-org/` copied to the SVN `assets/` directory. Until
those secrets exist the deploy step is skipped, so it is safe to leave enabled.

Update `SLUG` in that step if the name changes.

Note that directory assets are cached hard by the CDN. A new banner or icon can
take minutes, and up to six hours under load, to appear.
