=== ShopForge ===
Contributors: andreaemili
Tags: woocommerce, account, wishlist, returns, rma
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.10.1
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

= 1.10.1 =
* Fixed the Receipts settings tab rendering with no styling (borrowed CSS classes that only load on the Modules tab; the tab now ships its own scoped styles).
* Fixed 22 strings incorrectly translated by the automated merge (e.g. "Contact email" showing an unrelated Italian string) across the Receipts module and a few pre-existing WooCommerce template overrides.

= 1.10.0 =
* New PDF Receipts module: real server-generated PDF (Dompdf), 3 visual templates (Modern/Classic/Minimal), configurable company details/logo/footer note, one-click download from the order page and customer account, one-click email delivery. This is a receipt, not a fiscal invoice — no electronic-invoicing (SDI) integration.
* Italian translation completed for all new strings, 100% coverage verified against source (728/728).

= 1.9.1 =
* Removed all emoji from admin UI, notices and JS feedback text — plain text and native icons only.
* Completed the Italian translation: all strings introduced in 1.9.0 (Loyalty Points, order alert, dashboard widgets, Messages page) are now translated, 100% coverage verified against source.

= 1.9.0 =
* New Loyalty Points module: earn on completed orders (reversed on refund/cancel), redeem for a discount coupon, configurable earn rate/point value/minimum redemption.
* New admin order-edit alert for orders with open tickets, withdrawal requests or RMA.
* New WordPress Dashboard widgets: sales overview with a 7-day chart, and a customer-requests overview with recent activity.
* New unified "Messages" admin page aggregating tickets, withdrawals, RMA and quotes with filters and search.
* Notifications now also cover ticket/return/RMA status updates and wishlist back-in-stock.
* Auto-issue a store-credit coupon and set order status to Refunded when a withdrawal request is approved as refunded.
* Fixed returns modal not becoming visible, unified modal/support-panel text sizes, unified RMA form styling with the rest of the account UI.
* Fixed account navigation not stretching to full width outside the two-column layout.

= 1.8.0 =
* Full English base + Italian translation, translation-ready throughout.
* FontAwesome bundled locally, no external kit dependency.
* Added `uninstall.php` to clean up plugin options on removal.
* Added a themeable skin system (Clean / Boxed / Cards) selectable globally or per page.
* 17track API key is now configurable from Settings instead of hardcoded.

= 1.7.x =
* Cart/checkout layout and theme-compatibility fixes.
* Quantity selector redesign.

= 1.6.0 =
* Added the RMA module, product-info shortcodes, unified admin page.
