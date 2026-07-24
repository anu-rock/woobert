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
| `.wordpress-org/icon-128x128.png` | 128x128 | `assets-src/hoobert-owl.svg` | done |
| `.wordpress-org/icon-256x256.png` | 256x256 | same | done |
| `.wordpress-org/icon.svg` | vector | same | done |
| `.wordpress-org/banner-772x250.png` | 772x250 | `assets-src/banner.html` | done |
| `.wordpress-org/banner-1544x500.png` | 1544x500 | same | done |
| `.wordpress-org/screenshot-1..7.png` | any | shot by hand, see below | done |
| `.wordpress-org/blueprints/blueprint.json` | - | hand-written | done |

### About the owl

`assets-src/hoobert-owl.svg` is a vector redraw of the "funny owl" icon by
agustrisana on Flaticon, in the Fernfly palette. **If you have the original PNG,
drop it in as `assets-src/hoobert-owl.png`** and re-run the build; the script
prefers it for the icons automatically. The redraw exists so the pipeline works
without it, not because it is better.

The Flaticon free licence requires attribution wherever the icon appears. It is
in three places: the plugin settings screen (`Hoobert_Settings::render_credits`),
`readme.txt`, and the repository README.

### Screenshots

`screenshot-1.png` through `screenshot-7.png` are committed. Filenames must stay
lowercase and their numbering must match the captions in readme.txt's
`== Screenshots ==` section, in order: invoke, resolved call, confirm, result,
reports, settings, audit log.

They were shot against the seeded demo store, driving the real plugin. If you
re-shoot, these are the constraints worth preserving. Each one caused a bad frame
the first time round:

- **Only real states.** Never stage something the plugin cannot actually reach.
  Shots 2 and 3 are the same modal in two states, because for a tool flagged
  `x-woo.confirm` the preview *is* the confirm prompt; 2 opens the technical
  disclosure, 3 shows the prompt as it first appears.
- **Nothing destructive.** Capture the refund flow up to the confirm prompt and
  dismiss it. Only run reads.
- **The endpoint field is a published image.** Point `hoobert_options.endpoint`
  at a placeholder project before shooting the settings screen, then restore it.
  The API key field renders empty by design so the key is never in frame, but the
  project id would otherwise be. Back the real option up **outside** the wpcli
  container: it runs with `--rm`, so anything written to its `/tmp` is gone the
  moment it exits, and restoring from a file that is not there will null the
  option and take the API key with it.
- **Only claim what works.** Shot 5 covers sales over a period because that is
  the report that works end to end; top customers and top sellers do not.
- **Frame the audit log from the top.** The table is newest-first, so centring it
  lands arbitrarily in old history.
- **Mind the chrome.** `blogname` shows in the admin bar of every full-viewport
  shot, and core update nags and WooCommerce banners need hiding. Use the seeded
  store so every customer name in frame is fake. Anything visible is public
  forever.

**Animated GIFs are not in the documented spec.** The assets page lists
`screenshot-N.(png|jpg)`; GIF appears only in the icon formats. If you want motion
on the listing, two options that definitely work:

- **Live Preview.** `.wordpress-org/blueprints/blueprint.json` is committed, which
  turns on the "Live Preview" button on the plugin page: a real, throwaway store
  with WooCommerce, Hoobert, and sample data, running in the visitor's browser.
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

## Before you submit

- [ ] Finish the rename outside this repo. The code is done; these are not:
      rename the GitHub repository to `hoobert` (redirects cover the old URLs, but
      `releases/latest/download/hoobert.zip` only starts resolving after the next
      release), point `hoobert.fernfly.com` somewhere, and rename the Fernfly
      project label. The tool set itself needs no retraining: no tool name in
      `tools.json` carried the old name.
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
      Hoobert declares `WC requires at least: 8.0`; the REST v3 routes it calls are
      stable across WooCommerce 8.x to 10.x, but that pairing is the one to test.
- [ ] Set `Tested up to` to the WordPress version you last ran it against (7.0.2
      at time of writing). Reviewers do check this, and a stale value gets the
      listing flagged as untested.
- [ ] Shoot the screenshots and drop them in `.wordpress-org/`.
- [ ] Confirm the Fernfly terms and privacy URLs are the ones you want linked
      publicly: `https://fernfly.com/terms-of-service` and
      `https://fernfly.com/privacy-policy`. Both currently resolve.
- [ ] Decide the onboarding story. Right now a merchant has to create a Fernfly
      project, import `tools.json`, train, and deploy before Hoobert does anything.
      That is a real cliff between installing and first value, and it is the most
      likely reason a good listing still gets uninstalled. A hosted, shared Hoobert
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
