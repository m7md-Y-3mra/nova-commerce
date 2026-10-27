# NOVA Store — WordPress + WooCommerce demo

A premium tech-accessories store built the WordPress way: a **custom classic theme** (`nova-store`) for presentation and a **custom plugin** (`nova-store-core`) for business logic, on top of stock **WordPress 7.1.3** and **WooCommerce 11.2.1**. It runs in Docker.

The storefront is **bilingual: English and Arabic (العربية)**, with a full right-to-left layout. Visitors switch language from the header; the admin stays in English.

- Storefront: <http://localhost:8090> (English) · <http://localhost:8090/?lang=ar> (Arabic)
- Admin: <http://localhost:8090/wp-admin>. The user is `admin`, and the password is the one you set in `.env`.
- Public API: <http://localhost:8090/wp-json/nova-store/v1/featured-products>

> This is a demo. Payments are **Cash on Delivery only** and no real money is processed. The code follows WordPress security practices, but it has **not** had a production security review.

---

## Contents

1. [Prerequisites](#prerequisites)
2. [Quick start](#quick-start)
3. [Daily commands](#daily-commands)
4. [Project structure](#project-structure)
5. [Architecture](#architecture)
6. [Custom hooks](#custom-hooks)
7. [REST endpoint](#rest-endpoint)
8. [Languages (English / العربية)](#languages-english--العربية)
9. [Security decisions](#security-decisions)
10. [Sample data](#sample-data)
11. [Testing checklist](#testing-checklist)
12. [Code quality](#code-quality)
13. [Deploying to alwaysdata](#deploying-to-alwaysdata)
14. [Troubleshooting](#troubleshooting)
15. [Interview walkthrough](#interview-walkthrough)
16. [Known limitations](#known-limitations)
17. [Image credits](#image-credits)

---

## Prerequisites

| Tool | Version used | Needed for |
|---|---|---|
| Docker Engine + Compose v2 | 29.x / 5.x | Running WordPress, MariaDB and WP-CLI |
| Free port 8090 | — | The storefront (change `WP_PORT` in `.env` if it is taken) |
| PHP 8.1+ and Composer *(optional)* | 8.5 / 2.x | Local `php -l` and PHPCS only; the site runs PHP 8.3 inside Docker |

Nothing else needs to be installed on the host: WP-CLI runs from the official `wordpress:cli` image.

## Quick start

```bash
cp .env.example .env
```

Edit `.env` and replace every `change-me-*` value with your own passwords. Then run:

```bash
./bin/setup.sh
```

The script is idempotent, so you can re-run it safely. It does the following:

1. Starts MariaDB and WordPress (`docker compose up -d`).
2. Installs WordPress core and sets pretty permalinks (`/%postname%/`, which `/wp-json/` needs).
3. Installs **WooCommerce 11.2.1** from wordpress.org (version pinned) and activates it. It also deletes the unused Hello Dolly and Akismet plugins.
4. Installs the official **Arabic language packs** for WordPress core and WooCommerce (`wp language core|plugin install … ar`). They are installed but not activated: the site language stays English, and the plugin switches the storefront per visitor.
5. Activates the `nova-store` theme and the `nova-store-core` plugin.
6. Runs `seed/seed.php`, which:
   - sets the store options and turns off WooCommerce's *coming soon* mode;
   - enables COD only;
   - sets up shipping: free over $99, otherwise $9.95;
   - creates the pages and switches cart/checkout to classic shortcodes;
   - adds 5 categories, 19 products with images, and the menus;
   - writes the Arabic copy of every product, category and page (from `seed/translations-ar.php`).

Then open <http://localhost:8090>.

## Daily commands

```bash
docker compose up -d
```

```bash
docker compose stop
```

```bash
docker compose logs -f wordpress
```

```bash
docker compose run --rm wpcli plugin list
```

```bash
docker compose run --rm wpcli eval-file /seed/seed.php --user=admin
```

The last one re-seeds the catalog and resets prices and stock.

To reset everything, run `docker compose down -v` and then `./bin/setup.sh`. **`down -v` permanently deletes the database and uploads volumes**, including any demo orders.

## Project structure

```
.
├── docker-compose.yml          # db (MariaDB 11.8) · wordpress (7.1.3, PHP 8.3) · wpcli (profile "cli")
├── .env.example                # placeholders only; real .env is git-ignored
├── bin/
│   ├── setup.sh                # idempotent bootstrap (WP-CLI)
│   ├── i18n.sh                 # rebuild .pot → Arabic .po → .mo for theme + plugin
│   └── po-fill.py              # merges Arabic strings into a .po (used by i18n.sh)
├── deploy/
│   ├── alwaysdata-push.sh      # local: upload code over SSH, run the installer
│   └── alwaysdata-install.sh   # server: WordPress + WooCommerce + theme/plugin + seed (re-runnable)
├── seed/
│   ├── seed.php                # store config + catalog, matched by SKU (re-runnable)
│   ├── translations-ar.php     # Arabic catalog content: products, categories, pages
│   ├── translations-strings-ar.py  # Arabic interface strings (source for the .po files)
│   └── images/                 # product photos (Unsplash License)
├── composer.json / phpcs.xml.dist   # WPCS 3 + PHPCompatibilityWP dev tooling
└── wp-content/
    ├── themes/nova-store/      # PRESENTATION
    │   ├── style.css           # theme header
    │   ├── functions.php       # supports, menus, asset enqueueing
    │   ├── inc/template-tags.php   # icons, cart link, category helpers, language switcher
    │   ├── inc/woocommerce.php     # WooCommerce layout via hooks (no template copies)
    │   ├── header.php · footer.php · front-page.php · page.php · index.php · 404.php
    │   ├── template-parts/home/    # hero, values, categories, products row, promo
    │   ├── languages/              # nova-store.pot, ar.po, ar.mo
    │   └── assets/{css,js,img}     # main.css uses CSS logical properties (works LTR and RTL)
    └── plugins/nova-store-core/    # BUSINESS LOGIC
        ├── nova-store-core.php     # bootstrap, HPOS compatibility, WooCommerce dependency check
        ├── includes/
        │   ├── class-languages.php           # language detection (?lang / cookie), locale filter, hreflang
        │   ├── class-content-translation.php # swaps in Arabic product/page/category content
        │   ├── class-translation-admin.php   # "Arabic translation" fields in wp-admin
        │   ├── class-settings.php          # WooCommerce → NOVA Store settings (Settings API)
        │   ├── class-delivery.php          # free-shipping threshold + dispatch-date rules
        │   ├── class-product-hooks.php     # product page / label hooks
        │   ├── class-checkout-hooks.php    # checkout validation + order meta
        │   └── class-rest-featured-products.php
        ├── languages/                  # nova-store-core.pot, nova-store-core-ar.po/.mo
        ├── templates/trust-block.php
        └── assets/css/nova-store-core.css
```

Only the theme and plugin folders are bind-mounted into the container. WordPress core and WooCommerce live in a Docker volume and are **never edited**.

## Architecture

```
Browser ──► Apache/PHP 8.3 (wordpress container)
              ├── WordPress core 7.1.3        (untouched, Docker volume)
              ├── WooCommerce 11.2.1          (untouched, installed by WP-CLI)
              ├── nova-store-core plugin      (rules, hooks, REST, settings, language switching)
              └── nova-store theme            (templates, CSS/JS, layout hooks, RTL styles)
                         │
                         ▼
                   MariaDB 11.8 (db container, not exposed to the host)
```

**Theme vs plugin split.** If you switch to another theme, the business behaviour stays: delivery block, stock labels, discount badges, checkout validation, order meta and the API. If you disable the plugin, the theme still renders. The theme reads the free-shipping threshold through `apply_filters( 'nova_store_free_shipping_threshold', null )` and never calls plugin functions directly.

**Native WooCommerce, not a rebuild.** All of these are WooCommerce features that the theme only styles:

- the shop, category archives, search, sorting (popularity, price, latest), pagination and stock management;
- the cart and its fragments;
- checkout, orders, emails and the admin screens.

The home page product rows use the native `[products]` shortcode, so visibility, pricing and add-to-cart behave exactly as they do in the shop.

**Hooks instead of template overrides.** The theme copies no files into `theme/woocommerce/`. Layout is changed with `remove_action` / `add_action` on WooCommerce's own hooks, which means a WooCommerce update cannot leave stale template copies behind.

**Classic cart and checkout.** WooCommerce 11 creates *block* cart and checkout pages by default. Block checkout runs through the Store API and does **not** fire classic PHP hooks such as `woocommerce_after_checkout_validation`. This project deliberately switches those pages to `[woocommerce_cart]` and `[woocommerce_checkout]`, so the classic hook pipeline used by the plugin applies. On block checkout, the equivalent hook would be `woocommerce_store_api_checkout_update_order_from_request`.

**Single source of truth for free shipping.** The plugin reads the threshold from the *Free shipping* method's minimum amount in the WooCommerce shipping zones. The announcement bar, values strip and product trust block always match what checkout actually charges. Change it under WooCommerce → Settings → Shipping and every label follows.

## Custom hooks

All of these are in `wp-content/plugins/nova-store-core/includes/`. Every hook was checked against the installed WooCommerce 11.2.1 source.

| # | Hook | Type / priority | Where it runs | What it does |
|---|---|---|---|---|
| 1 | `woocommerce_single_product_summary` | action, **35** | Single product page, summary column. It sits between add-to-cart (30) and SKU/category meta (40). | Renders the **trust block**: "Ships by *Wed, 28 Oct*" (counted in business days), "Free shipping on orders over $99", returns window and warranty. Each item can be configured. Markup lives in `templates/trust-block.php`. |
| 2 | `woocommerce_get_availability_text` | filter, `($availability, $product)` | Wherever WooCommerce prints stock HTML: the product page, plus shop grids via `wc_get_stock_html()` in the theme | Returns **"Only 3 left in stock"** at or below the threshold, **"Sold out — restocking soon"** when out of stock, and **"In stock"** for unmanaged stock. (WooCommerce prints nothing for unmanaged stock.) |
| 3 | `woocommerce_get_availability_class` | filter | Same places as #2 | Adds a `low-stock` CSS class so the theme can colour the label amber. |
| 4 | `woocommerce_sale_flash` | filter, `($html, $post, $product)` | Sale badge in loops and on the product page | Replaces "Sale!" with the real discount, e.g. **"−12%"**. For variable products it shows the largest variation discount. |
| 5 | `woocommerce_after_checkout_validation` | action, `($data, $errors)` | Server side, after WooCommerce validates checkout fields | Requires the billing phone to have **7–15 digits**. (WooCommerce only checks which characters are used, so "+++" passes.) Adding to `$errors` blocks the order and highlights the field. |
| 6 | `woocommerce_checkout_create_order` | action, `($order, $data)` | While the order object is built, before WooCommerce calls `save()` | Stores `_nova_estimated_dispatch` and `_nova_sales_channel` through `$order->update_meta_data()`, which is HPOS-safe. Totals, payment and stock are untouched. |
| 7 | `woocommerce_order_details_after_order_table` | action | Thank-you page and My Account → order | Shows the customer "Estimated dispatch: …". |
| 8 | `woocommerce_admin_order_data_after_shipping_address` | action | Admin order edit screen | Shows the dispatch date and sales channel to staff. |
| 9 | `nova_store_free_shipping_threshold` | custom filter | Theme header, values strip | Decouples the theme from the plugin. |
| 10 | `locale` | filter (core) | Every storefront request, registered before WordPress loads translations | Returns `ar` or `en_US` for the visitor's language, so core, WooCommerce, the theme and the plugin all load matching translations and WordPress turns on RTL. Admin screens are skipped. |
| 11 | `nova_store_languages` | custom filter | Theme header and mobile menu | Gives the theme the switcher data (name, URL, current) without calling plugin code. |
| 12 | `woocommerce_cart_fragment_name` | filter | Cart fragments (AJAX header cart) | Adds the language to the fragment cache key, so the header never shows the other language's cart text. |

The content filters used for Arabic (`the_title`, `the_content`, `woocommerce_product_get_name`, `get_product_cat`, …) are listed under [Languages](#languages-english--العربية).

The plugin also uses `rest_api_init`, `before_woocommerce_init` (HPOS compatibility declaration), `admin_menu`, `admin_init`, `admin_post_*`, and the `woocommerce_*product*` save/stock actions for cache invalidation.

## REST endpoint

```
GET /wp-json/nova-store/v1/featured-products
```

| Param | Type | Default | Rules |
|---|---|---|---|
| `per_page` | integer | 4 | 1–12. Anything else → **400** |
| `category` | string | — | Must be an existing `product_cat` slug, else **400** |
| `lang` | string | visitor's saved choice, then `en` | `en` or `ar`. Anything else → **400** |

**Implementation**

- Products are fetched with `wc_get_products()` using `status=publish`, `featured=true`, `visibility=catalog` and sort order. Password-protected products are skipped.
- Each item is built from an explicit **field allow-list**, so nothing internal can leak.
- Argument validation is declared in the route schema and enforced by core's `rest_parse_request_arg()`.
- `permission_callback => '__return_true'` is deliberate and commented: the route returns only what an anonymous shopper already sees on `/shop/`.
- Only `GET` is registered. Any other method → **404 `rest_no_route`**.
- Responses are cached in transients for 10 minutes and sent with `Cache-Control: public, max-age=300` and `X-WP-Total`.
- The cache is invalidated when a product is created, updated, deleted, or changes stock or visibility. Admins can also clear it from **WooCommerce → NOVA Store**.
- The cache key includes the locale, so English and Arabic responses are cached separately. Each response has a `language` field.

**Try it**

```bash
curl -s "http://localhost:8090/wp-json/nova-store/v1/featured-products?per_page=2" | python3 -m json.tool
```

```json
{
    "count": 2,
    "currency": "USD",
    "products": [
        {
            "id": 16,
            "name": "Aura ANC Wireless Headphones",
            "price": "219.00",
            "regular_price": "249.00",
            "on_sale": true,
            "price_formatted": "$219.00",
            "in_stock": true,
            "permalink": "http://localhost:8090/product/aura-anc-wireless-headphones/",
            "image": {
                "src": "http://localhost:8090/wp-content/uploads/2026/10/aura-anc-headphones-900x900.jpg",
                "alt": "Aura ANC Wireless Headphones"
            }
        },
        { "...": "..." }
    ]
}
```

Error cases:

```bash
curl -s -i "http://localhost:8090/wp-json/nova-store/v1/featured-products?per_page=999" | head -1
```

That returns `HTTP/1.1 400 Bad Request`. Other examples:

- `?per_page=abc` → 400 (`rest_invalid_param`).
- `?category=nope` → 400.
- `?category=audio` → featured audio products only.
- `?lang=ar` → product names in Arabic, `"language": "ar"`.
- `?lang=fr` → 400.

```bash
curl -s "http://localhost:8090/wp-json/nova-store/v1/featured-products?per_page=1&lang=ar" | python3 -m json.tool
```

## Languages (English / العربية)

The storefront runs in English or Arabic. Arabic uses a full right-to-left layout and an Arabic font. The admin is not affected.

### How the language is chosen

For each storefront request, `Languages::current()` in `class-languages.php` picks the language in this order:

1. `?lang=en` or `?lang=ar` in the URL. Links can be shared, and this also works on the REST API.
2. Otherwise, the `nova_lang` cookie from an earlier choice. It is set when a visitor uses `?lang=`, and it lasts 1 year (HttpOnly, SameSite=Lax, Secure on HTTPS).
3. Otherwise, English.

Unknown values are ignored. The result goes to WordPress through the core `locale` filter. That filter is registered when the plugin file loads, before WordPress loads its translations, so one switch covers everything:

| Text | Where the Arabic comes from |
|---|---|
| WordPress core and WooCommerce screens (cart, checkout, account, notices, e-mails) | Official language packs from translate.wordpress.org, installed by `setup.sh` |
| Theme strings (header, footer, home page sections) | `wp-content/themes/nova-store/languages/ar.mo` |
| Plugin strings (trust block, stock labels, checkout error, order meta, API) | `wp-content/plugins/nova-store-core/languages/nova-store-core-ar.mo` |
| Product, category and page content | Arabic copy saved in meta, swapped in by `Content_Translation` |
| Store settings (shipping method names, COD texts, tagline) | Listed as gettext strings in `Content_Translation::translate_store_string()`, so the Arabic lives in the plugin `.po` |

`is_admin()` requests always keep the site or user locale, so staff see wp-admin in English even with the Arabic cookie.

### Switcher and SEO

- The theme prints the switcher with `nova_store_language_switcher()` in the header, and inside the mobile menu on small screens. It gets its data from the `nova_store_languages` filter.
- Switcher URLs are built from the configured `home_url()` plus the request path, never from the `Host` header.
- On public pages the plugin prints `<link rel="alternate" hreflang="en|ar|x-default">`. Cart, checkout, account and 404 pages are skipped because they are private or not indexable.

### Translated content

`Content_Translation` only registers when the request is Arabic. For each item it uses the Arabic value when one exists, and falls back to English when it is empty.

| Data | Stored in | Filters |
|---|---|---|
| Product / page title | post meta `_nova_ar_title` | `the_title`, `single_post_title` (browser tab), `woocommerce_product_get_name` (cart, checkout, orders, API), `nav_menu_item_title` |
| Product short description | `_nova_ar_excerpt` | `woocommerce_short_description`, `woocommerce_product_get_short_description` |
| Product description / page content | `_nova_ar_content` | `the_content` (priority 1, so blocks and shortcodes still run), `woocommerce_product_get_description` |
| "Additional information" table | `_nova_ar_attributes` (English label → Arabic label + value) | `woocommerce_display_product_attributes` |
| Category name and description | term meta `_nova_ar_name`, `_nova_ar_description` | `get_product_cat`, `nav_menu_item_title` |
| Shipping labels, COD texts, tagline | gettext | `woocommerce_shipping_rate_label`, `option_woocommerce_cod_settings`, `option_blogdescription` |

**Editing Arabic content in the admin.**

- Products and pages have an **Arabic translation (العربية)** box under the editor, with title, short description (products only) and description/content fields.
- **Products → Categories** has *Arabic name* and *Arabic description* fields.
- Leave a field empty to show the English version.
- Saving a product also clears the REST cache.

### RTL design

- `main.css` uses CSS logical properties (`margin-inline-start`, `padding-inline`, `inset-inline-end`, `text-align: start`, …), so one stylesheet works both ways. WordPress adds `dir="rtl"` to `<html>` automatically.
- On Arabic pages the theme adds **IBM Plex Sans Arabic** from Google Fonts. Latin text keeps Inter/Manrope.
- Arrow icons are mirrored. Prices, SKUs, phone and e-mail inputs stay left-to-right, so numbers read correctly.
- Uppercase and letter-spacing are turned off for Arabic text, because Arabic has no capitals and spacing breaks the joined letters.

### Updating translations

Interface strings live in `seed/translations-strings-ar.py`: the `THEME` and `PLUGIN` dictionaries map English → Arabic. Plural strings use a `(singular, plural)` key and a list of the 6 Arabic plural forms. After you add or change a translatable string in PHP:

```bash
./bin/i18n.sh
```

The script:

1. extracts the strings to `.pot` files with `wp i18n make-pot` (inside Docker);
2. fills the Arabic `.po` files with `bin/po-fill.py`;
3. compiles the `.mo` files with `msgfmt -c`, which checks placeholders and plural forms and prints the translated/untranslated counts. The untranslated plugin strings are admin-only, on purpose.

It needs the stack running, plus `python3` and GNU `gettext` on the host. Catalog content is edited in `seed/translations-ar.php` (then re-seed) or directly in the admin.

To add another language: add it to `Languages::available()`, install its language packs in `setup.sh`, add a strings file and `.po`/`.mo` pair, and add meta fields for its content.

## Security decisions

| Concern | What the code does |
|---|---|
| Secrets | `.env` is git-ignored; `.env.example` has placeholders only. MariaDB is not published to the host, and WordPress binds to `127.0.0.1` only. |
| Core / plugin integrity | WordPress and WooCommerce are installed by WP-CLI into a volume and never modified. Only the theme and plugin are mounted. |
| Output escaping | `esc_html`, `esc_attr`, `esc_url` everywhere. SVG icons are static strings defined in code. WooCommerce/shortcode output is echoed as-is, and that is annotated. |
| Input handling | Settings go through `register_setting` with a `sanitize_callback`, which keeps only known keys and clamps integers. Checkout data arrives already sanitized by WooCommerce. REST args are schema-validated and slugs pass through `sanitize_title`. |
| Nonces + capabilities | The settings form uses `settings_fields()` (nonce) and `options.php` verifies it. The capability is mapped to `manage_woocommerce` via `option_page_capability_*`. The cache-flush action uses `wp_nonce_field` + `check_admin_referer` + `current_user_can`. Tested: a bad nonce → 403, and an anonymous request is refused. |
| Database | Only WordPress/WooCommerce APIs: `wc_get_products`, CRUD objects, options and transients. No raw SQL anywhere. |
| Orders | Meta is written via `WC_Order::update_meta_data()`, and HPOS compatibility is declared. Nothing touches totals, payment or stock. |
| Public API | GET only, allow-listed fields, bounded `per_page`, `lang` limited to an enum, no customer/order/config data. |
| Language switch | `?lang` and the `nova_lang` cookie are checked against the list of supported languages (`sanitize_key` + allow-list), so nothing else reaches the `locale` filter. It is a display preference with no side effects, so it has no nonce (like a currency switcher). The cookie is HttpOnly and SameSite=Lax. Switcher links use `home_url()`, not the `Host` header. |
| Arabic content fields | Saved only with a valid nonce and `current_user_can( 'edit_post' / 'edit_term' )`. Titles go through `sanitize_text_field`, content through `wp_kses_post`; output is escaped again. Tested: a bad nonce left the data unchanged, and `<script>` was stripped on save. |
| Payments | COD only. No card fields and no gateway credentials. |
| Dependencies | WordPress 7.1.3, WooCommerce 11.2.1 (latest at build time), PHP 8.3, MariaDB 11.8. No other plugins are installed. |

This project has **not** been through a professional security audit. Before going live you would still need, at minimum:

- HTTPS;
- hardened `wp-config` (`DISALLOW_FILE_EDIT`, salts);
- rate limiting on login and REST;
- a real mail server;
- backups;
- a review of third-party code.

## Sample data

`seed/seed.php` creates the following. Every row is matched by SKU, so re-running the seeder updates the catalog instead of duplicating it.

| Category | Products |
|---|---|
| Audio (6) | Aura ANC Wireless Headphones *(featured, sale)* · Studio Pro Over-Ear *(featured)* · Reference Wired *(**sold out**)* · Pulse Earbuds *(featured, sale)* · Heritage Leather · Mono Bookshelf Speakers |
| Keyboards (4) | Tactile 75 *(featured)* · Chroma 65 *(**low stock: 3**)* · Slim Wireless *(sale)* · Essential Wired |
| Mice (3) | Glide Wireless · Phantom Ultralight *(featured)* · Vector RGB *(sale, **low stock: 4**)* |
| Workspace (3) | Aluminium Laptop Stand *(featured)* · Arc LED Desk Lamp · Oak Monitor Riser |
| Charging & Hubs (3) | Dock Pro USB-C Docking Station *(featured, **low stock: 2**)* · 65W GaN Charger · MagDock Wireless Charger *(sale)* |

Each product also gets:

- a SKU, a short description in bullets, a long description and an "Additional information" attributes table;
- a `total_sales` value, so the popularity sort is meaningful;
- an Arabic title, short description, description and attributes table.

Categories, the cart, checkout, my-account and *Shipping & Returns* pages also get Arabic versions.

To add products by hand, go to **Products → Add New** in the admin. Set the price, then on the *Inventory* tab enable "Manage stock" and enter a quantity. Featured status is the ★ in the product list.

## Testing checklist

These are the checks run for this build. Re-run them after any change.

**Catalog**
- [ ] Home shows the hero, values strip, 5 category tiles, *Featured gear* (4), the promo band, and *On sale now*.
- [ ] `/shop/` shows 8 products per page with pagination (page 2 exists), category pills, search and sorting. *Sort by price* re-orders the grid.
- [ ] `/?s=keyboard&post_type=product` returns the 4 keyboards plus the Oak Monitor Riser, whose description mentions keyboards.
- [ ] Product page: gallery zoom and lightbox, price, stock label and trust block are present. The tabs show the description and attributes.
- [ ] *Reference Wired Headphones* shows "Sold out — restocking soon" and has no add-to-cart button.
- [ ] *Dock Pro* shows "Only 2 left in stock" in amber.

**Cart**
- [ ] Adding to cart from the grid uses AJAX: the button spins, shows ✓, and the header count updates without a reload.
- [ ] Changing the quantity and clicking *Update cart* recalculates the line subtotal and total.
- [ ] Removing a line shows the "removed. Undo?" notice.
- [ ] Below $99 the Standard $9.95 rate applies; at $99 or more Free shipping appears.
- [ ] An empty cart shows the icon, message, *Return to shop* and "Popular right now".

**Checkout and orders**
- [ ] The only payment method offered is Cash on delivery.
- [ ] A phone of `12-34` blocks the order with "Billing phone must contain between 7 and 15 digits." (server side).
- [ ] A valid phone takes you to *Order received*, which shows "Estimated dispatch: …".
- [ ] In the admin, **WooCommerce → Orders** shows the order as *Processing*, with "Estimated dispatch (NOVA)" and "Channel: web-storefront" under the shipping address. The product's stock went down.

**API**
- [ ] `featured-products` returns 200 with `count` / `products`.
- [ ] `per_page=999`, `per_page=abc` and `category=nope` each return 400.
- [ ] A `POST` returns 404.

**Languages**
- [ ] The header switcher shows *العربية*. Clicking it reloads the same page in Arabic, right-to-left, with the Arabic font.
- [ ] Arabic home, shop, category and product pages show Arabic names, descriptions, attributes, breadcrumbs, trust block and stock labels ("بقيت قطعتان فقط" on *Dock Pro*).
- [ ] The cart, shipping labels, checkout, COD texts and the phone error are in Arabic. An Arabic order shows the dispatch date in Arabic on *Order received*.
- [ ] The choice is remembered on the next page without `?lang`. Switching back to English works the same way.
- [ ] At 375 px the switcher is inside the mobile menu, and the RTL page never scrolls sideways.
- [ ] wp-admin stays English with the Arabic cookie, and the *Arabic translation* box saves.
- [ ] API: `lang=ar` returns Arabic names, `lang=fr` returns 400.

**Admin settings**
- [ ] **WooCommerce → NOVA Store** saves; out-of-range values are clamped.
- [ ] *Clear cache now* shows the success notice.

**Responsive**
- [ ] At 375 px: the hamburger opens a full-screen menu with search, `Esc` closes it and returns focus to the toggle, and the page never scrolls horizontally.
- [ ] Category tiles swipe sideways.
- [ ] Product grids are 2-up.
- [ ] Checkout is a single column.

You can check the order from the command line:

```bash
docker compose run --rm wpcli wc shop_order list --user=admin --fields=id,status,total,payment_method
```

## Code quality

```bash
composer install
```

```bash
composer lint
```

```bash
vendor/bin/phpcs
```

`composer lint` runs `php -l` on every custom PHP file. PHPCS checks against the WordPress Coding Standards 3 and PHPCompatibilityWP (PHP 8.1+). Both currently pass with **0 errors and 0 warnings**.

There are no automated PHPUnit or E2E tests. Verification was done manually with the checklist above, in a browser and with `curl`/WP-CLI.

## Deploying to alwaysdata

The store runs on the alwaysdata free plan (1 GB disk, PHP 8.4, SSH). alwaysdata has no Docker, so two scripts replace `setup.sh` there:

| Script | Runs on | What it does |
|---|---|---|
| `deploy/alwaysdata-push.sh` | your machine | Uploads the theme, plugin and `seed/` to `~/nova-src` over SSH, then runs the installer. |
| `deploy/alwaysdata-install.sh` | the server | Installs WordPress 7.1.3, WooCommerce 11.2.1 and the Arabic language packs into `~/www`, copies the theme and plugin, activates them and seeds the catalog. Safe to run again. |

The live site is a **fresh install plus the seeder**. The local database is not copied, so local test orders and customer data stay on your machine.

**One-time setup in the alwaysdata admin panel**

1. *Databases → MySQL*: create a database named `<account>_nova`, and give the `<account>` user all rights on it.
2. *Web → Sites*:
   - **Empty account:** the default site at `https://<account>.alwaysdata.net`, with root `www/` and PHP 8.4, is fine.
   - **Account that already hosts a site in `www/` (free plan):** the free plan allows one account per person and only the `<account>.alwaysdata.net` address, so a second account or a `nova.<account>.alwaysdata.net` address is not an option. Do not add a new site. Install into a sub-folder of the existing site instead: `DOCROOT=www/nova SITE_URL=https://<account>.alwaysdata.net/nova`. The installer writes `www/nova/.htaccess` with `RewriteBase /nova/`; Apache does not inherit the parent site's rewrite rules, so `/nova/...` goes to NOVA and every other URL still goes to the parent site.
   - **Paid plan:** a separate site with its own domain and root directory `nova/` also works (`DOCROOT=nova SITE_URL=https://shop.example.com`).
3. *Remote access → SSH*: enable password login, or add your SSH key.

The installer refuses to run if its root directory already holds another theme, or a `wp-config.php` for a different database, so it cannot overwrite an existing site.

**One-time password files on the server** (mode 600, never in the repo or on a command line):

```bash
printf '%s' 'YOUR_DB_PASSWORD' | ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net 'umask 077; cat > ~/.dbpass'
```

```bash
printf '%s' 'YOUR_ADMIN_PASSWORD' | ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net 'umask 077; cat > ~/.adminpass'
```

Start these lines with a space so most shells keep them out of history. `~/.adminpass` is only read on the first install and can be deleted afterwards. If another site on the account already created `~/.dbpass` for the same database user, it can be reused.

**Deploy (first time and every update):**

```bash
ALWAYSDATA_ACCOUNT=ACCOUNT ADMIN_EMAIL=you@example.com ./deploy/alwaysdata-push.sh
```

Optional variables: `SITE_URL` (the site's address), `DOCROOT` (root directory under your home, default `www`; `www/nova` for a sub-folder), `DB_NAME` (if not `<account>_nova`), `ADMIN_USER` (default `admin`). Later pushes only replace the theme and plugin code. The catalog is seeded on the first run only; add `RESEED=1` to reset products, prices and stock to the demo values.

**What the installer sets up for alwaysdata**

- **HTTPS behind the proxy.** TLS ends at alwaysdata's proxy, so `wp-config.php` sets `$_SERVER['HTTPS']` when `X-Forwarded-Proto` is `https`. Without this, secure cookies and `https` URLs break. `.htaccess` redirects HTTP to HTTPS using the same header.
- **`.htaccess` is written by the script.** `wp rewrite flush --hard` cannot write it there, and without it every product URL returns 404.
- **No customer e-mails.** alwaysdata really sends mail, and shoppers on a public demo can type someone else's address. The installer turns off the customer order and account e-mails; the admin "new order" e-mail and password resets stay on.
- **Memory.** WP-CLI runs with `memory_limit=512M` because the seeder generates thumbnails for 19 images.
- **Hardening.** `WP_DEBUG` off, `DISALLOW_FILE_EDIT` on, `wp-config.php` mode 600. The inactive `twentytwentyfive` theme stays installed so wp-admin still works if the custom theme ever fatals.
- **Disk.** The install uses about 200 MB of the 1 GB.

This is a demo deployment, not a security-reviewed production setup.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `port is already allocated` | Change `WP_PORT` and `WP_URL` in `.env`, then run `docker compose up -d` again. |
| Store shows "Coming soon" to logged-out visitors | `docker compose run --rm wpcli option update woocommerce_coming_soon no` |
| `/wp-json/...` or product URLs return 404 | `docker compose run --rm wpcli rewrite structure '/%postname%/' --hard` |
| Header cart count does not update | Hard-refresh the page. The theme enqueues `wc-cart-fragments`, so check the browser console for JS errors. |
| `Error establishing a database connection` on first boot | MariaDB is still initialising. Wait about 10 s; `docker compose ps` should show `db` as *healthy*. |
| Re-running the seeder reset my stock or prices | That is intentional: the seeder restores the canonical demo catalog. |
| WooCommerce e-mails are not delivered | The stack has no mail server. Order e-mails fail silently and orders are still created. Add Mailpit/SMTP if you need them. |
| `Warning: Regenerating a .htaccess file requires special configuration` during setup | Harmless. The WordPress image already ships the standard rewrite rules. |
| Arabic page still shows English text | Check the field is filled in the *Arabic translation* box (empty fields fall back to English). For interface strings, run `./bin/i18n.sh` and check the "untranslated" count it prints. |
| Language keeps switching back | The choice is stored in the `nova_lang` cookie. Use the switcher or `?lang=en` / `?lang=ar`; clearing cookies resets to English. |
| Want to start over | `docker compose down -v && ./bin/setup.sh`. **This deletes the database and uploads.** |

## Interview walkthrough

This is roughly a 5-minute tour.

1. **Setup (30 s).**
   - Docker Compose runs MariaDB, WordPress 7.1.3 on PHP 8.3, and a WP-CLI container.
   - `bin/setup.sh` is idempotent: it installs the *pinned* WooCommerce release, activates my theme and plugin, and seeds the data.
   - Core and WooCommerce sit in a volume. Only my code is mounted, so I *can't* accidentally edit core.

2. **Theme vs plugin (1 min).**
   - The theme is presentation only: templates, CSS/JS, and layout moved around with WooCommerce hooks rather than copied template overrides.
   - The plugin holds the rules. Swap the theme and the delivery promise, stock labels, discount badges, checkout validation and API all keep working.
   - They talk through one custom filter (`nova_store_free_shipping_threshold`), so neither hard-depends on the other.

3. **Hooks (1.5 min).** Open a product page.
   - The trust block comes from the `woocommerce_single_product_summary` action at priority 35. I chose 35 because add-to-cart is 30 and the meta block is 40.
   - "Only 2 left" comes from the `woocommerce_get_availability_text` filter, which returns a modified string, plus a class filter for styling.
   - "−12%" comes from `woocommerce_sale_flash`.
   - The free-shipping amount is read from the shipping zone itself, so the label can't disagree with checkout.

4. **Checkout (1 min).**
   - Enter the phone `12-34`. `woocommerce_after_checkout_validation` adds to the `WP_Error`, which blocks the order server side.
   - Fix it and place the COD order. `woocommerce_checkout_create_order` writes HPOS-safe order meta. The dispatch date then shows on the thank-you page and in the admin order screen.
   - I used classic shortcode checkout on purpose. Block checkout goes through the Store API and skips these hooks; I can name the Store API equivalent.

5. **REST API (45 s).**
   - `curl` the endpoint. It is built from `wc_get_products()` with a field allow-list.
   - Args are validated by the schema (`per_page` 1–12, category must exist), so bad input gets a 400.
   - The public `permission_callback` is deliberate and documented. The route caches in transients with a versioned key, so there is no wildcard delete or SQL.

6. **Security (45 s).**
   - Escaping is done on output.
   - The settings page uses the Settings API, with a `manage_woocommerce` capability, a sanitize callback, and clamping.
   - The cache-flush button has an explicit nonce plus a capability check. I tested that a bad nonce gets a 403.
   - There is no raw SQL, no secrets in git, the DB is not exposed, and payment is COD only.
   - It is **not** production-audited, and I can list what's missing (see above).

7. **Languages (45 s).** Click *العربية* in the header.
   - One `locale` filter, registered before WordPress loads translations, switches core, WooCommerce, theme and plugin. The page turns RTL by itself.
   - Interface strings come from `.po`/`.mo` files; catalog content comes from meta with English fallback; the CSS uses logical properties, so there is no separate RTL stylesheet.
   - Show the *Arabic translation* box on a product, and `?lang=ar` on the API.

8. **Admin (30 s).** Show that the store is run natively:
   - Products → edit a price or stock quantity, then show the storefront and API update (the cache is invalidated by hook).
   - WooCommerce → Orders.
   - WooCommerce → NOVA Store settings.

## Known limitations

- No automated test suite (PHPUnit/Playwright). Verification is the manual checklist above.
- No mail server, so customer and admin e-mails are not delivered.
- Products are simple products. Variable products (colour/size) are supported by the code (for example the sale badge handles them), but none are seeded.
- No product reviews or ratings are seeded.
- Fonts load from Google Fonts. Self-host them for GDPR-strict deployments.
- Languages use `?lang` plus a cookie, not separate URLs such as `/ar/`. Search engines only see the Arabic version through the `hreflang` links with `?lang=ar`. A plugin such as Polylang or WPML would be the next step for SEO-heavy sites.
- Order line items keep the product name in the language the customer ordered in. Staff see that name in the admin.
- The Arabic "Additional information" table is filled by the seeder only; there is no admin field for it yet.
- Admin-only plugin strings (settings page, meta boxes) are not translated, because the admin stays English.
- WooCommerce e-mails would use the language of the request that sends them, but no mail server is configured, so this is untested.
- HPOS compatibility is declared and the code only uses CRUD APIs. This install runs on legacy post storage (HPOS was off after a WP-CLI install), and HPOS mode was not separately exercised.

## Image credits

Product, hero and promo photos are from [Unsplash](https://unsplash.com) and used under the [Unsplash License](https://unsplash.com/license) (free for commercial use; attribution appreciated). The photo ID is `https://unsplash.com/photos/<id>` / `images.unsplash.com/photo-<id>`. Product names and specs are fictional.

| File | Unsplash photo |
|---|---|
| aura-anc-headphones.jpg | `1546435770-a3e426bf472b` |
| studio-pro-headphones.jpg | `1505740420928-5e560c06d30e` |
| reference-wired-headphones.jpg | `1583394838336-acd977736f90` |
| pulse-earbuds.jpg | `1606220945770-b5b6c2c55bf1` |
| retro-classic-headphones.jpg | `1484704849700-f032a568e944` |
| bookshelf-speakers.jpg | `1545454675-3531b543be5d` |
| tactile-75-keyboard.jpg | `1618384887929-16ec33fab9ef` |
| chroma-mechanical-keyboard.jpg | `1595225476474-87563907a212` |
| slim-wireless-keyboard.jpg | `1587829741301-dc798b83add3` |
| minimal-wired-keyboard.jpg | `1541140532154-b024d705b90a` |
| glide-wireless-mouse.jpg | `1527864550417-7fd91fc51a46` |
| phantom-gaming-mouse.jpg | `1615663245857-ac93bb7c39e7` |
| rgb-gaming-mouse.jpg | `1629429408209-1f912961dbd8` |
| aluminium-laptop-stand.jpg | `1593642632559-0c6d3fc62b89` |
| arc-desk-lamp.jpg | `1507473885765-e6ed057f782c` |
| monitor-riser.jpg | `1616763355548-1b606f439f86` |
| usb-c-dock.jpg | `1588508065123-287b28e013da` |
| gan-charger.jpg | `1583863788434-e58a36330cf0` |
| wireless-charging-pad.jpg | `1615526675159-e248c3021d3f` |
| theme `hero.jpg` | `1593062096033-9a26b09da705` |
| theme `promo.jpg` | `1616763355548-1b606f439f86` |
