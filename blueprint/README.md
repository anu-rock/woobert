# Woobert demo blueprint

[`blueprint.json`](blueprint.json) is a [WordPress Playground](https://wordpress.github.io/wordpress-playground/blueprints/)
blueprint that spins up a throwaway demo store in the browser, no install required.
One click gives you:

1. A fresh WordPress site (auto-logged-in as admin).
2. WooCommerce installed and activated.
3. Sample products, a variable product, customers, orders, reviews, and a coupon.
   The blueprint fetches [`scripts/seed-sample-data.php`](../scripts/seed-sample-data.php)
   into the site and runs it, so the demo store and local dev share one seeder.
4. Woobert installed and activated.
5. You land on the Woobert settings page (**WooCommerce -> Woobert**).

## Before you share it

- The plugin URL points at the `v0.1.0` GitHub release asset
  (`https://github.com/antelligent-org/woobert/releases/download/v0.1.0/woobert.zip`). That asset
  exists once the **Release plugin zip** workflow has been run for that tag. Bump the tag in
  the URL when you cut a new release.
- The seeder is fetched at launch from `scripts/seed-sample-data.php` on the `main` branch
  (via the `writeFile` step), so `features.networking` must stay `true`. If you pin the
  blueprint to a release tag, point that URL at the same tag.
- Set the inference endpoint + key. The `woobert_options` in the `setSiteOptions` step
  pre-fill the endpoint with the example value and leave `api_key` blank, so the command
  bar won't answer until you add a working project endpoint + `X-Api-Key`. Fill these on
  the settings page after launch, or bake real demo values into the blueprint.

## Launching

Open Playground with the blueprint URL-encoded (or hosted and referenced by URL):

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/antelligent-org/woobert/main/blueprint/blueprint.json
```

You can also paste the JSON into the [Playground builder](https://playground.wordpress.net/builder/builder.html).
Playground sites are ephemeral; everything resets when the tab closes.
