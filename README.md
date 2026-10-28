# NOVA Store

[![CI/CD](https://github.com/m7md-Y-3mra/nova-commerce/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/m7md-Y-3mra/nova-commerce/actions/workflows/ci-cd.yml)

**A bilingual (English / العربية) online store for premium tech accessories, built on WordPress and WooCommerce.**

NOVA Store is a complete storefront: catalog, cart, checkout, customer accounts, an admin for products and orders, and a public product API. It is built with a custom theme for the look and a custom plugin for the store rules. WordPress and WooCommerce themselves are left untouched, so they can be updated safely.

| | |
|---|---|
| **Live preview** | <https://techscope.alwaysdata.net/nova/> · [Arabic](https://techscope.alwaysdata.net/nova/?lang=ar) |
| **Stack** | WordPress 7.1.3 · WooCommerce 11.2.1 · PHP 8.3 (local) / 8.4 (hosting) · MariaDB 11.8 |
| **Custom code** | `nova-store` theme · `nova-store-core` plugin |
| **Local setup** | Docker, with one command (`./bin/setup.sh`) |
| **Deployment** | Automatic on every push to `main` (GitHub Actions → alwaysdata) |

---

## Contents

1. [Features](#features)
2. [Getting started](#getting-started)
3. [Running the store (admin guide)](#running-the-store-admin-guide)
4. [Customer accounts](#customer-accounts)
5. [Languages](#languages)
6. [How it is built](#how-it-is-built)
7. [Public API](#public-api)
8. [Deployment](#deployment)
9. [Security](#security)
10. [Quality and testing](#quality-and-testing)
11. [Before going live](#before-going-live)
12. [Troubleshooting](#troubleshooting)
13. [Credits](#credits)

---

## Features

**Shopping**
- Home page with a hero, category tiles, *Featured gear*, a promo band and *On sale now*.
- Shop with category filters, search, sorting (popularity, price, latest) and pagination.
- Product pages with a zoomable gallery, a sticky buy box, specs and a **trust block**: the dispatch date ("Ships by Wed, 28 Oct"), the free-shipping limit, the returns window and the warranty.
- Clear stock labels ("Only 2 left in stock", "Sold out — restocking soon") and real discount badges ("−12%").
- Cart updates without a page reload. Free shipping above $99, otherwise $9.95.

**Checkout and orders**
- Classic WooCommerce checkout with **Cash on Delivery**.
- The server checks the phone number (7–15 digits) so the courier can always call.
- Every order stores its promised dispatch date. Customers see it on the confirmation page; staff see it in the admin.

**Customer accounts**
- Separate **Log in** (`/login/`) and **Create account** (`/register/`) pages.
- Sign-up asks for a first and last name, an e-mail and a password, and logs the customer in straight away.
- Customers can also create an account during checkout.

**Two languages**
- English and Arabic, with a full right-to-left layout and an Arabic font.
- Shoppers switch from the header, and the choice is remembered.
- Products, categories and pages have an *Arabic translation* box in the admin.

**For staff and developers**
- Products, stock, orders and customers are managed in the standard WooCommerce admin.
- A **WooCommerce → NOVA Store** settings page holds the delivery and label rules.
- A public read-only JSON API for featured products.
- One-command local setup, automatic deploys and a post-deploy check of the live site.

---

## Getting started

### Requirements

| Tool | Needed for |
|---|---|
| Docker Engine + Compose v2 | Running WordPress, MariaDB and WP-CLI |
| Port 8090 free | The local site (change `WP_PORT` in `.env` if it is taken) |
| PHP 8.1+ and Composer *(optional)* | Running the code checks outside Docker |
| `python3` and GNU `gettext` *(optional)* | Rebuilding translation files |

### Install

```bash
cp .env.example .env
```

Open `.env` and replace every `change-me-*` value with your own passwords. Then run:

```bash
./bin/setup.sh
```

The script can be re-run safely. It starts the containers and installs WordPress and the pinned WooCommerce version. It also installs the Arabic language packs, activates the theme and plugin, and loads the sample catalog: 5 categories and 19 products, in English and Arabic.

| | URL |
|---|---|
| Store | <http://localhost:8090> · <http://localhost:8090/?lang=ar> |
| Admin | <http://localhost:8090/wp-admin> (user `admin`, password from `.env`) |
| Log in / Create account | <http://localhost:8090/login/> · <http://localhost:8090/register/> |
| API | <http://localhost:8090/wp-json/nova-store/v1/featured-products> |

### Everyday commands

Start the stack:

```bash
docker compose up -d
```

Stop it:

```bash
docker compose stop
```

Follow the WordPress logs:

```bash
docker compose logs -f wordpress
```

Run any WP-CLI command, for example:

```bash
docker compose run --rm wpcli plugin list
```

Reload the sample catalog (this **resets prices and stock**):

```bash
docker compose run --rm wpcli eval-file /seed/seed.php --user=admin
```

To start over completely, run `docker compose down -v`, then `./bin/setup.sh` again. **`down -v` permanently deletes the local database and uploads.**

---

## Running the store (admin guide)

Everything is managed from **wp-admin** with standard WooCommerce screens.

| Task | Where |
|---|---|
| Add or edit a product, price, sale price or images | **Products → Add New / All Products** |
| Track stock | Product → *Inventory* tab → *Manage stock* + quantity. "Only X left" appears automatically at or below the threshold. |
| Feature a product on the home page | Click the ★ in **Products → All Products** |
| Arabic text for a product or page | The **Arabic translation (العربية)** box under the editor. Leave a field empty to show the English text. |
| Arabic name for a category | **Products → Categories** → *Arabic name* / *Arabic description* |
| See and process orders | **WooCommerce → Orders**. Each order shows "Estimated dispatch (NOVA)". |
| See customers | **WooCommerce → Customers** or **Users** |
| Free-shipping limit and shipping prices | **WooCommerce → Settings → Shipping**. The header, home page and product pages follow it automatically. |
| Dispatch time, returns window, warranty, low-stock label | **WooCommerce → NOVA Store** |
| Clear the API cache | **WooCommerce → NOVA Store → Clear cache now** (it also clears itself when products change) |
| Turn customer registration on or off | **WooCommerce → Settings → Accounts & Privacy** |

---

## Customer accounts

| Page | What it does |
|---|---|
| `/login/` | Log-in form, with a link to *Create an account* and *Lost your password?* |
| `/register/` | First name, last name, e-mail and password. On success the customer is logged in and sees "Hello *First Last*". |
| `/my-account/` | Orders, addresses and account details. Visitors who are not logged in are sent to `/login/`. |
| Checkout | *Create an account?* creates the customer together with the order. |

- Customers choose their own password, and WooCommerce's strength meter shows on the form.
- Logged-in customers who open `/login/` or `/register/` go straight to My Account.
- Password reset uses WooCommerce's standard flow. After a reset, the customer lands on `/login/` with a confirmation message.

---

## Languages

The store runs in **English** or **Arabic**. The admin always stays in English.

**How the language is chosen**
1. `?lang=en` or `?lang=ar` in the URL. This also works on the API.
2. Otherwise, the visitor's earlier choice, saved in a cookie for one year.
3. Otherwise, English.

**Where the Arabic comes from**

| Text | Source |
|---|---|
| WordPress and WooCommerce screens (cart, checkout, account, notices) | Official Arabic language packs |
| Theme and plugin text | `languages/*.po` / `.mo` files in the theme and plugin |
| Products, categories and pages | The *Arabic translation* fields in the admin |
| Store settings (shipping names, payment texts, privacy notes, tagline) | A translation map in the plugin |

**Right-to-left layout.** One stylesheet serves both directions, because it uses CSS logical properties. Arabic pages load *IBM Plex Sans Arabic*, and arrows are mirrored. Prices, phone numbers and e-mail addresses stay left-to-right.

**Updating interface translations.** Add the English → Arabic pair to `seed/translations-strings-ar.py`, then run:

```bash
./bin/i18n.sh
```

This needs the stack running. It extracts the strings, fills the Arabic `.po` files and compiles the `.mo` files, then prints how many strings are still untranslated. The untranslated ones are admin-only strings, which is intentional.

---

## How it is built

```
Browser ──► Apache + PHP
              ├── WordPress core          (never edited)
              ├── WooCommerce             (never edited, version pinned)
              ├── nova-store-core plugin  business rules, hooks, API, settings, languages, account pages
              └── nova-store theme        templates, CSS/JS, layout, RTL
                         │
                         ▼
                      MariaDB
```

**Key decisions**

- **Theme for the look, plugin for the rules.** If the theme is replaced, these keep working:
  - delivery dates and stock labels;
  - discount badges and checkout validation;
  - order data, account pages and the API.

  The two only talk through filters (`nova_store_free_shipping_threshold`, `nova_store_languages`, `nova_store_account_url`). Neither breaks if the other is missing.
- **Hooks, not copied templates.** The theme does not copy WooCommerce template files; it changes the layout with WooCommerce's own hooks, so a WooCommerce update cannot leave outdated copies behind.
- **Classic cart and checkout.** The store uses the `[woocommerce_cart]` and `[woocommerce_checkout]` shortcodes, because the plugin's checkout rules use the classic PHP hooks. Block checkout would need the Store API equivalent, `woocommerce_store_api_checkout_update_order_from_request`.
- **One source for free shipping.** The limit is read from the WooCommerce shipping zone, so every label matches what checkout charges.

### Project structure

```
.
├── docker-compose.yml              # db (MariaDB) · wordpress · wpcli
├── .env.example                    # placeholders only; the real .env is git-ignored
├── .github/workflows/ci-cd.yml     # checks every push/PR; deploys main
├── bin/
│   ├── setup.sh                    # local install (re-runnable)
│   ├── i18n.sh                     # rebuild translation files
│   └── po-fill.py                  # used by i18n.sh
├── deploy/
│   ├── alwaysdata-push.sh          # uploads the code and runs the installer
│   └── alwaysdata-install.sh       # server-side install/update (re-runnable)
├── seed/
│   ├── seed.php                    # store settings + sample catalog (matched by SKU)
│   ├── account-pages.php           # creates /login/ and /register/ (re-runnable)
│   ├── translations-ar.php         # Arabic catalog content
│   ├── translations-strings-ar.py  # Arabic interface strings
│   └── images/                     # product photos
└── wp-content/
    ├── themes/nova-store/          # PRESENTATION
    │   ├── functions.php · header.php · footer.php · front-page.php · page.php · 404.php
    │   ├── inc/template-tags.php   # icons, cart link, language switcher
    │   ├── inc/woocommerce.php     # WooCommerce layout via hooks
    │   ├── template-parts/home/    # home page sections
    │   ├── languages/
    │   └── assets/{css,js,img}
    └── plugins/nova-store-core/    # BUSINESS LOGIC
        ├── nova-store-core.php     # bootstrap, HPOS compatibility, WooCommerce check
        ├── includes/
        │   ├── class-languages.php            # language detection, locale, hreflang
        │   ├── class-content-translation.php  # Arabic product/page/category content
        │   ├── class-translation-admin.php    # "Arabic translation" admin fields
        │   ├── class-settings.php             # WooCommerce → NOVA Store settings
        │   ├── class-delivery.php             # free-shipping + dispatch-date rules
        │   ├── class-product-hooks.php        # trust block, stock labels, sale badge
        │   ├── class-checkout-hooks.php       # phone check, order meta
        │   ├── class-account-hooks.php        # name fields on sign-up
        │   ├── class-account-pages.php        # /login/, /register/, redirects
        │   └── class-rest-featured-products.php
        ├── templates/              # trust-block, login-form, register-form
        ├── languages/
        └── assets/css/
```

<details>
<summary><strong>Custom hooks reference</strong> (for developers)</summary>

All in `wp-content/plugins/nova-store-core/includes/`. Each was checked against the WooCommerce 11.2.1 source.

| Hook | Type | What it does |
|---|---|---|
| `woocommerce_single_product_summary` | action, priority 35 | Trust block between add-to-cart (30) and product meta (40). Markup in `templates/trust-block.php`. |
| `woocommerce_get_availability_text` | filter | "Only X left in stock", "Sold out — restocking soon", "In stock". |
| `woocommerce_get_availability_class` | filter | Adds `low-stock` so the label turns amber. |
| `woocommerce_sale_flash` | filter | Real discount ("−12%"), including the largest one for variable products. |
| `woocommerce_after_checkout_validation` | action | Phone must have 7–15 digits; blocks the order on the server. |
| `woocommerce_checkout_create_order` | action | Saves `_nova_estimated_dispatch` and `_nova_sales_channel` (HPOS-safe CRUD). |
| `woocommerce_order_details_after_order_table` | action | Shows the dispatch date to the customer. |
| `woocommerce_admin_order_data_after_shipping_address` | action | Shows dispatch date and channel to staff. |
| `woocommerce_register_form_start` | action | First/last name fields on the register form. |
| `woocommerce_register_post` | action | Requires both names (max 60 characters); register page only, not checkout. |
| `woocommerce_created_customer` | action | Saves the names on the user and as billing name. |
| `[nova_login_form]`, `[nova_register_form]` | shortcodes | The `/login/` and `/register/` pages; render WooCommerce's own forms. |
| `template_redirect` | action | Logged out: My Account → `/login/`. Logged in: `/login/`, `/register/` → My Account. |
| `woocommerce_is_account_page` | filter | Treats `/login/` and `/register/` as account pages (no-cache, strength meter). |
| `locale` | filter | Picks English or Arabic per visitor, before translations load. |
| `woocommerce_cart_fragment_name` | filter | Keeps the header cart's cached text per language. |
| `nova_store_free_shipping_threshold` | custom filter | Free-shipping amount for the theme. |
| `nova_store_languages` | custom filter | Language switcher data for the theme. |
| `nova_store_account_url` | custom filter | Header account link: `/login/` or My Account. |

Arabic content uses `the_title`, `the_content`, `woocommerce_product_get_name`, `get_product_cat`, `nav_menu_item_title`, `woocommerce_get_privacy_policy_text` and related filters. They only run on Arabic requests and fall back to English when a field is empty.

</details>

---

## Public API

```
GET /wp-json/nova-store/v1/featured-products
```

| Parameter | Default | Rules |
|---|---|---|
| `per_page` | 4 | 1–12, otherwise **400** |
| `category` | — | An existing product category slug, otherwise **400** |
| `lang` | visitor's choice, then `en` | `en` or `ar`, otherwise **400** |

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
            "image": { "src": "…/aura-anc-headphones-900x900.jpg", "alt": "Aura ANC Wireless Headphones" }
        }
    ]
}
```

- **Read-only and public.** Only `GET` is registered, and it returns only what a shopper already sees on `/shop/`.
- **Fixed fields.** Each product is built from an explicit list of fields, so nothing internal can leak.
- **Cached.** Results are cached for 10 minutes, separately per language. The cache clears when a product, its stock or its visibility changes.

---

## Deployment

The live preview runs on **alwaysdata** (free plan, PHP 8.4, SSH, no Docker). It is a fresh install plus the sample catalog; local orders and customers are never uploaded.

### Automatic (GitHub Actions)

`.github/workflows/ci-cd.yml`:

| Trigger | Jobs |
|---|---|
| Every push and pull request | **lint**: PHP syntax, WordPress Coding Standards, shell script syntax |
| Push to `main` (or *Run workflow*) | **lint** → **deploy** → **smoke test** on the live URL |

The smoke test checks these pages on the live site:
- the home page and the Arabic right-to-left home page;
- the shop and a product page;
- `/login/` and `/register/`;
- the API.

It also fails if "demo" wording appears on the home page. Deploys run one at a time, because the free plan has 256 MB of RAM for the whole account.

**Work on a branch, open a pull request, merge to `main`:** the merge deploys automatically.

<details>
<summary><strong>One-time CI setup</strong></summary>

Create an SSH key that only CI uses:

```bash
ssh-keygen -t ed25519 -N '' -C 'github-actions nova-commerce deploy' -f ~/.ssh/nova_deploy
```

Allow it on the server. `restrict` blocks forwarding and terminals:

```bash
{ printf 'restrict '; cat ~/.ssh/nova_deploy.pub; } | ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net 'umask 077; mkdir -p ~/.ssh; cat >> ~/.ssh/authorized_keys'
```

Store the private key in GitHub:

```bash
gh secret set ALWAYSDATA_SSH_KEY < ~/.ssh/nova_deploy
```

Store the server's host key:

```bash
ssh-keyscan ssh-ACCOUNT.alwaysdata.net 2>/dev/null | gh secret set ALWAYSDATA_KNOWN_HOSTS
```

Set the account name:

```bash
gh variable set ALWAYSDATA_ACCOUNT -b ACCOUNT
```

Set the folder the site lives in:

```bash
gh variable set ALWAYSDATA_DOCROOT -b www/nova
```

Set the site URL:

```bash
gh variable set SITE_URL -b https://ACCOUNT.alwaysdata.net/nova
```

To revoke CI access, delete the line ending in `github-actions nova-commerce deploy` from `~/.ssh/authorized_keys` on the server.

</details>

<details>
<summary><strong>First install / manual deploy</strong></summary>

**In the alwaysdata panel**

1. *Databases → MySQL*: create `<account>_nova` and give the `<account>` user all rights on it.
2. *Web → Sites*:
   - **Empty account:** use the default site (`www/`, PHP 8.4).
   - **Account that already hosts a site:** install into a sub-folder, with `DOCROOT=www/nova SITE_URL=https://<account>.alwaysdata.net/nova`. The free plan allows one account and only the `<account>.alwaysdata.net` address. The installer's `.htaccess` keeps `/nova/` separate from the parent site.
   - **Paid plan:** a separate site with its own domain also works.
3. *Remote access → SSH*: enable it, and add your key.

**Password files on the server** (mode 600, never in the repo). Start each line with a space, so most shells keep it out of history.

The database password:

```bash
printf '%s' 'YOUR_DB_PASSWORD' | ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net 'umask 077; cat > ~/.dbpass'
```

The admin password, needed only for the first install:

```bash
printf '%s' 'YOUR_ADMIN_PASSWORD' | ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net 'umask 077; cat > ~/.adminpass'
```

**Deploy from your machine**

```bash
ALWAYSDATA_ACCOUNT=ACCOUNT ADMIN_EMAIL=you@example.com DOCROOT=www/nova SITE_URL=https://ACCOUNT.alwaysdata.net/nova ./deploy/alwaysdata-push.sh
```

Optional variables: `DB_NAME`, `ADMIN_USER`, and `RESEED=1`, which resets the catalog, prices and stock to the sample data.

**What the installer handles**
- **HTTPS behind the hosting proxy:** it trusts the `X-Forwarded-Proto` header, and redirects HTTP to HTTPS.
- **Its own `.htaccess`,** so pretty URLs work, including inside a sub-folder.
- **Customer e-mails switched off** on the preview. The admin new-order e-mail and password resets stay on.
- **Hardening:** `WP_DEBUG` off, `DISALLOW_FILE_EDIT` on, and `wp-config.php` at mode 600.
- **Store options:** registration options and the `/login/` and `/register/` pages are applied on every deploy. Other settings stored in the database, such as payment texts, are written only on the first install. Change those in wp-admin, or with WP-CLI over SSH.
- **Safety check:** it refuses to install over a different site or database.
- **Memory:** it stays within the free plan's 256 MB.

</details>

---

## Security

| Area | What the code does |
|---|---|
| Secrets | `.env` is git-ignored, and `.env.example` has placeholders only. Server passwords live in mode-600 files, and CI keys are in GitHub secrets. |
| Core integrity | WordPress and WooCommerce are installed by WP-CLI and never modified. |
| Output | Everything is escaped (`esc_html`, `esc_attr`, `esc_url`). Icons are static SVG defined in code. |
| Input | Settings use the Settings API with a sanitize callback and clamped values. API arguments are validated by the route schema. Names are sanitized and length-checked. |
| Permissions | Settings require `manage_woocommerce`. Admin actions use nonces and capability checks. |
| Accounts | WooCommerce's own login and registration handlers: nonces, unique e-mail, password hashing. |
| Database | Only WordPress and WooCommerce APIs; no raw SQL. |
| Orders | HPOS-safe order meta. Nothing touches totals, payment or stock. |
| API | GET only, fixed field list, bounded parameters, no customer or order data. |
| Payments | Cash on Delivery only; no card data is handled. |

This project follows WordPress security practices but **has not had a professional security audit**. See [Before going live](#before-going-live).

---

## Quality and testing

Install the code-check tools:

```bash
composer install
```

Check PHP syntax:

```bash
composer lint
```

Check WordPress Coding Standards:

```bash
vendor/bin/phpcs
```

- **Automatic checks:** both pass with **0 errors and 0 warnings**, and CI runs them on every push and pull request.
- **No automated test suite:** behaviour is verified with the checklist below, in the browser and with `curl` and WP-CLI.

<details>
<summary><strong>Manual test checklist</strong></summary>

**Catalog**
- [ ] Home shows the hero, values strip, 5 category tiles, *Featured gear*, the promo band and *On sale now*.
- [ ] `/shop/` paginates (8 per page), and filters, searches and sorts.
- [ ] A product page shows the gallery zoom and lightbox, price, stock label, trust block and tabs.
- [ ] *Reference Wired Headphones* shows "Sold out — restocking soon" and has no add-to-cart button.
- [ ] *Dock Pro* shows "Only 2 left in stock" in amber.

**Cart**
- [ ] Add to cart updates the header count without a reload.
- [ ] Quantity changes and removals recalculate the totals.
- [ ] Below $99 the $9.95 rate applies; at $99 or more shipping is free.

**Checkout and orders**
- [ ] Only Cash on Delivery is offered.
- [ ] The phone `12-34` is rejected on the server.
- [ ] A valid order shows "Estimated dispatch: …". In the admin, the order shows the dispatch date, and the stock went down.

**Accounts**
- [ ] Logged out, the header icon and `/my-account/` lead to `/login/`. *Lost your password?* still works.
- [ ] `/login/` and `/register/` each show one form and link to each other.
- [ ] A wrong password shows its error on `/login/`; the right one opens the dashboard.
- [ ] A missing name, or an e-mail that is already used, shows its error on `/register/`.
- [ ] A valid sign-up logs in and greets the customer by name.
- [ ] Logged in, `/login/` and `/register/` redirect to My Account.
- [ ] *Create an account?* at checkout creates the customer with the order.

**Languages**
- [ ] *العربية* switches the same page to Arabic, right-to-left, and the choice is remembered.
- [ ] Products, categories, cart, checkout, payment texts, account pages and errors are in Arabic.
- [ ] wp-admin stays in English.
- [ ] The API with `lang=ar` returns Arabic names; `lang=fr` returns 400.

**API**
- [ ] The default request returns 200.
- [ ] `per_page=999`, `per_page=abc` and `category=nope` each return 400.
- [ ] A `POST` returns 404.

**Responsive**
- [ ] At 375 px the menu opens full-screen, `Esc` closes it, and no page scrolls sideways, in English or Arabic.

</details>

---

## Before going live

The store is ready for review on the preview URL. Before it takes real customers:

**Business details**
- [ ] Replace the footer contact e-mail (`hello@example.test` in `footer.php`) and opening hours.
- [ ] Set the real admin and store e-mail (**Settings → General**, **WooCommerce → Settings → Emails**).
- [ ] Publish a Privacy Policy page (**Settings → Privacy**); the sign-up and checkout privacy notes link to it.
- [ ] Replace the sample catalog with real products and photos.

**Store setup**
- [ ] Set up a real mail service (SMTP), then turn customer e-mails back on.
- [ ] Add online payment if needed (for example Stripe or PayPal) in **WooCommerce → Settings → Payments**.

**Hosting and security**
- [ ] Add a CAPTCHA and rate limiting to login and sign-up; open registration attracts bot accounts.
- [ ] Move to a paid plan with its own domain, backups and more memory.
- [ ] Arrange a security review of the hosting and the custom code.

**Known limitations**
- There is no automated test suite yet; checks are manual.
- Languages use `?lang=` plus a cookie, not `/ar/` URLs. Search engines find the Arabic pages through `hreflang` links. For SEO-heavy needs, a multilingual plugin (Polylang or WPML) is the next step.
- Order items keep the product name in the language the customer ordered in.
- The sample catalog has simple products only. The code supports variable products, such as colour or size options, but none are included.
- The Arabic "Additional information" table can only be set by the seeder; there is no admin field for it yet.
- HPOS compatibility is declared, but the store was tested on WooCommerce's classic order storage.
- Fonts load from Google Fonts. Self-host them if strict GDPR compliance is required.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `port is already allocated` | Change `WP_PORT` and `WP_URL` in `.env`, then `docker compose up -d` |
| "Coming soon" page for visitors | `docker compose run --rm wpcli option update woocommerce_coming_soon no` |
| Product URLs or `/wp-json/` return 404 | `docker compose run --rm wpcli rewrite structure '/%postname%/' --hard` |
| `/login/` or `/register/` return 404 | `docker compose run --rm wpcli eval-file /seed/account-pages.php` |
| Header cart count does not update | Hard-refresh; check the browser console for JavaScript errors |
| `Error establishing a database connection` on first start | MariaDB is still starting; wait about 10 seconds |
| E-mails are not delivered locally | The local stack has no mail server; orders are still created |
| Arabic page shows English text | Fill the *Arabic translation* field, or run `./bin/i18n.sh` for interface text |
| Language keeps switching back | It is stored in the `nova_lang` cookie; use the switcher or `?lang=` |
| Deploy fails with "Killed" | The free plan ran out of memory; make sure only one deploy runs at a time |

---

## Credits

Product, hero and promo photos are from [Unsplash](https://unsplash.com), used under the [Unsplash License](https://unsplash.com/license). Product names and specifications are fictional sample data.

<details>
<summary>Photo list</summary>

| File | Unsplash photo ID |
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

Photo URL: `https://unsplash.com/photos/<id>`.

</details>
