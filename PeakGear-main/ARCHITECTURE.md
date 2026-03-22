# PeakGear Architecture

PeakGear is a Magento 2-based e-commerce platform for outdoor and climbing gear, targeting the Vietnamese market. It runs entirely in Docker and extends Magento with custom modules for Vietnamese payment gateways, live currency rates, and a fully custom dark theme.

---

## Table of Contents

1. [Technology Stack](#technology-stack)
2. [Top-Level Directory Structure](#top-level-directory-structure)
3. [Docker Infrastructure](#docker-infrastructure)
4. [Request Lifecycle](#request-lifecycle)
5. [Custom Modules](#custom-modules)
6. [Custom Theme](#custom-theme)
7. [Caching Architecture](#caching-architecture)
8. [Database](#database)
9. [Custom Routes Reference](#custom-routes-reference)
10. [CMS Pages](#cms-pages)
11. [Key Configuration Files](#key-configuration-files)
12. [Key Files at a Glance](#key-files-at-a-glance)
13. [Quick Start for New Developers](#quick-start-for-new-developers)

---

## Technology Stack

| Layer | Technology | Docker Container |
|---|---|---|
| Web server | Nginx | `peakgear_nginx` (port 80) |
| Application | PHP-FPM + Magento 2 (developer mode) | `peakgear_php` (port 9000) |
| Database | MySQL 8 | `peakgear_mysql` |
| Cache / Sessions | Redis | `peakgear_redis` |
| Search | OpenSearch | `peakgear_opensearch` |
| DB GUI | phpMyAdmin | `peakgear_phpmyadmin` |
| Theme | Custom "climbing" theme (parent: Magento/blank) | — |
| Payments | VNPay, ZaloPay | — |

---

## Top-Level Directory Structure

```
PeakGear/
├── docker/                    # Nginx, PHP, and MySQL config files for Docker
├── docker-compose.yaml        # Orchestrates all 6 containers
├── scripts/                   # Developer utility scripts (setup, fixtures)
│   ├── install-magento.sh     # Full Magento install script
│   ├── setup-for-developer.sh # Dev-mode setup (cache flush, DI compile, etc.)
│   ├── setup-magento.sh       # Base Magento configuration
│   └── data_fixtures.php      # Seed data for local development
├── src/                       # Magento 2 application root (/var/www/html in container)
│   ├── app/code/              # All custom modules (PeakGear, Vendor, Lillik, Utklasad)
│   ├── app/design/            # Custom theme (PeakGear/climbing)
│   ├── app/etc/               # Magento runtime config (env.php, config.php)
│   ├── bin/magento            # Magento CLI tool
│   ├── generated/             # Auto-generated interceptors and DI factories
│   ├── pub/                   # Web root served by Nginx (index.php, static assets)
│   ├── var/                   # Cache, logs, sessions, tmp files
│   ├── vendor/                # Composer-managed dependencies
│   └── composer.json          # PHP dependency manifest
├── LICENSE
└── README.md
```

> `src/` maps to `/var/www/html` inside `peakgear_php` and `peakgear_nginx`. Never edit files directly in `generated/` or `var/` — they are runtime artifacts.

---

## Docker Infrastructure

All services are defined in `docker-compose.yaml`. The containers communicate over an internal Docker network.

```
Browser
  │
  ▼
peakgear_nginx (:80)          — Nginx reverse proxy, serves pub/static directly
  │
  ▼ (FastCGI, port 9000)
peakgear_php                  — PHP-FPM running Magento 2 in developer mode
  │
  ├──▶ peakgear_mysql          — MySQL 8, stores all Magento + custom module data
  ├──▶ peakgear_redis          — Session store + block/full-page cache backend
  └──▶ peakgear_opensearch     — Product catalog search index

peakgear_phpmyadmin            — Web GUI for MySQL (dev only)
```

### Volume Mounts

The `src/` directory on the host is bind-mounted into both `peakgear_php` and `peakgear_nginx` at `/var/www/html`. Changes to PHP/template files take effect immediately in developer mode (no container restart needed).

---

## Request Lifecycle

A complete trace from browser to rendered HTML:

```
Browser HTTP request
  │
  ▼
Nginx (peakgear_nginx:80)
  │  Serves pub/static/ files directly (CSS, JS, images)
  │  Forwards dynamic requests via FastCGI
  ▼
PHP-FPM (peakgear_php:9000)
  │
  ▼
pub/index.php
  │
  ▼
Magento\Framework\App\Bootstrap::create()
  │  Reads src/app/etc/env.php for DB, Redis, crypt key
  ▼
ObjectManager (developer mode: Dynamic/Developer factory)
  │  Reads generated/code/ for interceptors and proxies
  ▼
Magento\Framework\App\Http (via generated Interceptor)
  │
  ▼
Router Dispatch (checked in priority order)
  │
  ├── [sortOrder=25] PeakGear\CartRoute\Model\Router
  │     /cart           → checkout/cart/index
  │     /cart/checkout  → checkout/index/index
  │
  ├── Standard Magento frontName routers
  │     products  → PeakGear\Catalog\Controller\Index\Index
  │     vnpay     → Vendor\VNPay controllers
  │     zalopay   → Vendor\ZaloPay controllers
  │     currency  → Vendor\CurrencyRate\Controller\Index\Index
  │     news      → Vendor\NewsRss\Controller\Index\Index
  │     weather   → Vendor\Weather controllers
  │     catalog, checkout, customer, cms, etc. (Magento core)
  │
  ▼
Layout XML Processing
  │  default.xml (always applied) + page-specific layout XML
  │  → Removes default Magento header/footer blocks
  │  → Injects PeakGear\Catalog\Block\CategoryList into header + footer
  │  → Instantiates all Blocks and ViewModels
  ▼
Template Rendering (.phtml files)
  │  Blocks call Redis/MySQL/OpenSearch as needed
  │  CategoryList and FeaturedProducts served from Redis cache (3600s TTL)
  ▼
HTTP Response (HTML)
  │
  ▼
Browser receives HTML + loads pub/static/ assets (CSS/JS/images)
```

---

## Custom Modules

All custom modules live under `src/app/code/` and are organized into four namespaces.

### PeakGear Namespace (Core Project Modules)

---

#### PeakGear/Catalog — PRIMARY MODULE

This is the most important custom module. It owns the product listing page, injects navigation into the header and footer, and manages category data with caching.

**Key responsibilities:**
- Renders the `/products` catalog page
- Provides `CategoryList` block injected into both header and footer via `default.xml`
- Provides `FeaturedProducts` block for homepage
- Handles category data setup via data patches (EAV attribute + seed categories)

**Blocks:**

| Block Class | Role |
|---|---|
| `Block/CategoryList.php` | Loads top-level categories with subcategories and product counts; results cached 3600s in Redis; maps Vietnamese category names to inline SVGs via `getIconByCategoryName()` |
| `Block/AllProducts.php` | Powers the `/products` listing page |
| `Block/FeaturedProducts.php` | Renders featured product cards on the homepage; cached 3600s |

**ViewModels:**

| ViewModel | Role |
|---|---|
| `ViewModel/CheckoutContext.php` | Exposes checkout-related data (cart totals, quote ID) to templates |
| `ViewModel/ProductViewData.php` | Exposes product detail data to PDP templates |

**Data Patches (run once on `bin/magento setup:upgrade`):**

| Patch | Effect |
|---|---|
| `Setup/Patch/Data/AddCategoryIconAttribute.php` | Adds `category_icon` EAV attribute to the category entity |
| `Setup/Patch/Data/CreateDefaultCategories.php` | Seeds default Vietnamese product categories |

**Key files:**
```
src/app/code/PeakGear/Catalog/
├── Block/
│   ├── CategoryList.php          # Header/footer nav, cached, SVG icons
│   ├── AllProducts.php           # /products page block
│   └── FeaturedProducts.php      # Homepage featured block
├── Controller/Index/Index.php    # /products route handler
├── Model/CatalogFilterDataProvider.php
├── ViewModel/
│   ├── CheckoutContext.php
│   └── ProductViewData.php
├── Setup/Patch/Data/
│   ├── AddCategoryIconAttribute.php
│   └── CreateDefaultCategories.php
├── etc/frontend/
│   ├── di.xml                    # cache_lifetime=3600 for blocks
│   └── routes.xml                # frontName=products
├── etc/module.xml
├── composer.json
└── registration.php
```

---

#### PeakGear/Cart

A lightweight module providing a direct `/cart` controller. In practice, the actual URL routing for `/cart` is handled by `PeakGear/CartRoute`.

**Files:** `Controller/Index/Index.php`, `etc/frontend/routes.xml`, `etc/module.xml`, `registration.php`

---

#### PeakGear/CartRoute

Injects a custom Magento router at `sortOrder=25` (runs before standard routers) to redirect cart and checkout paths.

**Routing rules:**

| Incoming URL | Forwarded To |
|---|---|
| `/cart` | `checkout/cart/index` |
| `/cart/checkout` | `checkout/index/index` |

**Files:** `Model/Router.php`, `etc/frontend/di.xml`, `etc/module.xml`, `registration.php`

---

### Vendor Namespace (Custom Integrations)

---

#### Vendor/VNPay — VNPay Payment Gateway

Implements VNPay redirect-based payment flow, a major Vietnamese payment provider.

**Flow:**
```
Customer places order
  → /vnpay/payment/redirect    (builds signed redirect URL to VNPay)
  → VNPay hosted payment page  (customer pays)
  → /vnpay/payment/return      (VNPay redirects back)
  → PaymentStateApplier        (validates response, updates order status)
```

**Key classes:**
- `Model/Payment/VNPay.php` — Extends Magento's abstract payment method
- `Model/Payment/SignatureService.php` — HMAC-SHA512 request/response signing
- `Model/Order/PaymentStateApplier.php` — Applies order status based on VNPay return data

---

#### Vendor/ZaloPay — ZaloPay Payment Gateway

Implements ZaloPay callback-based payment flow.

**Flow:**
```
Customer places order
  → /zalopay/payment/create    (calls ZaloPay API via ApiClient, gets payment token)
  → ZaloPay hosted page        (customer pays)
  → /zalopay/payment/callback  (ZaloPay POSTs callback to store)
  → SignatureService validates  → order status updated
```

**Key classes:**
- `Model/Payment/ApiClient.php` — ZaloPay REST API calls
- `Model/Payment/SignatureService.php` — MAC signing for ZaloPay requests/callbacks

---

#### Vendor/CurrencyRate — Live USD/VND Exchange Rate

Fetches the official USD/VND rate from Vietcombank and displays it on the frontend.

**Architecture:**
- `Model/Http/VietcombankRateProvider.php` — Parses Vietcombank's public XML feed
- Rate is stored in a **custom DB table** (defined in `etc/db_schema.xml`)
- A **Magento cron job** (`RefreshCurrencyRate`) refreshes the rate daily
- Frontend block `CurrencyRate` renders the current rate
- Route: `/currency`

---

#### Vendor/NewsRss — RSS News Feed

Fetches and caches news articles from an RSS feed.

**Architecture:**
- Cron job `RefreshNews` fetches articles on a schedule
- Articles are persisted to a **custom DB table**
- Frontend block `News` renders cached articles
- Route: `/news`

---

#### Vendor/Weather — OpenWeather API Widget

Provides a weather widget using the OpenWeather API.

**Architecture:**
- Cron job `RefreshWeather` periodically fetches and stores weather data
- Three controllers: `Index` (weather page), `Data` (JSON API endpoint), `Weekly` (7-day forecast)
- Console command `CreateFixtures` generates dev fixture data
- Route: `/weather`

---

### Lillik Namespace (Third-Party: Price Decimal Precision)

#### Lillik/PriceDecimal

Controls the number of decimal places shown for prices across the storefront and admin.

- Admin UI panel to configure decimal precision
- Four plugins intercept price formatting: `PriceCurrency`, `Local/Format`, `OrderPlugin`, `Currency`
- Frontend JS override at `view/frontend/web/js/price-utils.js` ensures consistency in client-side price rendering

---

### Utklasad Namespace (Third-Party: Admin Enhancement)

#### Utklasad/AdminProductGridCategoryFilter

Adds a category column and filter to the admin product grid, making it easier to manage products by category.

- Uses Magento `<preference>` overrides (in `di.xml`) to replace four `DataProvider` classes:
  - `product_listing`
  - `related_product_listing`
  - `crosssell_product_listing`
  - `upsell_product_listing`

---

## Custom Theme

### Overview

**Location:** `src/app/design/frontend/PeakGear/climbing/`

**Parent theme:** `Magento/blank`

The "climbing" theme is a complete custom dark theme designed for the Vietnamese outdoor gear market. It overrides Magento's default header and footer entirely, applying a dark color palette with amber accent colors.

**Design tokens:**

| Token | Value | Usage |
|---|---|---|
| Dark background | `#0f0f1a` | Page, header, footer backgrounds |
| Amber primary | `#f59e0b` | Buttons, links, highlights |
| Text | `#ffffff` | Body text on dark backgrounds |
| Body font | Montserrat | UI text, navigation, buttons |
| Heading font | Playfair Display | Page headings, product names |

Both fonts are loaded from Google Fonts via `default.xml`.

---

### Theme Structure

```
PeakGear/climbing/
├── theme.xml                          # Parent=Magento/blank declaration
├── registration.php                   # Theme registration
├── requirejs-config.js                # JS module aliases/overrides
├── etc/view.xml                       # Product image resize configuration
│
├── web/
│   ├── css/source/_theme.less         # PRIMARY stylesheet (~9495 lines, all custom styles)
│   ├── css/peakgear-icons.css         # Icon overrides (search panel, action buttons)
│   ├── js/
│   │   ├── header.js                  # Mobile menu, dropdown nav, minicart behavior
│   │   ├── hero-section.js            # Homepage hero parallax and entrance animations
│   │   ├── product-list.js            # Filter/sort UI for product listing pages
│   │   └── toast-messages.js          # Toast notification auto-dismiss logic
│   └── images/                        # hero-mountains.jpg, 40+ brand SVGs, placeholders
│
├── Magento_Theme/
│   ├── layout/default.xml             # CRITICAL: Controls global layout for all pages
│   └── templates/html/
│       ├── header.phtml               # Custom dark header with navigation
│       ├── footer.phtml               # Custom footer with links and branding
│       └── messages.phtml             # Toast-styled flash messages
│
├── Magento_Catalog/
│   ├── layout/catalog_category_view.xml
│   ├── layout/catalog_product_view.xml
│   └── templates/
│       ├── category/header.phtml
│       ├── category/sidebar-filters.phtml
│       └── product/*.phtml            # PDP templates
│
├── Magento_Checkout/
│   ├── layout/checkout_cart_index.xml
│   ├── layout/checkout_index_index.xml
│   └── templates/
│       ├── cart/*.phtml
│       └── checkout-wrapper.phtml
│
├── Magento_Cms/
│   ├── layout/cms_index_index.xml                         # Homepage layout
│   ├── layout/cms_page_view_id_about.xml
│   ├── layout/cms_page_view_id_policies.xml
│   ├── layout/cms_page_view_id_chinh-sach-bao-mat.xml
│   ├── layout/cms_page_view_id_dieu-khoan-su-dung.xml
│   └── templates/
│       ├── homepage/*.phtml
│       ├── about/*.phtml
│       └── legal/*.phtml
│
├── Magento_Customer/
│   ├── layout/                        # Login and registration page layouts
│   └── templates/form/
│       ├── login.phtml
│       ├── register.phtml
│       └── social-login.phtml
│
├── Magento_Contact/
│   └── layout/ + templates/form.phtml
│
├── Magento_LayeredNavigation/
│   └── templates/layer/view.phtml     # Custom faceted filter sidebar
│
└── PeakGear_Catalog/
    ├── layout/peakgear_catalog_index_index.xml
    └── templates/products/all.phtml   # All-products listing template
```

---

### default.xml — Global Layout Configuration

`Magento_Theme/layout/default.xml` is the most important layout file. It applies to every page load and establishes the global page structure.

**What it does:**

1. **Head injections:** Loads Google Fonts (Montserrat + Playfair Display) and `peakgear-icons.css`
2. **Body class:** Adds `peakgear-theme` class to `<body>` for CSS scoping
3. **Header:** Removes ALL default Magento header blocks (logo, top navigation, search bar, minicart, etc.), then injects `PeakGear\Catalog\Block\CategoryList` into `header.container` with `header.phtml` as its template
4. **Footer:** Removes ALL default Magento footer blocks, then injects `PeakGear\Catalog\Block\CategoryList` into `footer-container` with `footer.phtml` as its template

The `CategoryList` block is shared between header and footer — both templates receive the same category tree data (cached in Redis at 3600s TTL) and each formats it differently.

---

## Caching Architecture

PeakGear uses a layered caching strategy:

```
Layer               Backend         TTL / Invalidation
─────────────────── ─────────────── ───────────────────────────────────────
PHP sessions        Redis           Magento session lifetime config
Magento block cache Redis           Per-block (CategoryList: 3600s)
Full Page Cache     Redis (FPC)     Invalidated by Category::CACHE_TAG
Config cache        Redis           Invalidated on config change / deploy
CurrencyRate data   Custom DB table Refreshed daily by cron
NewsRss articles    Custom DB table Refreshed on schedule by cron
Weather data        Custom DB table Refreshed on schedule by cron
OpenSearch index    OpenSearch      Updated on product save / full reindex
```

**Notes:**
- `CategoryList` and `FeaturedProducts` both use `cache_lifetime=3600` configured in `PeakGear/Catalog/etc/frontend/di.xml`
- The Full Page Cache is tagged with `Category::CACHE_TAG` so category changes automatically invalidate relevant cached pages
- Vendor module data (currency, news, weather) bypasses Magento's block cache and uses custom DB tables as their own persistence layer, refreshed by Magento cron

---

## Database

**Engine:** MySQL 8 (`peakgear_mysql`)

**Schema sources:**

| Source | Description |
|---|---|
| Magento core | Standard Magento 2 schema (catalog, sales, customer, etc.) |
| `Vendor/CurrencyRate/etc/db_schema.xml` | Stores fetched USD/VND exchange rates |
| `Vendor/NewsRss/etc/db_schema.xml` | Stores fetched RSS news articles |
| `Vendor/Weather/etc/db_schema.xml` | Stores fetched weather data |

Custom tables are created/updated by running `bin/magento setup:upgrade`.

---

## Custom Routes Reference

| URL Pattern | Handler | Notes |
|---|---|---|
| `/products` | `PeakGear\Catalog\Controller\Index\Index` | All-products listing page |
| `/cart` | `PeakGear\CartRoute\Model\Router` | Forwards to `checkout/cart/index` |
| `/cart/checkout` | `PeakGear\CartRoute\Model\Router` | Forwards to `checkout/index/index` |
| `/vnpay/payment/redirect` | `Vendor\VNPay\Controller\Payment\Redirect` | Initiates VNPay payment |
| `/vnpay/payment/return` | `Vendor\VNPay\Controller\Payment\Return_` | VNPay return handler |
| `/zalopay/payment/create` | `Vendor\ZaloPay\Controller\Payment\Create` | Initiates ZaloPay payment |
| `/zalopay/payment/callback` | `Vendor\ZaloPay\Controller\Payment\Callback` | ZaloPay async callback |
| `/currency` | `Vendor\CurrencyRate\Controller\Index\Index` | Currency rate display page |
| `/news` | `Vendor\NewsRss\Controller\Index\Index` | News feed page |
| `/weather` | `Vendor\Weather\Controller\Index\Index` | Weather widget page |

---

## CMS Pages

| URL | Page Title | Layout File |
|---|---|---|
| `/` | Homepage | `cms_index_index.xml` |
| `/about` | About PeakGear | `cms_page_view_id_about.xml` |
| `/policies` | Chinh sach & Dieu khoan | `cms_page_view_id_policies.xml` |
| `/chinh-sach-bao-mat` | Chinh sach bao mat | `cms_page_view_id_chinh-sach-bao-mat.xml` |
| `/dieu-khoan-su-dung` | Dieu khoan su dung | `cms_page_view_id_dieu-khoan-su-dung.xml` |

---

## Key Configuration Files

| File | Purpose | In Git? |
|---|---|---|
| `src/app/etc/env.php` | DB credentials, Redis host/port, crypt key, base URL | No (gitignored) |
| `src/app/etc/config.php` | Module enable/disable list | Yes |
| `docker-compose.yaml` | Container definitions, port mappings, volume mounts | Yes |
| `src/composer.json` | PHP dependency manifest | Yes |
| `docker/` | Nginx, PHP-FPM, and MySQL config files | Yes |

`env.php` must be created manually during local setup (see Quick Start below). It is never committed.

---

## Key Files at a Glance

| File | Why It Matters |
|---|---|
| `docker-compose.yaml` | Single source of truth for the entire dev infrastructure |
| `src/app/design/frontend/PeakGear/climbing/Magento_Theme/layout/default.xml` | Controls global page structure; injects custom header/footer and removes all Magento defaults |
| `src/app/design/frontend/PeakGear/climbing/web/css/source/_theme.less` | All storefront CSS (~9495 lines); the entire visual identity lives here |
| `src/app/code/PeakGear/Catalog/Block/CategoryList.php` | Navigation block used in both header and footer; cached 3600s |
| `src/app/code/PeakGear/CartRoute/Model/Router.php` | Custom router that intercepts `/cart` paths before Magento's standard routers |
| `src/app/code/Vendor/VNPay/Model/Payment/VNPay.php` | VNPay payment method implementation |
| `src/app/code/Vendor/VNPay/Model/Payment/SignatureService.php` | HMAC-SHA512 signing for VNPay |
| `src/app/code/Vendor/ZaloPay/Model/Payment/ApiClient.php` | ZaloPay API client |
| `src/app/code/Vendor/CurrencyRate/Model/Http/VietcombankRateProvider.php` | Fetches USD/VND rate from Vietcombank XML feed |
| `src/app/etc/env.php` | Runtime credentials (DB, Redis, crypt key) — NOT in git |
| `src/app/etc/config.php` | Module enable list — in git |
| `scripts/install-magento.sh` | Full automated Magento install script |
| `scripts/setup-for-developer.sh` | Dev-mode setup: cache flush, DI compile, static deploy |

---

## Quick Start for New Developers

### Prerequisites

- Docker Desktop (or Docker Engine + Compose plugin)
- Git

### 1. Clone and Start Containers

```bash
git clone <repository-url> PeakGear
cd PeakGear
docker-compose up -d
```

This starts all six containers: nginx, php, mysql, redis, opensearch, phpmyadmin.

### 2. Install Magento

Run the install script inside the PHP container:

```bash
docker exec -it peakgear_php bash
cd /var/www/html
bash /path/to/scripts/install-magento.sh
```

Or from the host:

```bash
docker exec peakgear_php bash scripts/install-magento.sh
```

This creates `src/app/etc/env.php` with DB and Redis credentials, and runs `setup:install`.

### 3. Run Setup for Developer Mode

```bash
docker exec peakgear_php bash scripts/setup-for-developer.sh
```

This runs the following Magento CLI commands:
- `bin/magento deploy:mode:set developer`
- `bin/magento setup:upgrade` (creates custom DB tables, applies data patches)
- `bin/magento setup:di:compile` (generates interceptors in `generated/`)
- `bin/magento cache:flush`

### 4. (Optional) Load Sample Data

```bash
docker exec peakgear_php php scripts/data_fixtures.php
```

### 5. Access the Store

| Service | URL |
|---|---|
| Storefront | http://localhost |
| Magento Admin | http://localhost/admin |
| phpMyAdmin | http://localhost:8080 |

### Common Magento CLI Commands

Run these inside the PHP container (`docker exec -it peakgear_php bash`):

```bash
# Flush all caches
bin/magento cache:flush

# Re-run data patches and update DB schema
bin/magento setup:upgrade

# Recompile DI (required after adding plugins/preferences)
bin/magento setup:di:compile

# Deploy static assets (required in production mode)
bin/magento setup:static-content:deploy -f

# Reindex search and catalog
bin/magento indexer:reindex

# Tail application logs
tail -f var/log/system.log
tail -f var/log/exception.log
```

### Developer Mode Notes

- In developer mode, Magento does NOT cache generated files — PHP errors show full stack traces
- Static assets (CSS/JS) are generated on the fly from `pub/static/`; no need to run `setup:static-content:deploy`
- Changes to `.phtml` templates and `.less` files are reflected on page reload
- Changes to PHP classes (blocks, plugins, models) require clearing `generated/` and running `setup:di:compile`
- Changes to `layout/*.xml` files require flushing the layout cache: `bin/magento cache:clean layout`

### Adding a New Custom Module

1. Create `src/app/code/<Namespace>/<ModuleName>/registration.php` and `etc/module.xml`
2. Add the module to `src/app/etc/config.php` (or run `bin/magento module:enable <Namespace_ModuleName>`)
3. Run `bin/magento setup:upgrade` and `bin/magento setup:di:compile`
4. Follow the existing module patterns in `src/app/code/PeakGear/Catalog/` as a reference
