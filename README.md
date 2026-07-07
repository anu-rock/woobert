# Woobert

An agentic **command bar for WooCommerce merchants**, built into WordPress's native `⌘K` command palette. Press `⌘K` / `Ctrl-K` anywhere in wp-admin, pick **Ask Woobert** and type what you want in plain English, like *"refund order 1042"*, *"add a Large/Red variation to this product at 54.99"*, or *"who are my top customers this month"*, and Woobert turns it into the right WooCommerce REST API v3 action and runs it.

Woobert is powered by [Fern](https://fernfly.com), a family of small function-calling models. A tiny specialized model drives a complex admin surface faster than clicking through menus.

## How it works

```
Merchant opens the WP command palette (⌘K) and picks "Ask Woobert: …"
        │
        ▼
POST /woobert/v1/resolve   ── plugin proxy (PHP) ──▶  Woobert inference endpoint
        │                                             (Fernfly project, /api/p/{id}/infer)
        │  ◀───────────── {name, arguments} tool call ───────┘
        ▼
Preview + (confirm if destructive)
        │
        ▼
POST /woobert/v1/execute   ── plugin proxy ──▶  WooCommerce REST API v3 (in-process, admin session)
        │
        ▼
Result rendered in the Woobert modal
```

The browser never holds the inference key or WooCommerce credentials. The PHP proxy holds the inference key and executes REST calls in-process via `rest_do_request` under the current admin's capabilities. Destructive tools (refunds, deletes, status changes) are flagged `x-woo.confirm` and require a confirm click.

## Layout

| Path | What |
| --- | --- |
| `plugin/woobert/` | The WordPress plugin: command-palette front-end (`src/`) + PHP proxy/executor (`includes/`). |
| `plugin/woobert/tools.json` | The shipped tool set. 28 merchant tools mapped to REST API v3 endpoints, each with the `x-woo` dispatch block the executor reads. |
| `docker-compose.yml` | Pinned WordPress + MariaDB + WP-CLI for local dev. The wpcli service auto-provisions the stack. |
| `scripts/setup.sh` | Runs in the wpcli container: installs WP + WooCommerce, seeds sample data, mints REST API keys. Idempotent. |
| `scripts/seed-sample-data.php` | Products, a variable product, orders, customers, reviews, a coupon. |

## Quickstart

```bash
cp .env.example .env            # local stack config (DB, admin account, port)

# 1. Build the front-end bundle (needed before the plugin can activate)
cd plugin/woobert && npm install && npm run build && cd ../..

# 2. Bring up the stack. The wpcli service provisions WordPress + WooCommerce,
#    seeds sample data, and mints API keys automatically (idempotent, safe to re-run).
docker compose up -d

# 3. Watch provisioning and grab the printed API keys
docker compose logs -f wpcli

# 4. Open the store and press Cmd/Ctrl-K
open http://localhost:8080/wp-admin
```

If you bring the stack up before building the bundle, the plugin won't activate. Build it, then re-run provisioning with `docker compose run --rm wpcli`.

Set the inference endpoint URL and API key under **WooCommerce → Woobert**. The endpoint is the full inference URL, e.g. `https://fernfly.com/api/p/27/infer`. The WooCommerce REST API key/secret for external testing are printed once in the `wpcli` logs.

## The tool set

28 tools spanning the merchant journeys: orders (list/get/status/refund/notes), products (list/get/create/update/stock/delete), variations (list/create), reviews (list/moderate), customers (list/get/top-by-spend), coupons (create/list/update), categories & tags, and reports (sales, top sellers). Each entry is an OpenAI function-calling schema plus a private `x-woo` block telling the executor which REST call to dispatch. See the file header for the training/registration contract.
