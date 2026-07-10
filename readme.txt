=== ShopForge ===
Contributors: andreaemili
Tags: woocommerce, account, wishlist, returns, rma
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.12.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WooCommerce plugin: redesigned account area, shipment tracking, wishlist, returns/RMA, quotes, notifications and theme skins.

== Description ==

ShopForge extends WooCommerce with a set of independent modules and a
redesigned account area. Every module can be enabled or disabled on its own,
so you only load the code and CSS you actually use.

= Core features =

* **Account area styles** — card-based dashboard, order list, status badges.
* **Shop/catalog styles** — restyled cart and checkout (classic and block checkout).
* **Custom colors** — a full color palette editable from the settings screen, injected as CSS variables.
* **Custom dashboard** — replaces the default WooCommerce dashboard with order stats, a tracking widget and address cards.
* **Themes (skins)** — three ready-made layouts (Clean, Boxed, Cards) selectable globally or per page (cart / checkout / account / other).

= Modules =

* **Shipment tracking** — tracking widget on the order page, powered by the 17track API (a free API key is required, see Settings).
* **Wishlist** — customers save favorite products and find them in their account.
* **Quotes** — customers request a custom quote for a list of products; the shop owner replies from a dedicated admin page.
* **Support & Returns** — a two-step withdrawal ("recesso") flow compliant with EU consumer law, with an automatic confirmation email.
* **Product Support (RMA)** — structured repair / replacement / refund requests per order line, with a message thread, CSV export, print view and stats.
* **Notifications** — an in-account notification center fed by real events (order status, tickets, returns, RMA, quotes, back-in-stock wishlist items).
* **Loyalty Points** — customers earn points on completed orders (reversed if later refunded/cancelled) and redeem them for a discount coupon. Earn rate, point value and minimum redemption are configurable.
* **PDF Receipts** — generates a real server-side PDF receipt for each order (not a fiscal invoice), with a choice of visual templates, your logo, company details and a footer note. Downloadable from the order page and the customer account, with one-click email delivery.
* **Italian Fiscal Checkout Fields** — adds Customer type (Private/Business), Tax code, VAT number, SDI code and PEC to the block checkout, natively (WooCommerce Blocks Additional Fields API). Fields are shown and required based on the selected customer type. Classic (shortcode) checkout is not covered.

= Shortcodes =

* `[shopforge_variation_description]` — shows the description of the selected product variation.
* `[wc_price_iva_box]` — price box with tax included/excluded.
* `[data_consegna_prodotto]` — estimated delivery date based on business days and public holidays.
* `[buy_now_button]` — adds to cart and redirects straight to checkout.
* `[stock_status_text]` — availability label with a colored dot.
* `[product_faq]` — product FAQ, managed from a metabox on the product edit screen.
* `[product_compatibility]` — compatibility list, managed from a metabox.
* `[product_datasheets]` — PDF datasheets, managed from a metabox.

Full attribute reference is available in-app under ShopForge → Shortcodes.

= Admin tools =

* **Open-requests alert** — editing an order shows a banner if it has open support tickets, withdrawal requests or RMA, linking straight to each one.
* **Dashboard widgets** — two widgets on the native WordPress Dashboard: a sales overview (orders/revenue, 7-day chart) and a customer-requests overview (open counts + recent activity across tickets, withdrawals, RMA and quotes).
* **Messages** — a single admin page aggregating every customer request (tickets, withdrawals, RMA, quotes) with type/status filters and search, each row linking to where it is actually managed. Data stays where it already lives; this is an overview, not a new store.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/shopforge`, or install the zip through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. WooCommerce must be active.
3. Go to **ShopForge** in the admin menu and enter your license key under the **License** tab.
4. Enable the modules and features you need under the **Modules** tab.
5. Optionally pick a theme (skin) and customize colors in the same screen.
6. If you enable **Shipment tracking**, get a free API key at 17track.net and paste it into the *17track API key* field under Configuration.

== Frequently Asked Questions ==

= Does this work with the WooCommerce block-based cart and checkout? =

Yes. The catalog/shop styles cover both the classic shortcode-based cart/checkout and the block-based ones, and the skin system applies to both.

= Can I use only some modules? =

Yes, every module and core feature has its own toggle under ShopForge → Modules. Disabled modules load no code, no CSS, and register no endpoints.

= Does the plugin add its own tracking service? =

No, shipment tracking relies on 17track.net's public API. You need to register your own free API key on their site and paste it in ShopForge → Modules → Configuration.

= Is the plugin translation-ready? =

Yes, all strings are wrapped for translation with the `shopforge` text domain. An Italian translation is bundled; a `.pot` file is included under `languages/` for adding more.

== Changelog ==

= 1.12.3 =
* Fixed the "Open RMA" (and "Open withdrawals" / "Pending quotes") badge on the Dashboard requests widget: it always linked to those modules' pages even when disabled, causing an "Invalid post type" error for RMA. Each badge now only shows if its module is active.

= 1.12.2 =
* Moved the version history out of this file into `CHANGELOG.md`, keeping only the latest entry here.

= 1.12.1 =
* Fixed the Receipts module: it registered a "Receipts" account menu item and a /shopforge-receipts/ endpoint with no page behind it, so visiting that URL silently fell back to the dashboard. Removed — receipts are downloaded from each order's own page, not a dedicated account tab.
* Notifications now always appears as the second item in the account menu, right after Dashboard.

Full version history: see `CHANGELOG.md` in the plugin root.
