# Publishing to the WordPress.org plugin directory

Everything needed to submit Woobert, plus what still has to be decided. Sources:
[Add your plugin](https://wordpress.org/plugins/developers/add/),
[detailed guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/),
[plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/),
[common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/).

## Blocker: the name

**"Woobert" cannot be published under that name or slug.** This is not a judgement
call; it is a hard automated rejection. WordPress.org's own Plugin Check tool reports:

```
WARNING  trademarked_term  The plugin name includes a restricted term. Your chosen
plugin name - "Woobert" - contains the restricted term "woo" which cannot be used
at all in your plugin name.

WARNING  trademarked_term  The plugin slug includes a restricted term. Your plugin
slug - "woobert" - contains the restricted term "woo" which cannot be used at all
in your plugin slug.
```

The rule is [guideline 17](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#17-plugins-must-respect-trademarks-copyrights-and-project-names):
trademarked terms may not be the sole or initial term of a slug. "woo" is on the
restricted list on behalf of Automattic, and the check is a substring match, so the
portmanteau does not get a pass. The slug is also **permanent once approved**, so
there is no fixing it later.

Two things follow:

1. **Pick a directory name without "woo" in it.** WooCommerce may still appear
   *after* the product name, in the "X for WooCommerce" form the guidelines
   explicitly bless. So `<Name> - AI Command Bar for WooCommerce` is fine as the
   display name as long as `<Name>` is clean.
2. **Decide whether the product renames or only the listing does.** The directory
   name and the slug must be clean. The owl, the settings page, the docs, and the
   marketing site can keep whatever branding you want, but a listing that calls
   itself one thing while every screen says another is a bad look and reviewers
   do comment on it.

Renaming touches: `Plugin Name` in `plugin/woobert/woobert.php`, the readme title,
the `woobert` text domain, the `Woobert_` class prefix, the `WOOBERT_*` constants,
the `woobert_options` option, the `woobert/v1` REST namespace, the
`{prefix}woobert_history` table, the `woobert` JS global, the plugin folder name,
the zip name in the release workflow and blueprint, and `SLUG` in the deploy step.
Option and table names need a migration if any install already exists; today none
do, so this is the cheapest it will ever be.

Everything below is ready and unaffected by the naming decision, except where noted.

## Assets

Sources live in `assets-src/`, outputs in `.wordpress-org/`. Regenerate with:

```bash
node scripts/build-wporg-assets.mjs
```

It rasterises through headless Chrome, so there are no npm dependencies. Set
`CHROME_BIN` if Chrome is somewhere unusual.

| File | Size | Source | Status |
| --- | --- | --- | --- |
| `.wordpress-org/icon-128x128.png` | 128x128 | `assets-src/woobert-owl.svg` | done |
| `.wordpress-org/icon-256x256.png` | 256x256 | same | done |
| `.wordpress-org/icon.svg` | vector | same | done |
| `.wordpress-org/banner-772x250.png` | 772x250 | `assets-src/banner.html` | done |
| `.wordpress-org/banner-1544x500.png` | 1544x500 | same | done |
| `.wordpress-org/screenshot-1..8.png` | any | you | **to do** |
| `.wordpress-org/blueprints/blueprint.json` | - | hand-written | done |

### About the owl

`assets-src/woobert-owl.svg` is a vector redraw of the "funny owl" icon by
agustrisana on Flaticon, in the Fernfly palette. **If you have the original PNG,
drop it in as `assets-src/woobert-owl.png`** and re-run the build; the script
prefers it for the icons automatically. The redraw exists so the pipeline works
without it, not because it is better.

The Flaticon free licence requires attribution wherever the icon appears. It is
in three places: the plugin settings screen (`Woobert_Settings::render_credits`),
`readme.txt`, and the repository README.

### Screenshots

Filenames must be lowercase `screenshot-1.png` through `screenshot-N.png`, and the
numbering must line up with the captions already written into readme.txt's
`== Screenshots ==` section. The captions are ordered as a story: invoke, preview,
confirm, result, page context, reports, settings, audit log. Reorder the captions
if you shoot them in a different order.

Practical notes: shoot at 2x on a 1280-wide admin window, crop to the palette or
modal rather than the whole screen, and use the seeded demo store so no real
customer data appears. Anything with a customer name or email in it will be
public forever.

**Animated GIFs are not in the documented spec.** The assets page lists
`screenshot-N.(png|jpg)`; GIF appears only in the icon formats. If you want motion
on the listing, two options that definitely work:

- **Live Preview.** `.wordpress-org/blueprints/blueprint.json` is committed, which
  turns on the "Live Preview" button on the plugin page: a real, throwaway store
  with WooCommerce, Woobert, and sample data, running in the visitor's browser.
  That beats a GIF. Note it still needs an endpoint and key to actually resolve a
  command, so decide whether to point the preview at a rate-limited public Fernfly
  project or let it demo the UI and settings only.
- **Video embed.** readme.txt renders a bare YouTube or Vimeo URL on its own line
  as an embedded player. A 30-second screen recording in the description reads
  better than a GIF screenshot anyway.

## Compliance

Verified by running WordPress.org's own tool against the plugin:

```bash
docker compose run --rm --entrypoint wp wpcli plugin install plugin-check --activate
docker compose run --rm --entrypoint wp wpcli plugin check woobert \
  --exclude-directories=node_modules,src,scripts,build --format=csv
```

Current result: **zero errors, zero warnings other than the naming ones above.**
Re-run it before every submission.

What the guidelines ask for, and where it is satisfied:

| Guideline | Where |
| --- | --- |
| 1. GPL-compatible licence | `LICENSE` (GPL-2.0), headers in `woobert.php` and `readme.txt` |
| 2. Stable tag matches a real version | `bump-version.js` keeps `Stable tag` in step with the plugin header |
| 4. No obfuscation, source available | `src/` is committed; readme links the repo; the build is `wp-scripts` |
| 5. No trialware or locked features | Everything in the plugin works; only the model is external |
| 7. External services documented | `== External service ==` in readme.txt, plus the settings screen |
| 7. No calling home without consent | No request until both settings are saved and the merchant runs a command |
| 8. SaaS integration, not remote code | Only tool names and arguments come back; execution is local against `wc/v3` |
| 9. No tracking without opt-in | None. No analytics, no telemetry, no phone-home |
| 10. "Powered by" is not forced | No storefront output at all |
| 11. Unique prefixes | `Woobert_`, `WOOBERT_`, `woobert_`, `woobert/v1` (all rename together) |
| 12. No hidden files | `.DS_Store` removed; the release workflow strips them from the zip |
| 13. No unneeded files | The zip ships only `woobert.php`, `includes/`, `assets/`, `build/`, `tools.json`, `readme.txt`, `LICENSE` |
| 17. Trademarks | **Unresolved. See the blocker above.** |

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
- **A privacy policy suggestion is registered.** `Woobert_Settings::privacy_policy_content()`
  hooks `wp_add_privacy_policy_content`, so the suggested wording shows up under
  Settings -> Privacy for merchants writing their own policy. Not required, but
  it is the expected courtesy for a plugin with an external service, and it saves
  a round of reviewer questions.

## Before you submit

- [ ] Settle the name and slug, then rename everything in the list above.
- [ ] Confirm `Contributors: anuragbhandari` is the wp.org account that will own
      the listing. Add any co-maintainers now; the field is a comma-separated list
      of wp.org usernames, not display names.
- [ ] Verify `Requires at least: 6.6` on a real 6.6 install before submitting. The
      floor is set by the front-end bundle, not by choice: `build/index.asset.php`
      declares `react-jsx-runtime`, which core registers as a script handle in 6.6
      (`wp-includes/script-loader.php`) and does not have in 6.5. On 6.5 the
      enqueue silently drops the bundle and the command bar never appears, with no
      error. **Do not lower it below 6.6** without changing the JSX build.
      Note the practical floor is higher than the declared one: current WooCommerce
      requires WP 6.9, so a merchant on 6.6 is necessarily on an older WooCommerce.
      Woobert declares `WC requires at least: 8.0`; the REST v3 routes it calls are
      stable across WooCommerce 8.x to 10.x, but that pairing is the one to test.
- [ ] Set `Tested up to` to the WordPress version you last ran it against (7.0.2
      at time of writing). Reviewers do check this, and a stale value gets the
      listing flagged as untested.
- [ ] Shoot the screenshots and drop them in `.wordpress-org/`.
- [ ] Confirm the Fernfly terms and privacy URLs are the ones you want linked
      publicly: `https://fernfly.com/terms-of-service` and
      `https://fernfly.com/privacy-policy`. Both currently resolve.
- [ ] Decide the onboarding story. Right now a merchant has to create a Fernfly
      project, import `tools.json`, train, and deploy before Woobert does anything.
      That is a real cliff between installing and first value, and it is the most
      likely reason a good listing still gets uninstalled. A hosted, shared Woobert
      project that new users can point at with one click would remove it.
- [ ] `npm run build` and confirm `build/` is current.
- [ ] Re-run Plugin Check. Expect zero errors.

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
